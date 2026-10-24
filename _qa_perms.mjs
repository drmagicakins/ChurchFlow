export default async function run(page, ui) {
  const out = { consoleErrors: [] };
  page.on('console', (m) => {
    if (m.type() === 'error') out.consoleErrors.push(m.text().slice(0, 200));
  });

  // ---- OWNER: sees the checkbox panel, can tick and save
  await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'owner@churchflow.test');
  await page.fill('input[name="password"]', 'Owner@12345');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');

  await page.goto('http://127.0.0.1:8000/settings?tab=team', { waitUntil: 'networkidle' });

  out.owner = await page.evaluate(() => {
    const card = document.querySelector('#parish-pastor-permissions');
    if (!card) return { panelFound: false };
    return {
      panelFound: true,
      pastorCount: card.querySelectorAll('details').length,
      checkboxCount: card.querySelectorAll('input[name="permissions[]"]').length,
      canEditParish: !!document.querySelector('form[action*="/parish"]'),
      canEditRole: !!document.querySelector('form[action*="/role"]'),
      summary: [...card.querySelectorAll('details > summary')].map((s) =>
        s.innerText.replace(/\s+/g, ' ').trim()
      ),
    };
  });

  const seeded = await page.evaluate(() => {
    const card = document.querySelector('#parish-pastor-permissions');
    const d = [...(card?.querySelectorAll('details') ?? [])].find((x) =>
      x.innerText.includes('QA Parish Pastor')
    );
    if (!d) return null;
    d.open = true;
    return {
      id: d.id,
      before: [...d.querySelectorAll('input[name="permissions[]"]')]
        .filter((b) => b.checked)
        .map((b) => b.value)
        .sort(),
    };
  });
  out.seededPastor = seeded;

  if (seeded) {
    const form = page.locator('#' + seeded.id + ' form');
    await form.locator('input[value="members.create"]').check();
    await form.locator('input[value="finance.record"]').check();
    await form.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
    out.afterSave = {
      flash: await page.evaluate(
        () => document.querySelector('.cf-alert--ok')?.innerText.replace(/\s+/g, ' ').trim() ?? null
      ),
      ticked: await page.evaluate(() => {
        const d = [...document.querySelectorAll('#parish-pastor-permissions details')].find((x) =>
          x.innerText.includes('QA Parish Pastor')
        );
        return [...d.querySelectorAll('input[name="permissions[]"]')]
          .filter((b) => b.checked)
          .map((b) => b.value)
          .sort();
      }),
    };
  }

  await page.screenshot({ path: '_perms_owner.png' });

  // ---- PASTOR: no parish / role / permission editors
  await page.context().clearCookies();
  await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'qa-pastor@churchflow.test');
  await page.fill('input[name="password"]', 'Pastor@12345');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');

  await page.goto('http://127.0.0.1:8000/settings?tab=team', { waitUntil: 'networkidle' });
  out.pastor = await page.evaluate(() => ({
    url: location.pathname + location.search,
    permissionPanel: !!document.querySelector('#parish-pastor-permissions'),
    parishForms: document.querySelectorAll('form[action*="/parish"]').length,
    roleForms: document.querySelectorAll('form[action*="/role"]').length,
    permissionForms: document.querySelectorAll('form[action*="/permissions"]').length,
    createAccountForm: !!document.querySelector('input[name="password_confirmation"]'),
    rows: [...document.querySelectorAll('table.cf-table tbody tr')].map((tr) =>
      tr.innerText.replace(/\s+/g, ' ').trim().slice(0, 90)
    ),
  }));

  await page.screenshot({ path: '_perms_pastor.png' });
  return out;
}
