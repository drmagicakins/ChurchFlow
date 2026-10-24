export default async function run(page, ui) {
    const out = {};
    await page.goto("http://127.0.0.1:8000/login", {
        waitUntil: "networkidle",
    });
    await page.fill('input[name="email"]', "owner@churchflow.test");
    await page.fill('input[name="password"]', "Owner@12345");
    await page.click('button[type="submit"]');
    await page.waitForLoadState("networkidle");

    await page.goto("http://127.0.0.1:8000/settings?tab=team", {
        waitUntil: "networkidle",
    });

    out.layout = await page.evaluate(() => {
        const wrap = document.querySelector(".cf-card--flush .cf-table-wrap");
        const table = wrap?.querySelector("table");
        const grid = document.querySelector(".cf-grid.cf-grid--2");
        const cells = [...(table?.querySelectorAll("thead th") ?? [])].map(
            (th) => ({
                label: th.innerText.trim().slice(0, 20),
                width: Math.round(th.getBoundingClientRect().width),
            }),
        );
        return {
            viewport: window.innerWidth,
            gridWidth: grid
                ? Math.round(grid.getBoundingClientRect().width)
                : null,
            wrapWidth: wrap
                ? Math.round(wrap.getBoundingClientRect().width)
                : null,
            wrapScrolls: wrap ? wrap.scrollWidth > wrap.clientWidth : null,
            tableWidth: table
                ? Math.round(table.getBoundingClientRect().width)
                : null,
            cells,
        };
    });

    return out;
}

