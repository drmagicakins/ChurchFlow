// TEMPORARY QA — deleted after use.
import { chromium } from "playwright";

const base = "http://127.0.0.1:8000";
const browser = await chromium.launch({
    executablePath:
        "C:\\Users\\DELL\\AppData\\Local\\ms-playwright\\chromium-1243\\chrome-win64\\chrome.exe",
});
const page = await browser.newPage();
page.setDefaultTimeout(15000);
page.setDefaultNavigationTimeout(20000);
const log = [];
const step = (s) => {
    log.push("STEP " + s);
    process.stderr.write("STEP " + s + "\n");
};
const killer = setTimeout(() => {
    console.error("HARD TIMEOUT");
    process.exit(2);
}, 90000);
killer.unref?.();
page.on("pageerror", (e) => log.push("pageerror: " + String(e).slice(0, 300)));
page.on(
    "console",
    (m) =>
        m.type() === "error" && log.push("console: " + m.text().slice(0, 300)),
);
page.on("response", (r) => {
    if (r.status() >= 400) log.push(`http ${r.status()} ${r.url()}`);
});

step("goto login");
await page.goto(base + "/login", { waitUntil: "domcontentloaded" });
await page.fill('input[name="email"]', "qa-pastor@churchflow.test");
await page.fill('input[name="password"]', "Pastor@12345");
await Promise.all([
    page.waitForNavigation({ waitUntil: "domcontentloaded" }),
    page.click('button[type="submit"]'),
]);
await page
    .waitForLoadState("networkidle", { timeout: 10000 })
    .catch(() => log.push("WARN login networkidle timeout"));
step("logged in -> " + page.url());
log.push("PASTOR after-login url: " + page.url());

step("goto wallet");
await page.goto(base + "/sms/wallet", { waitUntil: "domcontentloaded" });
await page
    .waitForLoadState("networkidle", { timeout: 8000 })
    .catch(() => log.push("WARN wallet networkidle timeout"));
log.push("PASTOR wallet url: " + page.url());
log.push(
    "PASTOR wallet head: " +
        (
            await page
                .locator(".cf-page-head")
                .first()
                .innerText()
                .catch(() => "NO HEAD")
        ).replace(/\s+/g, " "),
);
log.push(
    "PASTOR balance hint: " +
        (await page
            .locator(".cf-stat__hint")
            .first()
            .innerText()
            .catch(() => "—")),
);
log.push(
    "can purchase (topup forms): " +
        (await page.locator('form[action$="/sms/wallet/purchase"]').count()),
);

step("goto campaigns");
await page.goto(base + "/sms/campaigns", { waitUntil: "domcontentloaded" });
await page
    .waitForLoadState("networkidle", { timeout: 8000 })
    .catch(() => log.push("WARN campaigns networkidle timeout"));
log.push("PASTOR campaigns url: " + page.url());
log.push(
    "PASTOR campaign balance hint: " +
        (await page
            .locator(".cf-stat__hint")
            .first()
            .innerText()
            .catch(() => "—")),
);
const branchOpts = await page
    .locator('select[name="organizational_unit_id"] option')
    .allInnerTexts()
    .catch(() => []);
log.push("PASTOR branch options: " + JSON.stringify(branchOpts));

// A parish pastor must only ever be offered their own parish. "Wisdom Parish"
// is a SIBLING branch in the same church and must not appear here.
const otherBranch = branchOpts.filter((o) => /Wisdom Parish/.test(o));
log.push(
    "PASTOR campaign branch picker leaks sibling parish: " +
        (otherBranch.length ? "YES " + JSON.stringify(otherBranch) : "no"),
);

// Same question for the other pickers on that form.
for (const name of ["group_id", "department_id"]) {
    const opts = await page
        .locator(`select[name="${name}"] option`)
        .allInnerTexts()
        .catch(() => []);
    log.push(
        `${name} options: ` +
            JSON.stringify(opts.map((o) => o.trim()).filter(Boolean)),
    );
}

step("goto branches");
await page.goto(base + "/branches", { waitUntil: "domcontentloaded" });
await page
    .waitForLoadState("networkidle", { timeout: 8000 })
    .catch(() => log.push("WARN branches networkidle timeout"));
log.push("PASTOR branches url: " + page.url());
log.push(
    "PASTOR branches: " +
        (
            await page
                .locator("table.cf-table tbody")
                .innerText()
                .catch(() => "none")
        ).replace(/\s+/g, " "),
);

step("goto events");
await page.goto(base + "/events", { waitUntil: "domcontentloaded" });
await page
    .waitForLoadState("networkidle", { timeout: 8000 })
    .catch(() => log.push("WARN events networkidle timeout"));
log.push("PASTOR events url: " + page.url());
log.push(
    "PASTOR events: " +
        (
            await page
                .locator("table.cf-table tbody")
                .innerText()
                .catch(() => "none")
        )
            .replace(/\s+/g, " ")
            .slice(0, 200),
);

step("goto departments");
await page.goto(base + "/departments", { waitUntil: "domcontentloaded" });
await page
    .waitForLoadState("networkidle", { timeout: 8000 })
    .catch(() => log.push("WARN departments networkidle timeout"));
log.push(
    "PASTOR departments: " +
        (
            await page
                .locator("table.cf-table tbody")
                .innerText()
                .catch(() => "none")
        )
            .replace(/\s+/g, " ")
            .slice(0, 200),
);

step("screenshot");
await page.screenshot({ path: "_shot_pastor_wallet.png", fullPage: false });
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
process.exit(0);
