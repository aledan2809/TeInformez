import { NextRequest, NextResponse } from 'next/server';

export const runtime = 'nodejs';

type TrackEventBody = {
  event_name?: string;
  client_id?: string;
  user_id?: string;
  params?: Record<string, unknown>;
  debug_mode?: boolean;
};

const measurementId = process.env.GA_MEASUREMENT_ID || process.env.NEXT_PUBLIC_GA_MEASUREMENT_ID;
const apiSecret = process.env.GA_API_SECRET;
const VALID_EVENT_NAME = /^[a-zA-Z][a-zA-Z0-9_]{0,39}$/;
const VALID_MEASUREMENT_ID = /^G-[A-Z0-9]{6,}$/;

function buildEndpoint(debugMode: boolean): string {
  const base = debugMode
    ? 'https://www.google-analytics.com/debug/mp/collect'
    : 'https://www.google-analytics.com/mp/collect';

  const search = new URLSearchParams({
    measurement_id: measurementId || '',
    api_secret: apiSecret || '',
  });

  return `${base}?${search.toString()}`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function normalizeOptionalString(value: unknown): string | undefined {
  if (typeof value !== 'string') return undefined;
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : undefined;
}

export async function POST(request: NextRequest) {
  if (!measurementId || !apiSecret) {
    return NextResponse.json(
      { error: 'Google Analytics Measurement Protocol is not configured.' },
      { status: 500 }
    );
  }

  if (!VALID_MEASUREMENT_ID.test(measurementId)) {
    return NextResponse.json(
      { error: 'Google Analytics measurement ID format is invalid.' },
      { status: 500 }
    );
  }

  let rawBody: unknown;

  try {
    rawBody = await request.json();
  } catch {
    return NextResponse.json({ error: 'Invalid JSON body.' }, { status: 400 });
  }

  if (!isRecord(rawBody)) {
    return NextResponse.json({ error: 'Request body must be a JSON object.' }, { status: 400 });
  }

  const body = rawBody as TrackEventBody;
  const eventName = normalizeOptionalString(body.event_name) ?? 'page_view';
  const clientId = normalizeOptionalString(body.client_id);
  const userId = normalizeOptionalString(body.user_id);

  if (!VALID_EVENT_NAME.test(eventName)) {
    return NextResponse.json(
      { error: 'event_name must start with a letter and contain only letters, numbers, or underscores (max 40 chars).' },
      { status: 400 }
    );
  }

  if (!clientId && !userId) {
    return NextResponse.json(
      { error: 'Either client_id or user_id is required.' },
      { status: 400 }
    );
  }

  if (clientId && clientId.length > 200) {
    return NextResponse.json({ error: 'client_id is too long.' }, { status: 400 });
  }

  if (userId && userId.length > 256) {
    return NextResponse.json({ error: 'user_id is too long.' }, { status: 400 });
  }

  if (body.params !== undefined && !isRecord(body.params)) {
    return NextResponse.json({ error: 'params must be an object.' }, { status: 400 });
  }

  const debugMode = Boolean(body.debug_mode);

  const payload = {
    client_id: clientId,
    user_id: userId,
    events: [
      {
        name: eventName,
        params: {
          engagement_time_msec: 1,
          ...(body.params || {}),
        },
      },
    ],
  };

  let gaResponse: Response;
  try {
    gaResponse = await fetch(buildEndpoint(debugMode), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
      cache: 'no-store',
    });
  } catch {
    return NextResponse.json(
      { error: 'Failed to reach Google Analytics endpoint.' },
      { status: 502 }
    );
  }

  if (!gaResponse.ok) {
    const details = await gaResponse.text();
    return NextResponse.json(
      { error: 'Google Analytics request failed.', details },
      { status: gaResponse.status }
    );
  }

  if (debugMode) {
    const debugData = await gaResponse.json().catch(() => null);
    return NextResponse.json({ ok: true, debug: debugData });
  }

  return NextResponse.json({ ok: true, event_name: eventName });
}
