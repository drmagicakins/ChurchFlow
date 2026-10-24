// TEMPORARY QA — deleted after use.
import { chromium } from "playwright";

const base = "http://127.0.0.1:8000";
const browser = await chromium.launch({
    executablePath:
        "C:\\Users\\DELL\\AppData\\Local\\ms-playwright\\chromium-1243\\chrome-win64\\chrome.exe",
});
const ctx = await browser.newContext();
const page = await ctx.newPage();
const errors = [];
page.on("pageerror", (e) => errors.push("pageerror: " + String(e).slice(0, 300)));
page.on("console", (m) => m.type() === "error" && errors.push("console: " + m.text().slice(0, 300)));
page.on("response", (r) => {
    if (r.status() >= 400) errors.push(`http ${r.status()} ${r.url()}`);
});

const out = {};

await page.goto(base + "/login", { waitUntil: "networkidle" });
await page.fill('input[name="email"]', process.env.QA_EMAIL || "owner@churchflow.test");
await page.fill('input[name="password"]', process.env.QA_PASS || "Owner@12345");
await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle", timeout: 45000 }),
    page.click('button[type="submit"]'),
]);
out.afterLogin = page.url();

const step = async (label, url, fn) => {
    await page.goto(base + url, { waitUntil: "networkidle" });
    out[label] = { url: page.url(), status: "ok" };
    if (fn) await fn();
};

await step("departments", "/departments", async () => {
    out.departments.rows = await page.locator("table.cf-table tbody tr").count();
});
await step("families", "/families");
await step("members", "/members", async () => {
    out.members.count = await page.locator("table.cf-table tbody tr").count();
});
await step("events", "/events", async () => {
    out.events.rows = await page.locator("table.cf-table tbody tr").count();
});
await step("sms", "/sms/campaigns");
await step("wallet", "/sms/wallet");

out.errors = errors;
await page.screenshot({ path: "_probe_last.png", fullPage: false });
await browser.close();
console.log(JSON.stringify(out, null, 2));
