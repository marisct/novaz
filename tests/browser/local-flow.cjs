const { chromium } = require('playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');

(async () => {
  const browser = await chromium.launch({channel: 'msedge', headless: true});
  const results = [];
  const output = 'D:/NonaX/build/local-flow';
  fs.mkdirSync(output, {recursive: true});
  try {
    for (const role of ['admin', 'teacher', 'student']) {
      const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      await page.goto('http://127.0.0.1:8000/login');
      await page.locator('[name=email]').fill(`${role}@nonax.test`);
      await page.locator('[name=password]').fill('password');
      await Promise.all([page.waitForURL('**/dashboard'), page.getByRole('button', {name: 'Sign in', exact: true}).click()]);
      results.push({role, check: 'Web login', pass: true});
      const routes = role === 'admin' ? ['dashboard','students','teachers','courses','subjects','exams','attendance','materials'] : ['dashboard','exams','attendance','materials'];
      for (const route of routes) {
        const response = await page.goto(`http://127.0.0.1:8000/${route}`);
        assert.equal(response.status(), 200, `${role}/${route}`);
        if (role === 'student' && route === 'dashboard') {
          await page.getByRole('heading', {name: 'Weekly performance', exact: true}).waitFor();
          await page.getByRole('heading', {name: /Exam notifications/}).waitFor();
        }
        for (const width of [1440,390]) {
          await page.setViewportSize({width, height: 1000});
          const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 2);
          results.push({role, check: `${route} at ${width}px`, pass: !overflow, issue: overflow ? 'Horizontal overflow' : undefined});
        }
        if (route === 'dashboard' || route === 'exams') await page.screenshot({path: `${output}/${role}-${route}-mobile.png`, fullPage: true});
      }
      if (role === 'admin') {
        await page.goto('http://127.0.0.1:8000/exams');
        await page.getByRole('button', {name:'Create Exam', exact:true}).first().click();
        const select = page.locator('#createExamForm [name=exam_pattern_id]');
        await select.selectOption({label:'SSC CGL Tier 2 Paper 1 (2026)'});
        assert.equal(await page.locator('#createExamForm [name=duration_minutes]').inputValue(), '150');
        assert.equal(await page.locator('#createExamForm [name=total_questions]').inputValue(), '150');
        assert.equal(await page.locator('#destPassage').getAttribute('required'), '');
        results.push({role, check:'Tier 2 creation form: 150 questions, 150 minutes, typing passage required', pass:true});
        await page.screenshot({path:`${output}/exam-modal-mobile.png`,fullPage:true});
      }
      const login = await context.request.post('http://127.0.0.1:8000/api/login', {data:{email:`${role}@nonax.test`,password:'password'}});
      assert.equal(login.status(),200);
      const token = (await login.json()).token;
      for (const route of ['home','exam-patterns','notifications','mock-test','results']) {
        const response = await context.request.get(`http://127.0.0.1:8000/api/${route}`,{headers:{Authorization:`Bearer ${token}`}});
        assert.equal(response.status(),200);
        await response.json();
        results.push({role,check:`API ${route}`,pass:true});
      }
      results.push({role, check:'Browser JavaScript errors', pass:errors.length===0, errors});
      await context.close();
    }
  } catch (error) {
    results.push({check:'Browser run',pass:false,error:error.message});
    process.exitCode=1;
  } finally {
    await browser.close();
    fs.writeFileSync(`${output}/results.json`, JSON.stringify(results,null,2));
    console.log(JSON.stringify({checks:results.length,failed:results.filter(r=>!r.pass)},null,2));
  }
})();
