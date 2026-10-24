// TEMPORARY QA — deleted after use.
import { chromium } from "playwright";

const base = "http://127.0.0.1:8000";
const browser = await chromium.launch({
    executablePath:
        "C:\\Users\\DELL\\AppData\\Local\\ms-playwright\\chromium-1243\\chrome-win64\\chrome.exe",
});
const page = await browser.newPage();
const errors = [];
page.on("pageerror", (e) =>
    errors.push("pageerror: " + String(e).slice(0, 400)),
);
page.on(
    "console",
    (m) =>
        m.type() === "error" &&
        errors.push("console: " + m.text().slice(0, 400)),
);
page.on("response", (r) => {
    if (r.status() >= 400) errors.push(`http ${r.status()} ${r.url()}`);
});

await page.goto(base + "/login", { waitUntil: "networkidle" });
await page.fill(
    'input[name="email"]',
    process.env.QA_EMAIL || "owner@churchflow.test",
);
await page.fill('input[name="password"]', process.env.QA_PASS || "Owner@12345");
await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle", timeout: 45000 }),
    page.click('button[type="submit"]'),
]);

const out = { steps: [] };
const capture = async (label, extra) => {
    out.steps.push({
        at: label,
        url: page.url(),
        ...(extra || {}),
    });
};

await page.goto(base + "/departments", { waitUntil: "networkidle" });
await page.waitForTimeout(400);
const links = await page
    .locator("table.cf-table tbody tr a")
    .evaluateAll((els) =>
        els.map((e) => ({
            text: e.textContent.trim(),
            href: e.getAttribute("href"),
        })),
    );
out.indexLinks = links;
const firstHref = links[0]?.href;
await page.goto(firstHref.startsWith("http") ? firstHref : base + firstHref, {
    waitUntil: "networkidle",
});
await capture("show", { title: await page.title() });

// click the "Add to department" submit after picking a member
const memberCount = await page.locator("#dept-member option").count();
out.memberOptions = memberCount;
if (memberCount > 1) {
    const val = await page
        .locator("#dept-member option")
        .nth(1)
        .getAttribute("value");
    await page.selectOption("#dept-member", val);
    const res = await Promise.all([
        page
            .waitForNavigation({ waitUntil: "networkidle", timeout: 20000 })
            .catch((e) => "timeout: " + e.message),
        page.click('button[type="submit"]'),
    ]);
    await page.waitForTimeout(400);
    await capture("afterAttach", {
        nav: String(res[0]),
        body: (await page.locator("main").innerText()).slice(0, 300),
    });
}

out.errors = errors;
await page.screenshot({ path: "_probe_dept.png", fullPage: true });
await browser.close();
console.log(JSON.stringify(out, null, 2));
