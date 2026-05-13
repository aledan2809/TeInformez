import { NextRequest, NextResponse } from 'next/server';

const SPIKE_WINDOW_MS = 10 * 60 * 1000; // 10 minutes
const SPIKE_THRESHOLD = 5;

// In-process sliding window: timestamps of recent errors
const errorTimestamps: number[] = [];
let lastAlertSentAt = 0;
const ALERT_COOLDOWN_MS = 30 * 60 * 1000; // alert at most once per 30 min

function pruneWindow(now: number) {
  const cutoff = now - SPIKE_WINDOW_MS;
  while (errorTimestamps.length > 0 && errorTimestamps[0] < cutoff) {
    errorTimestamps.shift();
  }
}

async function sendSpikeAlert(count: number, sample: unknown) {
  const apiKey = process.env.BREVO_API_KEY;
  const adminEmail = process.env.ADMIN_ALERT_EMAIL;
  if (!apiKey || !adminEmail) return;

  const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://teinformez.eu';

  await fetch('https://api.brevo.com/v3/smtp/email', {
    method: 'POST',
    headers: {
      'api-key': apiKey,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      sender: { name: 'TeInformez Monitor', email: 'noreply@teinformez.eu' },
      to: [{ email: adminEmail }],
      subject: `[TeInformez] Spike erori: ${count} erori în ultimele 10 minute`,
      htmlContent: `
        <h2>Alertă spike erori — ${siteUrl}</h2>
        <p><strong>${count} erori</strong> raportate în ultimele 10 minute (prag: ${SPIKE_THRESHOLD}).</p>
        <h3>Ultima eroare:</h3>
        <pre style="background:#f4f4f4;padding:12px;border-radius:4px;font-size:12px;">${JSON.stringify(sample, null, 2)}</pre>
        <p><a href="${siteUrl}/wp-admin/admin.php?page=teinformez">Deschide admin panel</a></p>
      `,
    }),
  }).catch(() => {
    // swallow email send errors — don't cascade
  });
}

export async function POST(request: NextRequest) {
  let body: unknown;
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ ok: false }, { status: 400 });
  }

  const now = Date.now();
  pruneWindow(now);
  errorTimestamps.push(now);
  const windowCount = errorTimestamps.length;

  if (windowCount >= SPIKE_THRESHOLD && now - lastAlertSentAt > ALERT_COOLDOWN_MS) {
    lastAlertSentAt = now;
    // fire-and-forget; do not await to keep response fast
    sendSpikeAlert(windowCount, body).catch(() => {});
  }

  return NextResponse.json({ ok: true, windowCount });
}
