export default async function run(page, ui) {
    await page.goto("http://127.0.0.1:8000/login", {
        waitUntil: "domcontentloaded",
    });
    await page.waitForTimeout(1500);

    await page.fill(
        "input[type=email], input[name=email]",
        "owner@churchflow.test",
    );
    await page.fill(
        "input[type=password], input[name=password]",
        "Owner@12345",
    );
    await page.click("button[type=submit]");
    await page.waitForTimeout(5000);

    return {
        url: page.url(),
        title: await page.title(),
        bodyChars: await page.evaluate(() => document.body.innerText.length),
    };
}
