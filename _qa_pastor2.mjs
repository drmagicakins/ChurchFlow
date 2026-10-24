export default async function run(page, ui) {
  const out = {};
  await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'qa-pastor@churchflow.test');
  await page.fill('input[name="password"]', 'Pastor@12345');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');

  await page.goto('http://127.0.0.1:8000/settings?tab=team', { waitUntil: 'networkidle' });

  out.rows = await page.evaluate(() =>
    [...document.querySelectorAll('table.cf-table tbody tr')]
      .map((tr) => tr.innerText.replace(/\s+/g, ' ').trim())
  );

  out.navLinks = await page.evaluate(() =>
    [...document.querySelectorAll('.cf-navlink')].map((a) => a.innerText.trim().split('\n')[0])
  );

  await page.screenshot({ path: '_perms_pastor2.png', fullPage: true });
  return out;
}
