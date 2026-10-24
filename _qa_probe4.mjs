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
page.on("response", (r) => {
    if (r.status() >= 400) log.push(`http ${r.status()} ${r.url()}`);
});
page.on(
    "framenavigated",
    (f) => f === page.mainFrame() && log.push("nav -> " + f.url()),
);

await page.goto(base + "/login", { waitUntil: "networkidle" });
await page.fill('input[name="email"]', "owner@churchflow.test");
await page.fill('input[name="password"]', "Owner@12345");
await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle", timeout: 45000 }),
    page.click('button[type="submit"]'),
]);

await page.goto(base + "/departments/1", { waitUntil: "networkidle" });
const before = page.locator("#dept-member option").count();
log.push("options before: " + (await before));
const val = await page
    .locator("#dept-member option")
    .nth(1)
    .getAttribute("value");
await page.selectOption("#dept-member", val);
log.push("selected: " + val);
await page.click('button[type="submit"]');
await page.waitForTimeout(3000);
log.push("url now: " + page.url());
log.push(
    "body head: " + (await page.locator("body").innerText()).slice(0, 500),
);
await page.screenshot({ path: "_probe_dept2.png", fullPage: true });
console.log(log.join("\n"));
await browser.close();
