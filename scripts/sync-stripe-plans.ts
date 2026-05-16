/**
 * Sync TeInformez Stripe plans — run once to create products/prices in Stripe Dashboard.
 *
 * Usage:
 *   npx ts-node scripts/sync-stripe-plans.ts
 *
 * After running, copy the Price IDs printed to .env:
 *   NEXT_PUBLIC_STRIPE_PRICE_PREMIUM_MONTHLY=price_xxx
 *   NEXT_PUBLIC_STRIPE_PRICE_PREMIUM_YEARLY=price_xxx
 */

import Stripe from 'stripe'
import * as dotenv from 'dotenv'
import { resolve } from 'path'

dotenv.config({ path: resolve(__dirname, '../.env.local') })

const secretKey = process.env.STRIPE_TEINFORMEZ_SECRET_KEY
if (!secretKey) {
  console.error('❌  STRIPE_TEINFORMEZ_SECRET_KEY not set in .env.local')
  process.exit(1)
}

const stripe = new Stripe(secretKey, { apiVersion: '2024-06-20' })

async function main() {
  console.log('🔄  Syncing TeInformez plans to Stripe…\n')

  // Create or retrieve product
  let product: Stripe.Product
  const existing = await stripe.products.search({ query: 'name:"TeInformez Premium"', limit: 1 })
  if (existing.data.length) {
    product = existing.data[0]
    console.log(`✅  Product found: ${product.id} (${product.name})`)
  } else {
    product = await stripe.products.create({
      name: 'TeInformez Premium',
      description: 'Acces complet la funcționalitățile premium TeInformez',
      metadata: { project: 'teinformez' },
    })
    console.log(`✅  Product created: ${product.id}`)
  }

  // Monthly plan — 9 RON
  const monthlyPrice = await stripe.prices.create({
    product: product.id,
    unit_amount: 900,   // 9.00 RON in bani
    currency: 'ron',
    recurring: { interval: 'month' },
    nickname: 'Premium Lunar',
    metadata: { plan: 'monthly' },
  })
  console.log(`\n💳  Monthly price created: ${monthlyPrice.id}`)
  console.log(`    NEXT_PUBLIC_STRIPE_PRICE_PREMIUM_MONTHLY=${monthlyPrice.id}`)

  // Yearly plan — 99 RON
  const yearlyPrice = await stripe.prices.create({
    product: product.id,
    unit_amount: 9900,  // 99.00 RON in bani
    currency: 'ron',
    recurring: { interval: 'year' },
    nickname: 'Premium Anual',
    metadata: { plan: 'yearly' },
  })
  console.log(`\n📅  Yearly price created: ${yearlyPrice.id}`)
  console.log(`    NEXT_PUBLIC_STRIPE_PRICE_PREMIUM_YEARLY=${yearlyPrice.id}`)

  console.log('\n✅  Done! Add the two lines above to your .env.local\n')
}

main().catch((err) => {
  console.error('❌  Error:', err.message)
  process.exit(1)
})
