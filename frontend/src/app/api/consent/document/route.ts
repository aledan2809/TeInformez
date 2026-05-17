import { NextRequest, NextResponse } from "next/server";
import { APP_SLUG } from "@/lib/legal/sign-request";

const VALID_TYPES = ["tos", "privacy", "cookies"] as const;
type ValidType = (typeof VALID_TYPES)[number];

function isValidType(t: string): t is ValidType {
  return (VALID_TYPES as readonly string[]).includes(t);
}

function substituteTokens(text: string, vars: Record<string, string>): string {
  let result = text;
  for (const [token, value] of Object.entries(vars)) {
    result = result.replaceAll(token, value);
  }
  return result;
}

function formatAddress(address: unknown): string {
  if (!address || typeof address !== "object") return "—";
  const a = address as Record<string, string | undefined>;
  const parts = [a.street, a.city, a.postal, a.country].filter(Boolean);
  return parts.length ? parts.join(", ") : "—";
}

export const dynamic = "force-dynamic";

export async function GET(request: NextRequest) {
  const { searchParams } = new URL(request.url);
  const type = (searchParams.get("type") ?? "tos").toLowerCase();

  if (!isValidType(type)) {
    return NextResponse.json(
      { error: `type must be one of ${VALID_TYPES.join(", ")}` },
      { status: 400 },
    );
  }

  const apiUrl = process.env.LEGAL_API_URL;
  if (!apiUrl) {
    return NextResponse.json({ error: "Legal Hub not configured" }, { status: 503 });
  }

  try {
    const url = `${apiUrl.replace(/\/$/, "")}/api/v1/public/legal/${APP_SLUG}/${type}`;
    const res = await fetch(url, {
      headers: { Accept: "application/json" },
      signal: AbortSignal.timeout(8000),
    });

    if (!res.ok) {
      return NextResponse.json(
        { error: "Legal Hub upstream error", upstream: res.status },
        { status: 502 },
      );
    }

    const body = await res.json();
    const entity = body.entity ?? {};
    const version = body.version ?? {};

    if (!version.id || !version.contentMarkdown) {
      return NextResponse.json({ error: "Legal Hub response malformed" }, { status: 502 });
    }

    const effectiveDateStr = version.effectiveFrom
      ? new Intl.DateTimeFormat("ro-RO", { year: "numeric", month: "long", day: "2-digit" }).format(
          new Date(version.effectiveFrom),
        )
      : "—";

    const vars: Record<string, string> = {
      "{entity_name}": entity.name ?? "—",
      "{entity_cui}": entity.cui ?? "—",
      "{entity_jurisdiction}": entity.jurisdiction ?? "—",
      "{entity_address}": formatAddress(entity.address),
      "{entity_dpo_email}": entity.dpoEmail ?? "—",
      "{effective_date}": effectiveDateStr,
      "{version}": version.version ?? "—",
      "{app_name}": "TeInformez",
      "{app_slug}": APP_SLUG,
      "{user_email}": "—",
      "{rendered_at}": new Intl.DateTimeFormat("ro-RO", {
        year: "numeric",
        month: "long",
        day: "2-digit",
      }).format(new Date()),
    };

    const rendered = substituteTokens(version.contentMarkdown, vars);

    return NextResponse.json(
      {
        contentMarkdown: rendered,
        versionId: version.id,
        version: version.version ?? null,
        effectiveFrom: version.effectiveFrom ?? null,
        entityName: entity.name ?? null,
      },
      { headers: { "Cache-Control": "public, max-age=300, stale-while-revalidate=60" } },
    );
  } catch (err) {
    const message = err instanceof Error ? err.message : "unknown";
    return NextResponse.json(
      { error: "Failed to fetch document", detail: message },
      { status: 502 },
    );
  }
}
