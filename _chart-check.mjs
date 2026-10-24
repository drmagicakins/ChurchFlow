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
    await page.waitForTimeout(6000);

    return await page.evaluate(() => {
        const canvas = document.getElementById("cfd-finance-chart");
        const box = canvas?.parentElement;
        const out = {
            url: location.href,
            hasChartGlobal: typeof window.Chart,
            canvasFound: !!canvas,
        };
        if (canvas) {
            const r = canvas.getBoundingClientRect();
            out.canvasRect = {
                w: Math.round(r.width),
                h: Math.round(r.height),
            };
            out.canvasAttrW = canvas.width;
            out.canvasAttrH = canvas.height;
            // Did Chart.js attach an instance?
            out.chartInstance = !!(
                window.Chart &&
                window.Chart.getChart &&
                window.Chart.getChart(canvas)
            );
            out.datasetCount = canvas.dataset
                ? Object.keys(canvas.dataset).length
                : 0;
        }
        if (box) {
            const br = box.getBoundingClientRect();
            out.boxRect = { w: Math.round(br.width), h: Math.round(br.height) };
            out.boxHTMLhead = box.outerHTML.slice(0, 200);
        }
        // Is the script block even in the DOM?
        const scripts = [...document.querySelectorAll("script")];
        out.scriptCount = scripts.length;
        out.hasFinanceScript = scripts.some(
            (s) =>
                s.textContent.includes("cfd-finance-chart") &&
                s.textContent.includes("new Chart"),
        );
        out.financeScriptSnippet = (
            scripts.find((s) => s.textContent.includes("new Chart"))
                ?.textContent || ""
        ).slice(0, 300);

        // What does the panel actually say?
        const panel = [...document.querySelectorAll("*")].find(
            (el) =>
                /Financial Overview/.test(el.textContent) &&
                el.children.length < 6 &&
                el.tagName !== "BODY",
        );
        out.panelText = panel
            ? panel.textContent.replace(/\s+/g, " ").trim().slice(0, 200)
            : null;
        return out;
    });
}
