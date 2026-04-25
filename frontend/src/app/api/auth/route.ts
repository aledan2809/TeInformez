import { NextRequest, NextResponse } from 'next/server';

const WP_API_URL = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';

export async function POST(request: NextRequest) {
  const { searchParams } = new URL(request.url);
  const action = searchParams.get('action') || 'login';
  const endpoint = `${WP_API_URL}/teinformez/v1/auth/${action}`;

  let body: string;
  try {
    body = await request.text();
  } catch {
    return NextResponse.json({ success: false, message: 'Invalid request body' });
  }

  try {
    const wpResponse = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body,
    });

    const data = await wpResponse.json().catch(() => ({}));

    if (!wpResponse.ok) {
      return NextResponse.json({
        success: false,
        message: data.message || `Error ${wpResponse.status}`,
        data: data.data || null,
      });
    }

    return NextResponse.json({
      success: true,
      ...data,
    });
  } catch {
    return NextResponse.json({
      success: false,
      message: 'Eroare de conexiune. Verifică conexiunea la internet.',
    });
  }
}
