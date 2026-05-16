import { NextRequest, NextResponse } from 'next/server'
import { setKeyProvider } from '@projects/stripe-module'

setKeyProvider(async () => process.env.STRIPE_TEINFORMEZ_SECRET_KEY ?? '')

export async function POST(req: NextRequest) {
  try {
    const { email } = await req.json() as { email?: string }
    if (!email) {
      return NextResponse.json({ error: 'Missing email' }, { status: 400 })
    }

    const { getStripe } = await import('@projects/stripe-module')
    const stripe = await getStripe()

    // Find customer by email
    const customers = await stripe.customers.list({ email, limit: 1 })
    if (!customers.data.length) {
      return NextResponse.json({ error: 'Niciun abonament găsit pentru acest cont' }, { status: 404 })
    }

    const baseUrl = process.env.NEXT_PUBLIC_SITE_URL ?? 'https://teinformez.eu'
    const session = await stripe.billingPortal.sessions.create({
      customer: customers.data[0].id,
      return_url: `${baseUrl}/account/subscription`,
    })

    return NextResponse.json({ url: session.url })
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Internal error'
    return NextResponse.json({ error: message }, { status: 500 })
  }
}
