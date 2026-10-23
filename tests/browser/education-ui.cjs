const {chromium} = require('playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({channel:'msedge',headless:true});
  try {
    const page = await browser.newPage();
    for (const width of [1440,390]) {
      await page.setViewportSize({width,height:950});
      await page.goto('http://127.0.0.1:8000/');
      await page.locator('.public-hero').waitFor();
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
      await page.screenshot({path:`D:/NonaX/build/local-flow/home-${width}.png`});
    }
    await page.goto('http://127.0.0.1:8000/login');
    await page.locator('[name=email]').fill('student@nonax.test');
    await page.locator('[name=password]').fill('password');
    await Promise.all([page.waitForURL('**/dashboard'),page.getByRole('button',{name:'Sign in',exact:true}).click()]);
    await page.locator('.menu-button').click();
    assert(await page.locator('body').evaluate(el=>el.classList.contains('nav-open')));
    await page.keyboard.press('Escape');
    assert(!(await page.locator('body').evaluate(el=>el.classList.contains('nav-open'))));
    for (const width of [1440,390]) {
      await page.setViewportSize({width,height:950});
      await page.screenshot({path:`D:/NonaX/build/local-flow/student-new-${width}.png`});
    }
    console.log('Homepage responsive checks and mobile navigation passed.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
