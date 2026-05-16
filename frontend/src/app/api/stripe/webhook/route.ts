import { NextRequest, NextResponse } from 'next/server'
import { handleWebhook, setKeyProvider } from '@projects/stripe-module'
import type Stripe from 'stripe'

setKeyProvider(async () => process.env.STRIPE_TEINFORMEZ_SECRET_KEY ?? '')

const WP_API  = process.env.NEXT_PUBLIC_WP_API_URL ?? 'https://teinformez.eu/wp-json'
const SYNC_SECRET = process.env.STRIPE_TEINFORMEZ_SYNC_SECRET ?? ''

async function syncToWordPress(payload: {
  event: string
  customer_email: string
  customer_id: string
  subscription_id: string
  price_id: string
  status: string
  current_period_end: number | null
  cancel_at_period_end: boolean
}) {
  await fetch(`${WP_API}/teinformez/v1/stripe/internal-sync`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Internal-Secret': SYNC_SECRET,
    },
    body: JSON.stringify(payload),
  })
}

function extractCustomerEmail(event: Stripe.Event): string {
  const obj = event.data.object as unknown as Record<string, unknown>
  return (obj.customer_email as string | undefined)
      ?? (obj.customer_details as Record<string, string> | undefined)?.email
      ?? ''
}

function extractSubscriptionFromCheckout(session: Stripe.Checkout.Session) {
  return {
    customer_id:       (session.customer as string) ?? '',
    subscription_id:   (session.subscription as string) ?? '',
    price_id:          session.metadata?.priceId ?? '',
    status:            'active',
    current_period_end: null,
    cancel_at_period_end: false,
  }
}

function extractSubscriptionFields(sub: Stripe.Subscription) {
  return {
    customer_id:        (sub.customer as string) ?? '',
    subscription_id:    sub.id,
    price_id:           sub.items.data[0]?.price?.id ?? '',
    status:             sub.status,
    current_period_end: sub.current_period_end ?? null,
    cancel_at_period_end: sub.cancel_at_period_end ?? false,
  }
}

export async function POST(req: NextRequest) {
  const rawBody  = await req.text()
  const signature = req.headers.get('stripe-signature') ?? ''
  const webhookSecret = process.env.STRIPE_TEINFORMEZ_WEBHOOK_SECRET ?? ''

  try {
    await handleWebhook(
      { rawBody, signature, webhookSecret },
      {
        'checkout.session.completed': async (event) => {
          const session = event.data.object as Stripe.Checkout.Session
          const email   = extractCustomerEmail(event)
          if (!email) return
          await syncToWordPress({
            event:         event.type,
            customer_email: email,
            ...extractSubscriptionFromCheckout(session),
          })
        },

        'customer.subscription.updated': async (event) => {
          const sub   = event.data.object as Stripe.Subscription
          const email = await resolveEmailFromCustomer(sub.customer as string)
          if (!email) return
          await syncToWordPress({
            event:         event.type,
            customer_email: email,
            ...extractSubscriptionFields(sub),
          })
        },

        'customer.subscription.deleted': async (event) => {
          const sub   = event.data.object as Stripe.Subscription
          const email = await resolveEmailFromCustomer(sub.customer as string)
          if (!email) return
          await syncToWordPress({
            event:         event.type,
            customer_email: email,
            ...extractSubscriptionFields(sub),
          })
        },
      }
    )

    return NextResponse.json({ received: true })
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Webhook error'
    return NextResponse.json({ error: message }, { status: 400 })
  }
}

// Stripe customer objects carry email — resolve via Stripe API
async function resolveEmailFromCustomer(customerId: string): Promise<string> {
  try {
    const { getStripe } = await import('@projects/stripe-module')
    const stripe = await getStripe()
    const customer = await stripe.customers.retrieve(customerId)
    if (customer.deleted) return ''
    return (customer as Stripe.Customer).email ?? ''
  } catch {
    return ''
  }
}
