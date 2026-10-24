// TEMPORARY QA — deleted after use.
import { chromium } from "playwright";

const base = "http://127.0.0.1:8000";
const browser = await chromium.launch({
    executablePath:
        "C:\\Users\\DELL\\AppData\\Local\\ms-playwright\\chromium-1243\\chrome-win64\\chrome.exe",
});
const page = await browser.newPage();
const log = [];
page.on("pageerror", (e) => log.push("pageerror: " + String(e).slice(0, 300)));
page.on(
    "console",
    (m) =>
        m.type() === "error" && log.push("console: " + m.text().slice(0, 300)),
);
page.on("response", (r) => {
    if (r.status() >= 400) log.push(`http ${r.status()} ${r.url()}`);
});

// 1. Sign in as owner
await page.goto(base + "/login", { waitUntil: "networkidle" });
await page.fill('input[name="email"]', "owner@churchflow.test");
await page.fill('input[name="password"]', "Owner@12345");
await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle" }),
    page.click('button[type="submit"]'),
]);

// 2. Open the first event, mint its public form
await page.goto(base + "/events", { waitUntil: "networkidle" });
const firstEvent = await page
    .locator("table.cf-table tbody tr a")
    .first()
    .getAttribute("href");
await page.goto(firstEvent, { waitUntil: "networkidle" });
log.push("event page: " + (await page.locator("h1").first().innerText()));
await page.click('form[action$="/public-form"] button');
await page.waitForTimeout(1200);
log.push("share url: " + page.url());
const mainText = await page.locator("main").innerText();
log.push("flash: " + mainText.split("\n").slice(0, 4).join(" | "));
const publicLink = await page
    .locator("#visitors input[readonly]")
    .inputValue()
    .catch(() => null);
log.push("public link: " + publicLink);

// 3. Register as a visitor in a FRESH context (no session at all)
const visitor = await browser.newContext();
const vp = await visitor.newPage();
vp.on("pageerror", (e) =>
    log.push("visitor pageerror: " + String(e).slice(0, 300)),
);
vp.on(
    "console",
    (m) =>
        m.type() === "error" &&
        log.push("visitor console: " + m.text().slice(0, 300)),
);
await vp.goto(publicLink, { waitUntil: "networkidle" });
log.push("visitor heading: " + (await vp.locator("h1").first().innerText()));
await vp.fill("#reg-name", "QA Visitor Ada");
await vp.fill("#reg-email", "qa.visitor@example.com");
await vp.fill("#reg-phone", "+2348000000001");
await vp.fill("#reg-org", "QA Org");
await vp.fill("#reg-guests", "2");
await Promise.all([
    vp.waitForNavigation({ waitUntil: "networkidle" }),
    vp.click('form button[type="submit"]'),
]);
log.push(
    "visitor after submit: " +
        (await vp
            .locator(".cf-alert")
            .first()
            .innerText()
            .catch(() => "no alert")),
);

// 4. Organiser sees the visitor
await page.reload({ waitUntil: "networkidle" });
const visitorRows = await page
    .locator("#visitors table.cf-table tbody tr")
    .count();
log.push("visitor rows: " + visitorRows);
log.push(
    "visitor table: " +
        (
            await page
                .locator("#visitors table.cf-table tbody")
                .innerText()
                .catch(() => "none")
        )
            .replace(/\s+/g, " ")
            .slice(0, 300),
);

// 5. Email reminder
if (await page.locator('form[action$="/remind/email"] button').count()) {
    await page.click('form[action$="/remind/email"] button');
    await page.waitForTimeout(1000);
    log.push(
        "after email remind: " +
            (await page
                .locator(".cf-alert")
                .first()
                .innerText()
                .catch(() => "no alert")),
    );
}

await page.screenshot({ path: "_shot_event.png", fullPage: true });
log.push(
    "errors: " +
        JSON.stringify([
            ...new Set(log.filter((l) => /error|http 4|http 5/i.test(l))),
        ]),
);
console.log(log.join("\n"));
await browser.close();
