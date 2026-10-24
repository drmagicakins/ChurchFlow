export default async function run(page, ui) {
    const out = {};
    await page.goto("http://127.0.0.1:8000/login", {
        waitUntil: "networkidle",
    });
    await page.fill('input[name="email"]', "owner@churchflow.test");
    await page.fill('input[name="password"]', "Owner@12345");
    await page.click('button[type="submit"]');
    await page.waitForLoadState("networkidle");
    out.afterLogin = page.url();

    await page.goto("http://127.0.0.1:8000/settings?tab=team", {
        waitUntil: "networkidle",
    });

    out.rows = await page.evaluate(() =>
        [...document.querySelectorAll("table.cf-table tbody tr")].map((tr) =>
            [...tr.querySelectorAll("td")].map((td) =>
                td.innerText.replace(/\s+/g, " ").trim(),
            ),
        ),
    );

    out.permPanelCounts = await page.evaluate(() =>
        [
            ...document.querySelectorAll("#parish-pastor-permissions details"),
        ].map((d) => ({
            summary: d
                .querySelector("summary")
                ?.innerText.replace(/\s+/g, " ")
                .trim(),
            checked: [
                ...d.querySelectorAll('input[name="permissions[]"]'),
            ].filter((b) => b.checked).length,
            totalBoxes: d.querySelectorAll('input[name="permissions[]"]')
                .length,
        })),
    );

    return out;
}
