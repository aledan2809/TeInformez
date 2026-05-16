import { NextRequest, NextResponse } from 'next/server'
import { createCheckoutSession, setKeyProvider } from '@projects/stripe-module'

// Route Stripe calls through the teinformez-specific key
setKeyProvider(async () => process.env.STRIPE_TEINFORMEZ_SECRET_KEY ?? '')

export async function POST(req: NextRequest) {
  try {
    const body = await req.json()
    const { priceId, userId, email } = body as {
      priceId: string
      userId: number
      email: string
    }

    if (!priceId || !email) {
      return NextResponse.json({ error: 'Missing priceId or email' }, { status: 400 })
    }

    const baseUrl = process.env.NEXT_PUBLIC_SITE_URL ?? 'https://teinformez.eu'

    const result = await createCheckoutSession({
      priceItems: [{ priceId, quantity: 1 }],
      mode: 'subscription',
      customerEmail: email,
      metadata: {
        userId: String(userId),
        email,
        priceId,
      },
      successUrl: `${baseUrl}/account/subscription?checkout=success&session_id={CHECKOUT_SESSION_ID}`,
      cancelUrl:  `${baseUrl}/subscribe?checkout=canceled`,
      allowPromotionCodes: true,
    })

    return NextResponse.json({ url: result.url })
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Internal error'
    return NextResponse.json({ error: message }, { status: 500 })
  }
}
