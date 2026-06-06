import { ImageResponse } from 'next/og';
import type { NextRequest } from 'next/server';

const WP_API_URL = process.env.NEXT_PUBLIC_WP_API_URL || 'http://localhost/wp-json';

interface ShareNewsItem {
  id: number;
  title: string;
  summary: string;
  source: string;
  categories: string[];
  simple_explanation?: string | null;
  why_it_matters?: string | null;
}

async function getNewsItem(id: string): Promise<ShareNewsItem | null> {
  try {
    const res = await fetch(`${WP_API_URL}/teinformez/v1/news/${id}`, {
      next: { revalidate: 3600 },
    });
    if (!res.ok) return null;
    const json = await res.json();
    return json.data?.news || null;
  } catch {
    return null;
  }
}

function truncate(text: string, max: number): string {
  const t = (text || '').trim();
  return t.length > max ? t.slice(0, max).replace(/\s+\S*$/, '') + '…' : t;
}

const WIDTH = 1080;
const HEIGHT = 1350;

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params;
  const news = await getNewsItem(id);

  const title = truncate(news?.title || 'TeInformez.eu', 120);
  // ELI12 hero: prefer simple_explanation, fall back to summary
  const eli = truncate(news?.simple_explanation || news?.summary || '', 300);
  const why = truncate(news?.why_it_matters || '', 200);
  const category = news?.categories?.[0] || '';

  return new ImageResponse(
    (
      <div
        style={{
          width: `${WIDTH}px`,
          height: `${HEIGHT}px`,
          display: 'flex',
          flexDirection: 'column',
          background: 'linear-gradient(160deg, #ffffff 0%, #ecfdf5 100%)',
          padding: '72px',
          fontFamily: 'sans-serif',
        }}
      >
        {/* Top bar: brand + category */}
        <div style={{ display: 'flex', alignItems: 'center', marginBottom: '56px' }}>
          <div style={{ display: 'flex', fontSize: '40px', fontWeight: '800', letterSpacing: '-1px' }}>
            <span style={{ color: '#059669' }}>TeInformez</span>
            <span style={{ color: '#94a3b8' }}>.eu</span>
          </div>
          {category ? (
            <div
              style={{
                marginLeft: 'auto',
                display: 'flex',
                background: '#059669',
                color: '#ffffff',
                fontSize: '24px',
                fontWeight: '600',
                padding: '8px 22px',
                borderRadius: '24px',
              }}
            >
              {category}
            </div>
          ) : null}
        </div>

        {/* Title */}
        <div style={{ display: 'flex', marginBottom: '40px' }}>
          <div
            style={{
              fontSize: title.length > 80 ? '46px' : '54px',
              fontWeight: '800',
              color: '#0f172a',
              lineHeight: '1.2',
              letterSpacing: '-1px',
            }}
          >
            {title}
          </div>
        </div>

        {/* "Pe scurt" hero block */}
        <div
          style={{
            display: 'flex',
            flexDirection: 'column',
            flex: 1,
            background: '#ffffff',
            border: '2px solid #6ee7b7',
            borderRadius: '28px',
            padding: '44px',
          }}
        >
          <div
            style={{
              display: 'flex',
              fontSize: '26px',
              fontWeight: '700',
              color: '#047857',
              textTransform: 'uppercase',
              letterSpacing: '2px',
              marginBottom: '24px',
            }}
          >
            Pe scurt
          </div>
          <div
            style={{
              display: 'flex',
              fontSize: eli.length > 200 ? '38px' : '44px',
              fontWeight: '500',
              color: '#1f2937',
              lineHeight: '1.4',
            }}
          >
            {eli || title}
          </div>

          {why ? (
            <div
              style={{
                display: 'flex',
                flexDirection: 'column',
                marginTop: 'auto',
                paddingTop: '32px',
                borderTop: '1px solid #d1fae5',
              }}
            >
              <div
                style={{
                  display: 'flex',
                  fontSize: '22px',
                  fontWeight: '700',
                  color: '#b45309',
                  textTransform: 'uppercase',
                  letterSpacing: '1.5px',
                  marginBottom: '12px',
                }}
              >
                De ce contează
              </div>
              <div style={{ display: 'flex', fontSize: '28px', color: '#374151', lineHeight: '1.4' }}>
                {why}
              </div>
            </div>
          ) : null}
        </div>

        {/* Footer */}
        <div style={{ display: 'flex', alignItems: 'center', marginTop: '48px' }}>
          <div style={{ display: 'flex', fontSize: '26px', fontWeight: '600', color: '#059669' }}>
            Citește pe teinformez.eu
          </div>
          {news?.source ? (
            <div style={{ marginLeft: 'auto', display: 'flex', fontSize: '22px', color: '#94a3b8' }}>
              Sursă: {news.source}
            </div>
          ) : null}
        </div>
      </div>
    ),
    {
      width: WIDTH,
      height: HEIGHT,
      headers: {
        'cache-control': 'public, max-age=3600, s-maxage=86400, stale-while-revalidate=86400',
      },
    }
  );
}
