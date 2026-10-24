// TEMPORARY QA — deleted after use.
import { chromium } from "playwright";

const base = "http://127.0.0.1:8000";
const browser = await chromium.launch({
    executablePath:
        "C:\\Users\\DELL\\AppData\\Local\\ms-playwright\\chromium-1243\\chrome-win64\\chrome.exe",
});
const page = await browser.newPage();
const log = [];
page.on("pageerror", (e) => log.push("pageerror: " + String(e).slice(0, 400)));
page.on(
    "console",
    (m) =>
        m.type() === "error" && log.push("console: " + m.text().slice(0, 400)),
);
page.on("request", (r) => {
    if (r.method() === "POST" && r.url().includes("departments"))
        log.push("REQ " + r.url() + " body=" + r.postData());
});
page.on("response", (r) => {
    if (r.request().method() === "POST")
        log.push(
            "RES " +
                r.status() +
                " " +
                r.url() +
                " loc=" +
                (r.headers()["location"] || ""),
        );
    if (r.status() >= 400) log.push(`http ${r.status()} ${r.url()}`);
});

await page.goto(base + "/login", { waitUntil: "networkidle" });
await page.fill('input[name="email"]', "owner@churchflow.test");
await page.fill('input[name="password"]', "Owner@12345");
await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle", timeout: 45000 }),
    page.click('button[type="submit"]'),
]);

await page.goto(base + "/departments/1", { waitUntil: "networkidle" });
await page.waitForTimeout(1200);
log.push("alpine typeof: " + (await page.evaluate(() => typeof window.Alpine)));
log.push(
    "has cfLoader: " + (await page.evaluate(() => typeof window.cfLoader)),
);
await page.evaluate(() => {
    window.__submitted = [];
    document.addEventListener(
        "submit",
        (e) => window.__submitted.push(e.target.action),
        true,
    );
});
const memberSelect = page.locator("#dept-member");
log.push(
    "selects with name member_id: " +
        (await page.locator('select[name="member_id"]').count()),
);
const val = await memberSelect.locator("option").nth(1).getAttribute("value");
await memberSelect.selectOption(val);
await page.locator('form[action$="/members"] button[type="submit"]').click();
await page.waitForTimeout(1200);
log.push(
    "submits seen: " +
        JSON.stringify(await page.evaluate(() => window.__submitted)),
);
log.push("url: " + page.url());
console.log(log.join("\n"));
await browser.close();
