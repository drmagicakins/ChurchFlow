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
await page.goto(base + "/departments", { waitUntil: "networkidle" });
out.steps.push({ at: "index", url: page.url(), status: 200 });

// Open create panel
await page.click('[data-toggle="dept-create"]');
await page.waitForTimeout(200);
await page.fill('input[name="name"]', "QA Probe Department");
await page.fill('textarea[name="description"]', "created by a QA probe");
await Promise.all([
    page
        .waitForNavigation({ waitUntil: "networkidle", timeout: 30000 })
        .catch(() => {}),
    page.click('button[type="submit"]'),
]);
out.steps.push({ at: "afterCreate", url: page.url() });
out.createdHtml = (await page.content()).slice(0, 400);

// Count total rows and click each View link
await page.goto(base + "/departments", { waitUntil: "networkidle" });
const links = await page
    .locator("table.cf-table tbody tr a")
    .evaluateAll((els) =>
        els.map((e) => ({
            text: e.textContent.trim(),
            href: e.getAttribute("href"),
        })),
    );
out.links = links;

for (const l of links) {
    const r = await page
        .goto(base + l.href, { waitUntil: "networkidle" })
        .catch((e) => null);
    out.steps.push({
        at: "show " + l.text,
        href: l.href,
        url: page.url(),
        status: r ? r.status() : "ERR",
    });
}

out.errors = errors;
await browser.close();
console.log(JSON.stringify(out, null, 2));
