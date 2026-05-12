import { ImageResponse } from 'next/og';
import type { NextRequest } from 'next/server';

export async function GET(request: NextRequest) {
  const { searchParams } = new URL(request.url);
  const title = searchParams.get('title') || 'TeInformez.eu';
  const source = searchParams.get('source') || '';
  const category = searchParams.get('category') || '';

  const fontSize = title.length > 80 ? 36 : title.length > 50 ? 44 : 52;
  const displayTitle = title.length > 120 ? title.slice(0, 120) + '…' : title;

  return new ImageResponse(
    (
      <div
        style={{
          width: '1200px',
          height: '630px',
          display: 'flex',
          flexDirection: 'column',
          background: 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)',
          padding: '60px',
          fontFamily: 'sans-serif',
        }}
      >
        {/* Top bar: brand + category badge */}
        <div style={{ display: 'flex', alignItems: 'center', marginBottom: '48px' }}>
          <div
            style={{
              fontSize: '28px',
              fontWeight: '700',
              color: '#3b82f6',
              letterSpacing: '-0.5px',
            }}
          >
            TeInformez
            <span style={{ color: '#64748b' }}>.eu</span>
          </div>
          {category && (
            <div
              style={{
                marginLeft: 'auto',
                background: '#1d4ed8',
                color: '#fff',
                fontSize: '16px',
                fontWeight: '600',
                padding: '6px 16px',
                borderRadius: '20px',
              }}
            >
              {category}
            </div>
          )}
        </div>

        {/* Article title */}
        <div style={{ flex: 1, display: 'flex', alignItems: 'center' }}>
          <div
            style={{
              fontSize: `${fontSize}px`,
              fontWeight: '800',
              color: '#f8fafc',
              lineHeight: '1.25',
              letterSpacing: '-1px',
            }}
          >
            {displayTitle}
          </div>
        </div>

        {/* Bottom bar: source + domain */}
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            borderTop: '1px solid #334155',
            paddingTop: '24px',
            marginTop: '24px',
          }}
        >
          <div style={{ fontSize: '18px', color: '#94a3b8' }}>
            {source ? `Sursa: ${source}` : 'Stiri rezumate de AI'}
          </div>
          <div style={{ marginLeft: 'auto', fontSize: '18px', color: '#475569' }}>
            teinformez.eu
          </div>
        </div>
      </div>
    ),
    {
      width: 1200,
      height: 630,
    }
  );
}
