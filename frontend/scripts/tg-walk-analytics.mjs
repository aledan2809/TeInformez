// TG-equivalent verification walk for AN-01 analytics simple/advanced split.
//
// Why this exists: Tester-Gateway criticalFlows can assert on visible text,
// URLs, and clicks, but cannot easily extract SVG <title> attributes, parse
// percentage strings into floats, or run regex on rendered HTML for the
// `&mdash;` literal. This script complements `Tester-Gateway/apps/teinformez.json`
// with bug-specific assertions for AN-01 Phase 1 fixes.
//
// Usage (from TeInformez/frontend/):
//   npx playwright install chromium 2>/dev/null
//   node scripts/tg-walk-analytics.mjs
//
// Env (auto-loaded from Master/credentials/teinformez-test-users.env if missing):
//   WP_USER  — WP admin email or username
//   WP_PASS  — WP admin password
//
// Exit codes:
//   0 — all assertions pass
//   1 — at least one assertion failed (details in console + screenshots)

import { chromium } from "@playwright/test";
import { mkdirSync, readFileSync } from "fs";
import { dirname, resolve } from "path";
import { fileURLToPath } from "url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const OUT_DIR = resolve(__dirname, "../../reports/tg-walk-analytics");
mkdirSync(OUT_DIR, { recursive: true });

let WP_USER = process.env.WP_USER || "";
let WP_PASS = process.env.WP_PASS || "";
const BASE = process.env.TEINFORMEZ_URL || "https://teinformez.eu";

if (!WP_USER || !WP_PASS) {
  try {
    const credPath = "/Users/danciulescu/Projects/Master/credentials/teinformez-test-users.env";
    const txt = readFileSync(credPath, "utf8");
    const env = {};
    txt.split("\n").forEach((l) => {
      const m = l.match(/^([A-Z_]+)=(.*)$/);
      if (m) env[m[1]] = m[2];
    });
    WP_USER = WP_USER || env.TEINFORMEZ_ADMIN_EMAIL;
    WP_PASS = WP_PASS || env.TEINFORMEZ_ADMIN_PASSWORD;
    console.log(`Loaded creds from credentials file: user=${WP_USER}`);
  } catch (e) {
    console.error("No env vars + cannot read credentials file:", e.message);
    process.exit(1);
  }
}
if (!WP_USER || !WP_PASS) {
  console.error("Missing WP_USER or WP_PASS");
  process.exit(1);
}

const failures = [];
const todayISO = new Date().toISOString().slice(0, 10);

function assert(cond, label, detail = "") {
  if (cond) {
    console.log(`  ✅ ${label}`);
  } else {
    console.log(`  ❌ ${label}${detail ? " — " + detail : ""}`);
    failures.push({ label, detail });
  }
}

async function shoot(page, name) {
  const path = `${OUT_DIR}/${name}.png`;
  await page.screenshot({ path, fullPage: true });
  console.log(`  📸 ${name}.png`);
}

async function loginAdmin(page) {
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: "domcontentloaded" });
  await page.fill("#user_login", WP_USER);
  await page.fill("#user_pass", WP_PASS);
  await Promise.all([page.waitForLoadState("domcontentloaded"), page.click("#wp-submit")]);
  if (!page.url().includes("wp-admin")) {
    await shoot(page, "FAIL-login");
    throw new Error(`Login failed — landed at ${page.url()}`);
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();

  console.log("→ Login wp-admin");
  await loginAdmin(page);
  console.log(`  ✅ logged in as ${WP_USER}`);

  // ============================================================
  // SIMPLE VIEW
  // ============================================================
  console.log("\n→ Simple view");
  await page.goto(`${BASE}/wp-admin/admin.php?page=teinformez-analytics`, { waitUntil: "networkidle", timeout: 30000 });
  await page.waitForTimeout(800);
  await shoot(page, "01-simple-desktop");

  const cardCount = await page.locator(".ti-card").count();
  assert(cardCount === 5, `5 headline cards rendered`, `got ${cardCount}`);

  const chartCount = await page.locator(".ti-chart svg").count();
  assert(chartCount === 3, `3 SVG trend charts rendered`, `got ${chartCount}`);

  const advancedLink = await page.locator('a:has-text("Show advanced view")').count();
  assert(advancedLink >= 1, `'Show advanced view' link present`, `count=${advancedLink}`);

  // ----- Bug 1: today inclusive in trend chart -----
  // Each <circle> in the chart has a <title> with format "YYYY-MM-DD: N".
  // After the fix, today's date MUST appear as the last point in the FIRST chart.
  const titleTexts = await page.locator(".ti-chart").first().locator("svg circle title").allTextContents();
  console.log(`  chart 1 has ${titleTexts.length} data points`);
  const datesInChart = titleTexts.map((t) => t.split(":")[0].trim());
  const lastDateInChart = datesInChart[datesInChart.length - 1] || "";
  console.log(`  chart 1 last date = ${lastDateInChart}, today = ${todayISO}`);
  assert(
    titleTexts.length === 30,
    `chart 1 has 30 data points (today inclusive)`,
    `got ${titleTexts.length}`
  );
  assert(
    datesInChart.includes(todayISO),
    `Bug 1 FIX: today (${todayISO}) IS in chart 1`,
    `last date in chart was ${lastDateInChart}`
  );

  // ----- Bug 2: Card 5 percentage 0..100 -----
  // Card 5 displays "X views · Y% din audiență" (or similar).
  // After fix, Y must be in [0, 100] always.
  const card5Text = await page.locator(".ti-card").nth(4).textContent();
  console.log(`  card 5 text: ${card5Text?.trim().replace(/\s+/g, " ")}`);
  const pctMatch = (card5Text || "").match(/([\d.,]+)\s*%/);
  if (pctMatch) {
    const pct = parseFloat(pctMatch[1].replace(",", "."));
    assert(
      pct >= 0 && pct <= 100,
      `Bug 2 FIX: top category percentage in [0,100]`,
      `got ${pct}%`
    );
  } else {
    assert(false, `Bug 2 FIX: top category percentage parsable`, `no percent found in text`);
  }

  // ----- Bug 3: real em-dash, NOT &mdash; literal -----
  // Sidebar shows "Analytics — Advanced" using real em-dash. If the i18n
  // string still has &mdash;, WordPress emits the literal "&amp;mdash;" or
  // "&mdash;" depending on context. Check the rendered HTML doesn't contain
  // the entity literal text.
  const sidebarHtml = await page.locator("#adminmenu").innerHTML();
  assert(
    !sidebarHtml.includes("&amp;mdash;") && !/Analytics &mdash; Advanced/.test(sidebarHtml),
    `Bug 3 FIX: sidebar has no '&mdash;' literal`,
    `sidebarHtml contains literal entity`
  );
  const sidebarText = await page.locator("#adminmenu").innerText();
  assert(
    sidebarText.includes("Analytics — Advanced") || sidebarText.includes("Analytics —"),
    `Bug 3 FIX: sidebar shows real em-dash 'Analytics —'`,
    `sidebarText snippet around 'Analytics': ${(sidebarText.match(/Analytics[^\n]*/g) || []).join(" | ")}`
  );

  // ============================================================
  // ADVANCED VIEW (regression: must NOT 403)
  // ============================================================
  console.log("\n→ Advanced view via toggle link");
  await page.locator('a:has-text("Show advanced view")').first().click();
  await page.waitForLoadState("networkidle", { timeout: 30000 });
  await page.waitForTimeout(800);
  await shoot(page, "02-advanced-desktop");

  const bodyText = await page.locator("body").innerText();
  assert(
    !bodyText.includes("Sorry, you are not allowed"),
    `Regression 27e64d4: advanced page is NOT 403 'not allowed'`,
    `body contains the 403 text`
  );
  const backLink = await page.locator('a:has-text("Back to simple")').count();
  assert(backLink >= 1, `'Back to simple view' link on advanced page`, `count=${backLink}`);
  const advancedTitle = await page.locator("h1").first().textContent();
  assert(
    /Visitor Analytics.*Advanced/.test(advancedTitle || ""),
    `Advanced page title contains 'Advanced'`,
    `got '${advancedTitle?.trim()}'`
  );
  const detailCards = await page.locator(".wrap a[href*='detail=']").count();
  assert(
    detailCards >= 10,
    `Advanced page has many clickable metric cards (>=10)`,
    `got ${detailCards}`
  );

  // ============================================================
  // BACK NAVIGATION
  // ============================================================
  console.log("\n→ Back to simple via link");
  await page.locator('a:has-text("Back to simple")').first().click();
  await page.waitForLoadState("networkidle", { timeout: 30000 });
  await page.waitForTimeout(500);
  const backUrl = page.url();
  assert(
    backUrl.includes("page=teinformez-analytics") && !backUrl.includes("advanced"),
    `Back link returns to simple view`,
    `URL was ${backUrl}`
  );

  // ============================================================
  // DRILL-DOWN from simple card
  // ============================================================
  console.log("\n→ Drill-down: click first headline card");
  const firstCardHref = await page.locator(".ti-card").first().getAttribute("href");
  await page.locator(".ti-card").first().click();
  await page.waitForLoadState("networkidle", { timeout: 30000 });
  await page.waitForTimeout(600);
  await shoot(page, "03-drilldown-from-card");
  const drilldownUrl = page.url();
  assert(
    drilldownUrl.includes("teinformez-analytics-advanced") && drilldownUrl.includes("detail="),
    `Drill-down lands on advanced page with detail param`,
    `URL was ${drilldownUrl}`
  );

  // ============================================================
  // MOBILE viewport
  // ============================================================
  console.log("\n→ Mobile viewport (375px)");
  await ctx.close();
  const mctx = await browser.newContext({ viewport: { width: 375, height: 812 } });
  const mpage = await mctx.newPage();
  await loginAdmin(mpage);
  await mpage.goto(`${BASE}/wp-admin/admin.php?page=teinformez-analytics`, { waitUntil: "networkidle" });
  await mpage.waitForTimeout(800);
  await shoot(mpage, "04-simple-mobile-375");
  const mobileCards = await mpage.locator(".ti-card").count();
  assert(mobileCards === 5, `Mobile: 5 cards still rendered`, `got ${mobileCards}`);

  await browser.close();

  // ============================================================
  // RESULT
  // ============================================================
  console.log(`\n${"=".repeat(60)}`);
  if (failures.length === 0) {
    console.log(`✅ ALL ASSERTIONS PASSED. Screenshots in ${OUT_DIR}`);
    process.exit(0);
  } else {
    console.log(`❌ ${failures.length} ASSERTION(S) FAILED:`);
    failures.forEach((f, i) => console.log(`  ${i + 1}. ${f.label}${f.detail ? " — " + f.detail : ""}`));
    console.log(`\nScreenshots in ${OUT_DIR}`);
    process.exit(1);
  }
})().catch((e) => {
  console.error("\nWALK ERROR:", e);
  process.exit(2);
});
