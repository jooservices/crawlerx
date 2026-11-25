// Age gates and consent interstitials of the sites, shared by the browser scripts.
// Returns the response of the page the browser had to return to after a click took it away, or null.

export async function dismissInterstitials(page, context, targetUrl) {
    const requestedPath = new URL(targetUrl).pathname;
    const startedOnRequestedPage = new URL(page.url()).pathname === requestedPath;

    try {
        const form = page.locator('#ageVerify form#form1').first();
        if ((await form.count()) > 0 && (await form.isVisible())) {
            const checkbox = form.locator('input[type="checkbox"]').first();
            if ((await checkbox.count()) > 0) {
                await checkbox.check({ timeout: 3000 });
            }
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => null),
                form.evaluate((element) => {
                    const submitValue = document.createElement('input');
                    submitValue.type = 'hidden';
                    submitValue.name = 'Submit';
                    submitValue.value = 'confirm';
                    element.appendChild(submitValue);
                    element.submit();
                }),
            ]);
            await page.waitForTimeout(1500);
            if (page.url().includes('/doc/driver-verify')) {
                await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 15000 });
            }
        }
    } catch {
        // best-effort JavBus age form submission
    }

    for (const selector of [
        'button[data-action="over18#accept"]',
        'button:has-text("Yes, I am of legal age")',
        'button:has-text("I am 18")',
        'button:has-text("I\'m 18")',
        'a:has-text("I am 18")',
        // The whole text of the control, nested elements included: a substring match would click any link
        // whose title contains "enter" (JOOXCR-522).
        (current) => current.locator('a, button').filter({ hasText: /^\s*enter\s*$/i }),
        'input[value="I am over 18"]',
        '#age-verify-yes',
    ]) {
        try {
            const locator = (typeof selector === 'string' ? page.locator(selector) : selector(page)).first();
            if ((await locator.count()) > 0 && (await locator.isVisible())) {
                await locator.click({ timeout: 3000 });
                await page.waitForTimeout(1500);
            }
        } catch {
            // best-effort
        }
    }

    try {
        const hostname = new URL(page.url()).hostname;
        const domain = hostname.startsWith('www.') ? hostname.slice(3) : hostname;
        await context.addCookies([
            { name: 'over18', value: '18', domain, path: '/' },
            { name: 'age', value: 'verified', domain, path: '/' },
            { name: 'dv', value: '1', domain, path: '/' },
            { name: 'existmag', value: 'all', domain, path: '/' },
        ]);
    } catch {
        // ignore cookie failures
    }

    try {
        // A click that took a requested page somewhere else is not the page that was asked for.
        if (startedOnRequestedPage && new URL(page.url()).pathname !== requestedPath) {
            return await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 15000 });
        }
    } catch {
        // best-effort: the caller reads whatever page is open
    }

    return null;
}
