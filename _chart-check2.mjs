export default async function run(page, ui) {
    await page.goto("http://127.0.0.1:8000/login", {
        waitUntil: "domcontentloaded",
    });
    await page.waitForTimeout(1500);
    await page.fill(
        "input[type=email], input[name=email]",
        "owner@churchflow.test",
    );
    await page.fill(
        "input[type=password], input[name=password]",
        "Owner@12345",
    );
    await page.click("button[type=submit]");

    // Wait for the dashboard to actually be the page we are on.
    await page.waitForURL("**/dashboard", { timeout: 30000 });
    await page.waitForLoadState("load");

    // Wait for the module bundle to define Chart (this is the whole question).
    let chartAppeared = true;
    try {
        await page.waitForFunction(
            () => typeof window.Chart !== "undefined",
            null,
            { timeout: 15000 },
        );
    } catch (e) {
        chartAppeared = false;
    }

    // Give the chart a moment to draw.
    await page.waitForTimeout(2500);

    return await page
        .evaluate(() => {
            const canvas = document.getElementById("cfd-finance-chart");
            const out = {
                url: location.href,
                chartGlobal: typeof window.Chart,
                canvasFound: !!canvas,
            };
            if (canvas) {
                const r = canvas.getBoundingClientRect();
                out.canvasRect = {
                    w: Math.round(r.width),
                    h: Math.round(r.height),
                };
                out.chartInstance = !!window.Chart?.getChart?.(canvas);
                // Is anything actually painted on it?
                try {
                    const ctx = canvas.getContext("2d");
                    const px = ctx.getImageData(
                        0,
                        0,
                        canvas.width,
                        canvas.height,
                    ).data;
                    let nonBlank = 0;
                    for (let i = 3; i < px.length; i += 4)
                        if (px[i] !== 0) nonBlank++;
                    out.paintedPixels = nonBlank;
                } catch (e) {
                    out.paintError = String(e);
                }
            }
            return out;
        })
        .then((r) => ({ chartAppeared, ...r }));
}
