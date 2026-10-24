export default async function run(page, ui) {
  const out = { consoleErrors: [], csp: [] };
  page.on('console', (m) => {
    if (m.type() === 'error') {
      const t = m.text();
      out.consoleErrors.push(t.slice(0, 200));
      if (/Content Security Policy/i.test(t)) out.csp.push(t.slice(0, 200));
    }
  });

  await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'owner@churchflow.test');
  await page.fill('input[name="password"]', 'Owner@12345');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');

  await page.goto('http://127.0.0.1:8000/settings?tab=team', { waitUntil: 'networkidle' });

  out.ownerRows = await page.evaluate(() =>
    [...document.querySelectorAll('table.cf-table tbody tr')].map((tr) => ({
      text: tr.innerText.replace(/\s+/g, ' ').trim().slice(0, 110),
      roleSelect: tr.querySelectorAll('select[name="role_id"]').length,
      parishSelect: tr.querySelectorAll('select[name="organizational_unit_id"]').length,
    }))
  );

  const dId = await page.evaluate(() => {
    const d = [...document.querySelectorAll('#parish-pastor-permissions details')].find((x) =>
      x.innerText.includes('QA Parish Pastor')
    );
    return d ? d.id : null;
  });
  out.detailsId = dId;

  if (dId) {
    await page.evaluate((id) => {
      const form = document.querySelector('#' + id + ' form');
      form.querySelectorAll('input[name="permissions[]"]').forEach((b) => {
        b.checked = b.value === 'members.create' || b.value === 'finance.record';
      });
    }, dId);

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle' }),
      page.evaluate((id) => document.querySelector('#' + id + ' form').submit(), dId),
    ]);

    out.saveFlashCount = await page.evaluate(
      () => document.querySelectorAll('.cf-alert--ok').length
    );
    out.saveFlashText = await page.evaluate(
      () => document.querySelector('.cf-alert--ok')?.innerText.replace(/\s+/g, ' ').trim()
    );
  }

  await page.goto('http://127.0.0.1:8000/settings?tab=team', { waitUntil: 'networkidle' });
  out.afterReload = await page.evaluate(() => {
    const d = [...document.querySelectorAll('#parish-pastor-permissions details')].find((x) =>
      x.innerText.includes('QA Parish Pastor')
    );
    const row = [...document.querySelectorAll('table.cf-table tbody tr')].find((tr) =>
      tr.innerText.includes('QA Parish Pastor')
    );
    return {
      ticked: d
        ? [...d.querySelectorAll('input[name="permissions[]"]')]
            .filter((b) => b.checked)
            .map((b) => b.value)
            .sort()
        : null,
      rowText: row ? row.innerText.replace(/\s+/g, ' ').trim() : null,
      rowHasRoleSelect: row ? row.querySelectorAll('select[name="role_id"]').length : null,
    };
  });

  await page.screenshot({ path: '_final_owner.png' });
  return out;
}
