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

const login = async (email, pass) => {
    await page.goto(base + "/login", { waitUntil: "networkidle" });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', pass);
    await Promise.all([
        page.waitForNavigation({ waitUntil: "networkidle" }),
        page.click('button[type="submit"]'),
    ]);
};

await login("owner@churchflow.test", "Owner@12345");

// Families
await page.goto(base + "/families", { waitUntil: "networkidle" });
log.push(
    "FAMILIES head: " +
        (await page.locator(".cf-page-head").innerText()).replace(/\s+/g, " "),
);
const opts = await page.locator("#family-filter option").allInnerTexts();
log.push("filter options: " + JSON.stringify(opts));
log.push(
    "member rows: " +
        (await page.locator("#members table.cf-table tbody tr").count()),
);
log.push(
    "first members: " +
        (await page.locator("#members table.cf-table tbody").innerText())
            .replace(/\s+/g, " ")
            .slice(0, 250),
);
await page.screenshot({ path: "_shot_families.png", fullPage: false });

// Departments
await page.goto(base + "/departments", { waitUntil: "networkidle" });
const deptLink = await page
    .locator("table.cf-table tbody tr a")
    .first()
    .getAttribute("href");
log.push("dept link: " + deptLink);
await page.goto(deptLink.startsWith("http") ? deptLink : base + deptLink, {
    waitUntil: "networkidle",
});
log.push("dept page: " + (await page.locator("h1").first().innerText()));
log.push(
    "has edit toggle: " +
        (await page.locator('[data-toggle="dept-edit"]').count()),
);
await page.click('[data-toggle="dept-edit"]');
await page.waitForTimeout(300);
await page.fill(
    'input[name="name"]',
    await page.locator('input[name="name"]').inputValue(),
);
await page.fill('textarea[name="description"]', "edited by QA");
await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle" }),
    page.click('[data-panel="dept-edit"] button[type="submit"]'),
]);
log.push(
    "after dept save: " +
        (await page
            .locator(".cf-alert")
            .first()
            .innerText()
            .catch(() => "no alert")),
);
await page.screenshot({ path: "_shot_dept.png", fullPage: false });

// SMS wallet (owner)
await page.goto(base + "/sms/wallet", { waitUntil: "networkidle" });
log.push(
    "OWNER wallet hint: " +
        (await page.locator(".cf-stat__hint").first().innerText()),
);

log.push(
    "errors: " +
        JSON.stringify([
            ...new Set(
                log.filter((l) => /pageerror|console:|http 4|http 5/.test(l)),
            ),
        ]),
);
console.log(log.join("\n"));
await browser.close();
