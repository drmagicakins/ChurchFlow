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

await page.goto(base + "/login", { waitUntil: "networkidle" });
await page.fill('input[name="email"]', "owner@churchflow.test");
await page.fill('input[name="password"]', "Owner@12345");
await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle", timeout: 45000 }),
    page.click('button[type="submit"]'),
]);

for (const url of ["/families", "/members", "/settings?tab=team"]) {
    await page.goto(base + url, { waitUntil: "networkidle" });
    await page.screenshot({
        path: "_shot_" + url.replace(/[^a-z]/gi, "_") + ".png",
        fullPage: false,
    });
}

// open import panel and list what the page offers
await page.goto(base + "/members", { waitUntil: "networkidle" });
await page.click('[data-toggle="member-import"]');
await page.waitForTimeout(300);
const importPanel = await page
    .locator('[data-panel="member-import"]')
    .innerText();
log.push("IMPORT PANEL:\n" + importPanel);

// department show attach, capturing the validator message
await page.goto(base + "/departments/1", { waitUntil: "networkidle" });
const val = await page
    .locator("#dept-member option")
    .nth(1)
    .getAttribute("value");
await page.selectOption("#dept-member", val);
await page.click('button[type="submit"]');
await page.waitForTimeout(2500);
log.push("AFTER ATTACH url: " + page.url());
log.push(
    "AFTER ATTACH page: " +
        (await page.locator("body").innerText()).slice(0, 1200),
);

console.log(log.join("\n\n"));
await browser.close();
