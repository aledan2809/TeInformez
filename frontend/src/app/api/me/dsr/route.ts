import { NextRequest, NextResponse } from "next/server";

const VALID_TYPES = ["ACCESS", "DELETE", "RECTIFY", "PORTABILITY", "OBJECTION", "OTHER"] as const;

export async function POST(request: NextRequest) {
  let body: Record<string, unknown> | null = null;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ error: "Invalid request body" }, { status: 400 });
  }
  if (!body) {
    return NextResponse.json({ error: "Invalid request body" }, { status: 400 });
  }

  const { name, email, type, description } = body;

  if (!name || typeof name !== "string" || name.trim().length < 2) {
    return NextResponse.json({ error: "Numele este obligatoriu (minim 2 caractere)." }, { status: 400 });
  }
  const trimmedEmail = typeof email === "string" ? email.trim() : "";
  if (!trimmedEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedEmail)) {
    return NextResponse.json({ error: "Adresa de email nu este validă." }, { status: 400 });
  }
  const normalizedType = typeof type === "string" ? type.toUpperCase() : "";
  if (!VALID_TYPES.includes(normalizedType as (typeof VALID_TYPES)[number])) {
    return NextResponse.json(
      { error: `Tip invalid. Acceptate: ${VALID_TYPES.join(", ")}` },
      { status: 400 },
    );
  }

  const wpApiUrl = process.env.NEXT_PUBLIC_WP_API_URL || "https://teinformez.eu/wp-json";

  try {
    const res = await fetch(`${wpApiUrl.replace(/\/$/, "")}/teinformez/v1/legal/dsr`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name: name.trim(), email: trimmedEmail, type: normalizedType, description: (typeof description === "string" ? description : "").slice(0, 500) }),
      signal: AbortSignal.timeout(10000),
    });

    const data = await res.json().catch(() => null);
    if (!res.ok) {
      return NextResponse.json(
        { error: data?.message || "Cererea nu a putut fi trimisă." },
        { status: res.status },
      );
    }

    return NextResponse.json(data, { status: 201 });
  } catch (err) {
    const msg = err instanceof Error ? err.message : "unknown";
    return NextResponse.json({ error: "Eroare de conexiune.", detail: msg }, { status: 502 });
  }
}
