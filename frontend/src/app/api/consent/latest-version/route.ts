import { NextRequest, NextResponse } from "next/server";
import { APP_SLUG } from "@/lib/legal/sign-request";

export const dynamic = "force-dynamic";

export async function GET(_req: NextRequest) {
  const apiUrl = process.env.LEGAL_API_URL;
  if (!apiUrl) {
    return NextResponse.json({ error: "Legal Hub not configured" }, { status: 503 });
  }

  try {
    const res = await fetch(
      `${apiUrl.replace(/\/$/, "")}/api/v1/public/legal/${APP_SLUG}/privacy`,
      { headers: { Accept: "application/json" }, signal: AbortSignal.timeout(6000) },
    );

    if (!res.ok) {
      return NextResponse.json({ error: "Legal Hub upstream error" }, { status: 502 });
    }

    const body = await res.json();
    const versionId = body?.version?.id;
    if (!versionId) {
      return NextResponse.json({ error: "Version not found" }, { status: 404 });
    }

    return NextResponse.json(
      { versionId, version: body.version.version ?? null },
      { headers: { "Cache-Control": "public, max-age=300, stale-while-revalidate=60" } },
    );
  } catch (err) {
    const msg = err instanceof Error ? err.message : "unknown";
    return NextResponse.json({ error: "Failed to reach Legal Hub", detail: msg }, { status: 502 });
  }
}
