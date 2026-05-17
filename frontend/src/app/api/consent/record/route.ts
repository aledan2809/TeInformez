import { NextRequest, NextResponse } from "next/server";
import { signLegalRequest, tiGlobalUserId, APP_SLUG } from "@/lib/legal/sign-request";

export async function POST(request: NextRequest) {
  const body = await request.json().catch(() => null);
  if (!body?.documentVersionId || typeof body.documentVersionId !== "string") {
    return NextResponse.json({ error: "documentVersionId required" }, { status: 400 });
  }
  if (!body.consentText || typeof body.consentText !== "string" || body.consentText.trim().length < 10) {
    return NextResponse.json({ error: "consentText must be at least 10 characters" }, { status: 400 });
  }
  if (body.consentText.length > 2000) {
    return NextResponse.json({ error: "consentText must not exceed 2000 characters" }, { status: 400 });
  }
  if (!body.userId) {
    return NextResponse.json({ error: "userId required" }, { status: 400 });
  }

  const legalUrl = process.env.LEGAL_API_URL;
  if (!legalUrl) {
    return NextResponse.json({ error: "Legal Hub not configured" }, { status: 503 });
  }

  const forwarded = request.headers.get("x-forwarded-for");
  const ip = forwarded ? forwarded.split(",")[0].trim() : "unknown";
  const userAgent = request.headers.get("user-agent") ?? "";
  const globalUserId = tiGlobalUserId(body.userId);

  const requestPayload = {
    appSlug: APP_SLUG,
    documentVersionId: body.documentVersionId as string,
    consentText: body.consentText as string,
    method: typeof body.method === "string" ? body.method : "IN_APP",
  };
  const bodyText = JSON.stringify(requestPayload);

  let signedHeaders: Record<string, string> = {};
  try {
    const signed = signLegalRequest({ globalUserId, bodyText });
    if (signed) {
      signedHeaders = { ...signed };
    }
  } catch (e) {
    const message = e instanceof Error ? e.message : "HMAC signing failed";
    console.error("[consent/record] sign error:", message);
    return NextResponse.json({ error: "Consent signing misconfigured" }, { status: 500 });
  }

  const res = await fetch(`${legalUrl.replace(/\/$/, "")}/api/v1/consents/record`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "x-forwarded-for": ip,
      "user-agent": userAgent,
      ...signedHeaders,
    },
    body: bodyText,
    signal: AbortSignal.timeout(8000),
  });

  const data = await res.json().catch(() => null);
  if (data === null) {
    return NextResponse.json({ error: "Legal Hub response not parseable" }, { status: 502 });
  }
  return NextResponse.json(data, { status: res.status });
}
