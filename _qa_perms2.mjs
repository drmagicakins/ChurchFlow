export default async function run(page, ui) {
    const out = { consoleErrors: [], csp: [] };
    page.on("console", (m) => {
        if (m.type() === "error") {
            const t = m.text();
            out.consoleErrors.push(t.slice(0, 220));
            if (/Content Security Policy/i.test(t))
                out.csp.push(t.slice(0, 220));
        }
    });

    await page.goto("http://127.0.0.1:8000/login", {
        waitUntil: "networkidle",
    });
    await page.fill('input[name="email"]', "owner@churchflow.test");
    await page.fill('input[name="password"]', "Owner@12345");
    await page.click('button[type="submit"]');
    await page.waitForLoadState("networkidle");

    await page.goto("http://127.0.0.1:8000/settings?tab=team", {
        waitUntil: "networkidle",
    });

    // Which pastor boxes are ticked BEFORE we touch anything?
    out.before = await page.evaluate(() => {
        const d = [
            ...document.querySelectorAll("#parish-pastor-permissions details"),
        ].find((x) => x.innerText.includes("QA Parish Pastor"));
        if (!d) return null;
        d.open = true;
        return [...d.querySelectorAll('input[name="permissions[]"]')]
            .filter((b) => b.checked)
            .map((b) => b.value)
            .sort();
    });

    const detailsId = await page.evaluate(() => {
        const d = [
            ...document.querySelectorAll("#parish-pastor-permissions details"),
        ].find((x) => x.innerText.includes("QA Parish Pastor"));
        return d ? d.id : null;
    });

    if (detailsId) {
        const form = page.locator("#" + detailsId + " form");
        // Uncheck everything, then tick exactly two boxes.
        const boxes = form.locator('input[name="permissions[]"]');
        const n = await boxes.count();
        for (let i = 0; i < n; i++) {
            if (await boxes.nth(i).isChecked()) await boxes.nth(i).uncheck();
        }
        await form.locator('input[value="members.create"]').check();
        await form.locator('input[value="finance.record"]').check();
        await form.locator('button[type="submit"]').click();
        await page.waitForLoadState("networkidle");

        out.afterSave = await page.evaluate(() => {
            const d = [
                ...document.querySelectorAll(
                    "#parish-pastor-permissions details",
                ),
            ].find((x) => x.innerText.includes("QA Parish Pastor"));
            return {
                flashCount: document.querySelectorAll(".cf-alert--ok").length,
                flashText: [...document.querySelectorAll(".cf-alert--ok")].map(
                    (e) => e.innerText.replace(/\s+/g, " ").trim().slice(0, 90),
                ),
                ticked: [...d.querySelectorAll('input[name="permissions[]"]')]
                    .filter((b) => b.checked)
                    .map((b) => b.value)
                    .sort(),
            };
        });
    }

    // Reload: does the save persist?
    await page.goto("http://127.0.0.1:8000/settings?tab=team", {
        waitUntil: "networkidle",
    });
    out.persisted = await page.evaluate(() => {
        const d = [
            ...document.querySelectorAll("#parish-pastor-permissions details"),
        ].find((x) => x.innerText.includes("QA Parish Pastor"));
        if (!d) return null;
        d.open = true;
        return [...d.querySelectorAll('input[name="permissions[]"]')]
            .filter((b) => b.checked)
            .map((b) => b.value)
            .sort();
    });

    // The roles matrix: is the "granted" tick a sensible size (not squashed)?
    await page.goto("http://127.0.0.1:8000/settings?tab=roles", {
        waitUntil: "networkidle",
    });
    out.matrix = await page.evaluate(() => {
        const badge = document.querySelector(".mk-matrix__yes");
        if (!badge) return { found: false };
        const r = badge.getBoundingClientRect();
        return {
            found: true,
            width: Math.round(r.width),
            height: Math.round(r.height),
        };
    });

    await page.screenshot({ path: "_perms_owner2.png" });
    return out;
}
