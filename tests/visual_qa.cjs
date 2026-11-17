/* Explicitly requested Playwright QA. Uses an isolated profile; never edits source DB records. */
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || '/private/tmp/faculty-phase5-playwright/node_modules/playwright');
const root = path.resolve(__dirname, '..');
const stage = process.env.QA_STAGE || 'before';
const base = process.env.QA_BASE_URL || 'http://localhost/Faculty/';
if (!/^http:\/\/(localhost\/Faculty\/|127\.0\.0\.1:\d+\/)$/.test(base)) throw Error('Local portal only');
const output = stage==='phase6' ? path.join(root,'analytics/phase6/browser') : path.join(root, 'analytics/qa', stage);
fs.mkdirSync(path.join(output,'screenshots'), {recursive:true});
const evidenceStyle = stage==='fixtures' ? 'html::after {content:"ISOLATED QA FIXTURES — NOT UNIVERSITY RECORDS";position:fixed;left:0;right:0;top:0;text-align:center;z-index:99999;background:#fff4d6;color:#704000;font:11px sans-serif;padding:4px 8px;border:1px solid #bb8b22;} @media print {html::after {top:auto;bottom:0;}}' : '';
function php(code) {
 const p = spawnSync('/Applications/XAMPP/xamppfiles/bin/php',['-r',code],{cwd:root,encoding:'utf8',env:process.env});
 if(p.status) throw Error(p.stderr);
 return JSON.parse(p.stdout);
}
const credentials = php('require "config.php"; echo json_encode([DEFAULT_USER_EMAIL,DEFAULT_USER_PASS]);');
const routes = ['index.php','index.php?view=brief','incoming.php','schedule.php','enrollment.php','grades.php','at-risk.php','evaluations.php','setup.php','search.php?q=QA','predictions.php','class.php?id=999999'];
if(stage==='fixtures' && !/^bat405_verify_[0-9_]+$/.test(process.env.BAT405_TEST_DB||'')) throw Error('Populated browser fixtures require isolated database');
if(stage==='phase6') { const x=php('require "includes/dataset.php"; echo json_encode(rows("SELECT id FROM sections WHERE term_id=? ORDER BY id LIMIT 1",[active_term()["id"]])[0]);'); routes.push('class.php?id='+x.id); }
if(process.env.QA_CLASS_ID) routes.push('class.php?id='+Number(process.env.QA_CLASS_ID));
const viewports = [{name:'desktop',width:1440,height:1000},{name:'tablet',width:820,height:1180},{name:'mobile',width:390,height:844}];
(async()=>{
 const browser = await chromium.launch({channel:'chrome',headless:true});
 const report = {stage,browser:browser.version(),base,pages:[],pageErrors:[],consoleErrors:[],failedRequests:[],httpFailures:[],interactions:[],intentionalFaults:[]};
 try {
 for(const viewport of viewports) {
  const context = await browser.newContext({viewport:{width:viewport.width,height:viewport.height},isMobile:viewport.name!=='desktop',hasTouch:viewport.name!=='desktop',deviceScaleFactor:1,reducedMotion:'reduce'});
  const page = await context.newPage(); let currentRoute='login.php'; let intentionalFault=false;
  page.on('pageerror',err=>report.pageErrors.push({viewport:viewport.name,route:currentRoute,error:err.message}));
  page.on('console',msg=>{if(msg.type()==='error')report.consoleErrors.push({viewport:viewport.name,route:currentRoute,error:msg.text(),expected:intentionalFault});});
  page.on('requestfailed',req=>report.failedRequests.push({viewport:viewport.name,route:currentRoute,url:req.url(),error:req.failure()?.errorText}));
  page.on('response',res=>{if(res.status()>=400) report.httpFailures.push({viewport:viewport.name,route:currentRoute,url:res.url(),status:res.status(),expected:intentionalFault});});
  await page.goto(base+'login.php',{waitUntil:'networkidle'});
  await page.screenshot({path:path.join(output,'screenshots',viewport.name+'-login.png'),fullPage:true,animations:'disabled',style:evidenceStyle});
  await page.locator('[name="email"]').fill(credentials[0]);
  await page.locator('[name="password"]').fill(credentials[1]);
  await Promise.all([page.waitForURL('**/index.php'),page.locator('button[type="submit"]').click()]);
  for(const route of routes) {
   currentRoute=route;
   const res=await page.goto(base+route,{waitUntil:'networkidle'});
   await page.evaluate(()=>document.fonts.ready);
   const geometry=await page.evaluate(()=>{
    const w=innerWidth;
    return {viewport:w,documentWidth:document.documentElement.scrollWidth,bodyWidth:document.body.scrollWidth,
     overflow:[...document.querySelectorAll('main *')].filter(e=>{
      const r=e.getBoundingClientRect(); const s=getComputedStyle(e);
      return r.width>0&&r.height>0&&s.display!=='none'&&s.visibility!=='hidden'&&!e.closest('.table-wrap,dialog,[hidden]')&&(r.right>w+2||r.left < -2);
     }).slice(0,16).map(e=>({tag:e.tagName,class:e.className,text:e.textContent.trim().slice(0,70),width:Math.round(e.getBoundingClientRect().width)}))};
   });
   const name=viewport.name+'-'+route.split('?')[0].replace('.php','')+(route.includes('view=brief')?'-brief':'')+(route.startsWith('class.php')&&process.env.QA_CLASS_ID&&route.includes('='+process.env.QA_CLASS_ID)?'-populated':(stage==='phase6'&&route.startsWith('class.php')&&!route.includes('999999')?'-populated':''));
   await page.screenshot({path:path.join(output,'screenshots',name+'.png'),fullPage:true,animations:'disabled',style:evidenceStyle,maskColor:'#e7edf5',mask:[page.locator('.sidebar-user')]});
   report.pages.push({viewport:viewport.name,route,status:res.status(),expected:intentionalFault,url:page.url(),...geometry,screenshot:'screenshots/'+name+'.png'});
  }
  await page.goto(base+'index.php',{waitUntil:'networkidle'});
  const assert = (condition,name) => { report.interactions.push({viewport:viewport.name,name,pass:!!condition}); if(!condition)throw Error(name); };
  if(stage !== 'before') {
   assert(await page.locator('#bat405Kpis > .stat').count()===5,'Five primary institutional KPI cards');
   assert(await page.locator('[data-chart]').count()>=3,'Three identified institutional visualizations/placeholders');
   assert(await page.locator('[role="alert"]').count()>0,'A genuine setup or threshold warning is visible');
   if(stage==='phase6') {
    assert((await page.locator('[aria-label="Demonstration data"]').innerText()).includes('DEMONSTRATION DATA'),'Global academic provenance label');
    assert((await page.locator('#bat405Kpis').innerText()).includes('768'),'Current cohort from inserted rows');
    assert(await page.locator('[data-chart="enrollment"] li').count()===4,'Four populated college enrollment bars');
    assert(await page.locator('[data-chart="grade-submission"] svg').count()===1,'Populated grade submission chart');
    assert(await page.locator('[data-chart="sete-dimensions"] li').count()===5,'Five populated SETE dimensions');
    const independent=await page.locator('[aria-label="Demonstration predictive analytics"]').innerText();
    await Promise.all([page.waitForURL('**college=Demo+Computing'),page.locator('[name="college"]').first().selectOption('Demo Computing')]);
    await page.waitForLoadState('networkidle');
    assert((await page.locator('#bat405Kpis').innerText()).includes('192'),'College filter recalculates student KPI');
    assert(await page.locator('[aria-label="Demonstration predictive analytics"]').innerText()===independent,'College filter does not change independent UCI results');
    await page.goto(base+'index.php',{waitUntil:'networkidle'});
    await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),page.getByLabel('Academic year',{exact:true}).selectOption('2025-2026')]);
    assert((await page.locator('#bat405Kpis').innerText()).includes('576'),'Academic-year filter recalculates historical cohort');
    const termOptions=await page.getByLabel('Semester',{exact:true}).locator('option').evaluateAll(opts=>opts.map(o=>({id:o.value,name:o.textContent})));
    assert(termOptions.length===2,'Prior academic year has two semesters');
    await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),page.getByLabel('Semester',{exact:true}).selectOption(termOptions[1].id)]);
    assert((await page.locator('#bat405Kpis').innerText()).includes('96'),'Historical semester has its own class count');
    assert((await page.locator('[data-chart="risk-trend"]').innerText()).includes('2025'),'Historical risk trend does not append current-year point');
    assert(await page.locator('body').evaluate(e=>!/(Warning:|Fatal error|Uncaught)/.test(e.innerText)),'Historical queries have no runtime errors');
    await page.screenshot({path:path.join(output,'screenshots',viewport.name+'-historical.png'),fullPage:true,animations:'disabled',maskColor:'#e7edf5',mask:[page.locator('.sidebar-user')]});
    for(const historicalRoute of ['enrollment.php','grades.php','at-risk.php','evaluations.php','schedule.php']) {
     currentRoute=historicalRoute+' [historical]';
     const historicalResponse=await page.goto(base+historicalRoute,{waitUntil:'networkidle'});
     assert(historicalResponse.status()===200,'Historical module renders: '+historicalRoute);
     assert(!/(Warning:|Fatal error|Uncaught)/.test(await page.locator('body').innerText()),'Historical module has no PHP errors: '+historicalRoute);
     const dimensions=await page.evaluate(()=>({viewport:innerWidth,documentWidth:document.documentElement.scrollWidth}));
     const screenshot='screenshots/'+viewport.name+'-historical-'+historicalRoute.replace('.php','')+'.png';
     await page.screenshot({path:path.join(output,screenshot),fullPage:true,animations:'disabled',maskColor:'#e7edf5',mask:[page.locator('.sidebar-user')]});
     report.pages.push({viewport:viewport.name,route:historicalRoute+' [historical]',status:historicalResponse.status(),...dimensions,screenshot});
    }
    const histBody=await page.content(); const histToken=await page.locator('input[name="csrf"]').first().inputValue();
    const historyPost=await context.request.post(base+'actions.php',{form:{action:'student_save',csrf:histToken,return:'enrollment.php'}});
    assert((await historyPost.text()).includes('Historical terms are read-only'),'Historical mutations rejected server-side');
    await page.goto(base+'index.php?ay=2026-2027',{waitUntil:'networkidle'});
    assert((await page.locator('#bat405Kpis').innerText()).includes('768'),'Current academic year restores current cohort');
    const legend=page.locator('[data-chart="grade-submission"] .legend li').first();await legend.click();
    assert(await legend.evaluate(e=>e.classList.contains('is-off')),'Populated chart legend toggles');await legend.click();
    await page.goto(base+'enrollment.php',{waitUntil:'networkidle'});
    const dl=page.waitForEvent('download'); await page.getByRole('link',{name:'Export CSV',exact:true}).click();const csv=await dl;
    assert(csv.suggestedFilename().startsWith('DEMONSTRATION_DATA-'),'Export filename marked demonstration');
    const csvPath=await csv.path();assert(fs.readFileSync(csvPath,'utf8').includes('Data source')&&fs.readFileSync(csvPath,'utf8').includes('DEMONSTRATION DATA'),'Export rows labeled demonstration');
    (report.verifiedDownloads??=[]).push(base+'enrollment.php?export=csv');
    await page.getByRole('button',{name:'Add student',exact:true}).click();
    assert(await page.locator('#studentModal').isVisible(),'Populated enrollment form opens with references');
    await page.locator('#studentModal [data-close]').first().click();
    await page.goto(base+'schedule.php',{waitUntil:'networkidle'});
    await page.getByRole('button',{name:'Add class',exact:true}).click();
    assert(await page.locator('#classModal').isVisible(),'Scheduling form opens with populated references');
    assert(await page.locator('#classModal [name="course_id"] option').count()>=64,'Scheduling form contains generated courses');
    await page.locator('#classModal [data-close]').first().click();
    await page.goto(base+'at-risk.php',{waitUntil:'networkidle'});
    await page.locator('[data-modal="interventionModal"]').first().click();
    assert(await page.locator('#interventionModal').isVisible(),'Academic intervention form opens for a generated case');
    await page.locator('#interventionModal [data-close]').first().click();
    await page.goto(base+'evaluations.php',{waitUntil:'networkidle'});
    await page.locator('[data-modal="facultyModal"][data-fill]').first().click();
    assert(await page.locator('#facultyModal [name="first_name"]').inputValue().then(v=>v.startsWith('DEMO ')),'Faculty form loads generated teaching record');
    await page.locator('#facultyModal [data-close]').first().click();
    await page.goto(base+'index.php',{waitUntil:'networkidle'});
   }
   if(stage==='fixtures') {
    assert(await page.locator('[data-chart="enrollment"] li').count()>0,'Populated enrollment bar chart');
    assert(await page.locator('[data-chart="grade-submission"] svg').count()>0,'Populated grade-submission donut chart');
    assert(await page.locator('[data-chart="sete-dimensions"] li').count()===5,'Populated five-dimension SETE chart');
    assert((await page.locator('[data-dashboard-warning]').innerText()).includes('Overdue grade submissions'),'Real overdue-grade condition triggers warning');
    assert((await page.locator('#bat405Kpis').innerText()).includes('4.20'),'SETE KPI matches isolated 4.2 response');
    assert((await page.locator('.eval-stats').innerText()).includes('50%'),'SETE response rate includes the unresponded faculty invitation');
    const beforePrediction=await page.locator('[aria-label="Demonstration predictive analytics"]').innerText();
    await Promise.all([page.waitForURL('**college=Test+College'),page.locator('[name="college"]').first().selectOption('Test College')]);
    await page.waitForLoadState('networkidle');
    assert((await page.locator('[data-chart="enrollment"]').innerText()).includes('TSTP'),'College selection filters enrollment to programs');
    assert(await page.locator('[aria-label="Demonstration predictive analytics"]').innerText()===beforePrediction,'College filter never scopes external UCI sample counts');
    const legend=page.locator('[data-chart="grade-submission"] .legend li').nth(1);
    await legend.click();
    assert(await legend.evaluate(e=>e.classList.contains('is-off')),'Donut legend toggles slice');
    await legend.click();
    assert(!await legend.evaluate(e=>e.classList.contains('is-off')),'Donut legend restores slice');
    await page.locator('[data-chart="sete-dimensions"] li').first().scrollIntoViewIfNeeded();
    await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
    await page.mouse.move(0,0);
    await page.locator('[data-chart="sete-dimensions"] li').first().hover();
    await page.locator('#tip').waitFor({state:'visible',timeout:3000});
    assert(await page.locator('#tip').isVisible(),'SETE bars reveal interactive tooltip');
   }
   assert((await page.locator('[aria-label="Demonstration predictive analytics"]').innerText()).includes('External UCI'),'External predictions clearly separate from institutional KPIs');
   await page.locator('[data-filter="Qualified"]').click();
   assert(await page.locator('[data-filter="Qualified"]').evaluate(e=>e.classList.contains('is-active')),'Applicant chip interaction');
   await page.getByRole('button',{name:'Help',exact:true}).click();
   assert(await page.locator('#helpModal').isVisible(),'Help dialog opens');
   await page.locator('#helpModal [data-close]').first().click();
   assert(!await page.locator('#helpModal').isVisible(),'Help dialog closes');
   const gold = php('require "includes/prediction.php"; $out=[]; foreach(prediction_samples() as $i=>$sample){$p=predict_outcome($sample["features"]);$out[$p["outcome"]]??=["index"=>$i,"features"=>$sample["features"]];} echo json_encode($out);');
   await page.goto(base+'predictions.php',{waitUntil:'networkidle'}); currentRoute='predictions.php';
   for(const outcome of ['Dropout','Enrolled','Graduate']) {
    const sample=gold[outcome]; assert(!!sample,'Saved demonstration sample for '+outcome);
    await page.locator('#demoSample').selectOption(String(sample.index));
    for(const [key,value] of Object.entries(sample.features))assert(Number(await page.locator('[data-feature="'+key+'"]').inputValue())===value,'Sample input loaded: '+outcome+'/'+key);
    await page.getByRole('button',{name:'Predict demonstration outcome',exact:true}).click();
    await page.locator('#predictionResult').waitFor({state:'visible'});
    assert((await page.locator('#predictedOutcome').innerText())===outcome,'Browser classification matches saved model: '+outcome);
    assert((await page.locator('#predictedRecommendation').innerText()).length>40,'Academic support recommendation: '+outcome);
    await page.screenshot({path:path.join(output,'screenshots',viewport.name+'-prediction-'+outcome.toLowerCase()+'.png'),fullPage:true,animations:'disabled',style:evidenceStyle,maskColor:'#e7edf5',mask:[page.locator('.sidebar-user')]});
   }
   if(viewport.name!=='desktop') {
    const scroll=await page.locator('.table-wrap').first().evaluate(e=>{e.scrollLeft=e.scrollWidth;return {left:e.scrollLeft,hasOverflow:e.scrollWidth>e.clientWidth};});
    assert(!scroll.hasOverflow || scroll.left>0,'Narrow confusion matrix scrolls to all outcome columns');
   }
   await page.locator('[data-feature="grade"]').fill('999');
   assert(await page.locator('[data-feature="grade"]').evaluate(e=>!e.checkValidity()),'Native grade-range validation');
   assert(!(await page.locator('#predictionResult').isVisible()),'Editing input clears stale prediction');
   await page.locator('[data-feature="enrolled"]').fill('2'); await page.locator('[data-feature="approved"]').fill('3');
   await page.locator('[data-feature="grade"]').fill('12');
   intentionalFault=true;
   await page.getByRole('button',{name:'Predict demonstration outcome',exact:true}).click();
   await page.locator('#predictionError').filter({hasText:'Passed units cannot exceed enrolled units'}).waitFor();
   assert((await page.locator('#predictionError').innerText()).includes('Passed units cannot exceed'),'API validation displayed in UI');
   await page.locator('[data-feature="approved"]').fill('1');
   await page.route('**/api/predict.php',r=>r.fulfill({status:503,contentType:'application/json',body:JSON.stringify({error:'QA simulated model service unavailable'})}));
   await page.getByRole('button',{name:'Predict demonstration outcome',exact:true}).click();
   await page.locator('#predictionError').filter({hasText:'QA simulated model service unavailable'}).waitFor();
   assert((await page.locator('#predictionError').innerText()).includes('QA simulated'),'API failure message displayed in UI');
   assert(await page.getByRole('button',{name:'Predict demonstration outcome',exact:true}).isEnabled(),'Prediction button recovers after API failure');
   await page.unroute('**/api/predict.php'); intentionalFault=false;
   report.intentionalFaults.push({viewport:viewport.name,validationStatus:422,simulatedServiceStatus:503});
   await page.goto(base+'index.php',{waitUntil:'networkidle'});
  }
  if(viewport.name==='desktop' && stage!=='before') {
   await page.goto(base+'index.php?view=brief',{waitUntil:'networkidle'});
   assert(await page.locator('#bat405Kpis > .stat').count()===5,'Brief contains the five KPI cards');
   assert(await page.locator('.dashboard-extended').isHidden(),'Brief retains full features via optional full-overview link');
   await page.emulateMedia({media:'print'}); await page.setViewportSize({width:740,height:1170});
   await page.screenshot({path:path.join(output,'screenshots','long-bond-print-preview.png'),fullPage:true,animations:'disabled',style:evidenceStyle});
   const printHeight=await page.evaluate(()=>document.documentElement.scrollHeight);
   report.interactions.push({viewport:'long-bond-print',name:'Brief fits printable long-bond height at 740px width',pass:printHeight<=1171,height:printHeight});
   await page.emulateMedia({media:'screen'}); await page.setViewportSize({width:viewport.width,height:viewport.height});
  }
  if(viewport.name!=='desktop') {
   await page.getByRole('button',{name:'Open menu',exact:true}).click();
   report.interactions.push({viewport:viewport.name,name:'Mobile navigation opens',pass:await page.locator('.sidebar').evaluate(e=>e.classList.contains('is-open'))});
   await page.locator('.sidebar-close').click();
   report.interactions.push({viewport:viewport.name,name:'Mobile navigation closes',pass:!await page.locator('.sidebar').evaluate(e=>e.classList.contains('is-open'))});
  }
  await page.waitForLoadState('networkidle');
  await context.close();
 }
 } finally {
  await browser.close(); fs.writeFileSync(path.join(output,'browser-results.json'),JSON.stringify(report,null,2)+'\n');
 }
 // Chrome may cancel favicon loads while navigating; verify the exact image
 // bytes from the same live test server before classifying these separately.
 report.verifiedDownloads ??=[];
 for(const event of report.failedRequests) if(event.error==='net::ERR_ABORTED'&&report.verifiedDownloads.includes(event.url))event.verifiedBrowserCancellation=true;
 report.cancelledIconChecks=[];
 for(const url of new Set(report.failedRequests.filter(e=>e.error==='net::ERR_ABORTED'&&e.url===base+'assets/img/logo.png').map(e=>e.url))) {
  const response=await fetch(url); const bytes=Buffer.from(await response.arrayBuffer());
  const healthy=response.status===200 && bytes.equals(fs.readFileSync(path.join(root,'assets/img/logo.png')));
  report.cancelledIconChecks.push({url,status:response.status,exactImageBytesMatch:healthy});
  if(healthy) for(const event of report.failedRequests.filter(e=>e.url===url&&e.error==='net::ERR_ABORTED')) event.verifiedBrowserCancellation=true;
 }
 report.status = !report.pageErrors.length && !report.failedRequests.some(e=>!e.verifiedBrowserCancellation) && !report.httpFailures.some(e=>!e.expected) && !report.consoleErrors.some(e=>!e.expected) && report.interactions.every(e=>e.pass) && report.pages.every(p=>p.documentWidth<=p.viewport+2) ? 'PASS' : 'FAIL';
 fs.writeFileSync(path.join(output,'browser-results.json'),JSON.stringify(report,null,2)+'\n');
 if(report.status!=='PASS') throw Error('Browser diagnostics found failures; review browser-results.json');
 console.log(JSON.stringify({stage,pages:report.pages.length,interactionChecks:report.interactions.length,interactionFailures:report.interactions.filter(x=>!x.pass),overflowPages:report.pages.filter(p=>p.documentWidth>p.viewport+2).map(p=>({viewport:p.viewport,route:p.route,width:p.documentWidth,overflow:p.overflow})),pageErrors:report.pageErrors,failedRequests:report.failedRequests.slice(0,5),httpFailures:report.httpFailures.slice(0,5),},null,2));
})().catch(e=>{console.error(e.message);process.exitCode=1;});
