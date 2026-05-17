import { NextRequest, NextResponse } from "next/server";
import { tiGlobalUserId, APP_SLUG } from "@/lib/legal/sign-request";

export const dynamic = "force-dynamic";

export async function GET(request: NextRequest) {
  const { searchParams } = new URL(request.url);
  const rawUserId = searchParams.get("userId");

  if (!rawUserId) {
    return NextResponse.json({ status: [] });
  }

  const legalUrl = process.env.LEGAL_API_URL;
  if (!legalUrl) {
    return NextResponse.json({ status: [] });
  }

  try {
    const globalUserId = tiGlobalUserId(rawUserId);
    const url = `${legalUrl.replace(/\/$/, "")}/api/v1/consent/status/${APP_SLUG}/${encodeURIComponent(globalUserId)}`;
    const res = await fetch(url, {
      headers: { Accept: "application/json" },
      signal: AbortSignal.timeout(8000),
    });
    if (!res.ok) {
      return NextResponse.json({ status: [] });
    }
    const data = await res.json();
    return NextResponse.json({ status: data.status ?? [] }, {
      headers: { "Cache-Control": "no-store" },
    });
  } catch {
    return NextResponse.json({ status: [] });
  }
}
