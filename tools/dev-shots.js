// Screenshots of every page of the new app (and the legacy tabs) on a demo
// database built by tools/dev-seed.php. Local and cloud sessions only.
//
//   node tools/dev-shots.js <out dir> <teasers,posts,menu,social job ids> [--legacy] [--only=name,name]
//
// Needs the server from the dev-seed output on 127.0.0.1:8301 and Playwright.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const out = process.argv[2];
const [j1, j2, j3, j4] = (process.argv[3] || '').split(',');
const legacy = process.argv.includes('--legacy');
const onlyArg = process.argv.find((a) => a.startsWith('--only='));
const only = onlyArg ? onlyArg.slice(7).split(',') : null;
if (!out || !j4) {
  console.error('usage: node tools/dev-shots.js <out dir> <j1,j2,j3,j4> [--legacy] [--only=a,b]');
  process.exit(2);
}
const base = 'http://127.0.0.1:8301/slash301pm';
const pass = 'correct horse battery';
const widths = [1280, 390];

// [file name, user, path]
const pages = [
  ['login', null, '/login'],
  ['today-am', 'am_amy', '/today'],
  ['today-cd', 'cd_isabelle', '/today'],
  ['jobs-grid', 'am_amy', '/jobs'],
  ['jobs-board', 'am_amy', '/jobs/board'],
  ['briefs', 'am_amy', '/briefs'],
  ['brief-editor', 'am_amy', `/jobs/${j1}/brief`],
  ['brief-versions', 'am_amy', `/jobs/${j1}/brief/versions`],
  ['job-assets', 'am_amy', `/jobs/${j1}/assets`],
  ['campaigns', 'am_amy', '/campaigns'],
  ['work', 'copy_alex', `/jobs/${j1}/work`],
  ['review', 'cd_isabelle', `/jobs/${j2}/review`],
  ['review-ready', 'am_amy', `/jobs/${j3}/review`],
  ['reviews-list', 'cd_isabelle', '/today/reviews'],
  ['social', 'social_sol', '/social'],
  ['social-job', 'social_sol', `/social/jobs/${j4}`],
  ['social-list', 'social_sol', '/social/list'],
  ['people', 'coo_priya', '/people'],
  ['admin-users', 'coo_priya', '/admin/users'],
  ['admin-system', 'coo_priya', '/admin/system'],
  ['admin-overrides', 'coo_priya', '/admin/overrides'],
  ['account', 'am_amy', '/account/password'],
  ['error-404', 'am_amy', '/nope'],
  ['clients', 'am_amy', '/brands/brand_merc/clients'],
  ['portal', 'client_sophie', '/portal'],
  ['portal-job', 'client_sophie', `/portal/jobs/${j3}`],
];

async function login(ctx, user) {
  const page = await ctx.newPage();
  await page.goto(base + '/login');
  await page.fill('#username', user);
  await page.fill('#password', pass);
  await Promise.all([page.waitForNavigation(), page.click('form:has(#username) button[type=submit]')]);
  await page.close();
}

(async () => {
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch();
  for (const width of widths) {
    const sessions = {};
    for (const [name, user, p] of pages) {
      if (only && !only.includes(name)) continue;
      const key = user || '-';
      if (!sessions[key]) {
        sessions[key] = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : 800 }, deviceScaleFactor: 1 });
        if (user) await login(sessions[key], user);
      }
      const page = await sessions[key].newPage();
      const res = await page.goto(base + p, { waitUntil: 'networkidle' }).catch((e) => ({ status: () => 'ERR ' + e.message }));
      await page.waitForTimeout(250);
      await page.screenshot({ path: path.join(out, `${name}-${width}.png`), fullPage: true });
      console.log(`${name}-${width}: ${res ? res.status() : '?'}`);
      await page.close();
    }
    if (legacy && !only) {
      const ctx = await browser.newContext({ viewport: { width, height: 800 }, deviceScaleFactor: 1 });
      const page = await ctx.newPage();
      await page.goto(base + '/legacy/', { waitUntil: 'networkidle' });
      await page.screenshot({ path: path.join(out, `legacy-login-${width}.png`) });
      const u = page.locator('input[type=text], input[name=username]').first();
      if (await u.count()) {
        await u.fill('am_amy');
        await page.locator('input[type=password]').first().fill(pass);
        await page.locator('button[type=submit], form button').first().click();
        await page.waitForTimeout(1500);
      }
      await page.screenshot({ path: path.join(out, `legacy-dashboard-${width}.png`), fullPage: true });
      for (const tab of ['Work', 'Capacity', 'Reviews']) {
        const t = page.locator('.tab', { hasText: tab }).first();
        if (await t.count()) {
          await t.click();
          await page.waitForTimeout(700);
          await page.screenshot({ path: path.join(out, `legacy-${tab.toLowerCase()}-${width}.png`), fullPage: true });
          if (tab === 'Work') {
            const toggles = page.locator('.view-toggle button');
            for (let i = 0; i < (await toggles.count()); i++) {
              await toggles.nth(i).click();
              await page.waitForTimeout(500);
              await page.screenshot({ path: path.join(out, `legacy-work-view${i}-${width}.png`), fullPage: true });
            }
          }
        }
      }
      await ctx.close();
    }
    for (const c of Object.values(sessions)) await c.close();
  }
  await browser.close();
})();
