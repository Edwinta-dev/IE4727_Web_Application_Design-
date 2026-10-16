import assert from 'node:assert/strict';
import { chromium } from '../tools/ui/node_modules/playwright/index.mjs';
import { withTestServer, login, prepareStaticAssets } from '../tools/ui/lib.mjs';
import { testPhp } from '../tools/ui/isolation.mjs';
process.env.CLINIC_DB_NAME='ie4727db_test';
process.env.MAIL_DELIVERY='off';
const before=process.argv.includes('--before');
testPhp(['tools/db_reset.php','--test'],{encoding:'utf8'});
testPhp(['tests/fixtures/issue169_mobile.php'],{encoding:'utf8'});
await withTestServer(true,async base=>{
 const browser=await chromium.launch({headless:true});
 try {
  const page=await browser.newPage({javaScriptEnabled:false});
  await prepareStaticAssets(page,base);
  const check=async (route,role,selector,labels)=>{
   if(role) { await page.context().clearCookies(); await login(page,role,base); } else await page.goto(base+'doctors.php');
   if(role) await page.goto(base+route);
   for(const width of [320,360,390,1280]){
    await page.setViewportSize({width,height:width===1280?800:844});
    await page.reload();
    const result=await page.evaluate(({selector,labels})=>{
      const region=document.querySelector(selector); const row=region?.querySelector('tbody tr');
      return {overflow:document.documentElement.scrollWidth-innerWidth, wide:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).slice(0,8).map(e=>[e.tagName,e.className,e.innerText.slice(0,40),e.parentElement?.className,e.getBoundingClientRect().right]), labels:labels.map(label=>{
       const cell=[...row.querySelectorAll('[data-label]')].find(x=>x.dataset.label===label);
       const rect=cell?.getBoundingClientRect(); return [label,!!rect&&rect.width>0&&rect.left>=0&&rect.right<=innerWidth];
      })};
    },{selector,labels});
    if(result.overflow>0) console.log(JSON.stringify({route,width,...result})); assert.ok(result.overflow<=0,`${route} ${width}px document overflow ${result.overflow}`);
    if(!before&&width<=390) for(const [label,visible] of result.labels) assert.ok(visible,`${route} ${width}px ${label} visible in first row`);
    if(!before&&width===390){
     const keyboardTarget=page.locator(`${selector} tbody tr [data-label] a, ${selector} tbody tr [data-label] button, ${selector} tbody tr summary`).first();
     if(await keyboardTarget.count()){ await keyboardTarget.focus(); assert.equal(await keyboardTarget.evaluate(el=>document.activeElement===el),true,`${route} first-row action can receive keyboard focus`); }
     if(route==='admin/outbox.php'){ const summary=page.locator('.notification-details summary').first(); await summary.focus(); await page.keyboard.press('Enter'); assert.equal(await page.locator('.notification-details').first().getAttribute('open')!==null,true,'outbox message expands by keyboard'); }
    }

   }
   const capture=route.replaceAll('/','-').replace('.php','');
   await page.setViewportSize({width:390,height:844}); await page.reload();
   if(before) await page.addStyleTag({content:'.doctor-directory{display:table;min-width:62rem}.staff-table-scroll,.appointment-table-scroll,.outbox-table-scroll{overflow-x:auto}.day-board{display:table;min-width:58rem}.appointment-table-scroll table{display:table;min-width:46rem}.admin-outbox table{display:table;min-width:44rem}.doctor-directory thead,.day-board thead,.appointment-table-scroll thead,.admin-outbox thead{position:static;width:auto;height:auto;clip:auto;overflow:visible}.doctor-directory tbody,.day-board tbody,.appointment-table-scroll tbody,.admin-outbox tbody{display:table-row-group}.doctor-directory tbody tr,.day-board tbody tr,.appointment-table-scroll tbody tr,.admin-outbox tbody tr{display:table-row}.doctor-directory td::before,.doctor-directory th::before,.day-board td::before,.appointment-table-scroll td::before,.admin-outbox td::before{content:none}'});
   await page.screenshot({path:`UIPROBLEMS/after/issue164-${before?'before':'after'}/${capture}-390.png`,fullPage:true});
   await page.setViewportSize({width:1280,height:800}); await page.reload();
   if(before) await page.addStyleTag({content:'.doctor-directory{display:table;min-width:62rem}.staff-table-scroll,.appointment-table-scroll,.outbox-table-scroll{overflow-x:auto}.day-board{display:table;min-width:58rem}.appointment-table-scroll table{display:table;min-width:46rem}.admin-outbox table{display:table;min-width:44rem}.doctor-directory tbody tr,.day-board tbody tr,.appointment-table-scroll tbody tr,.admin-outbox tbody tr{display:table-row}'});
   await page.screenshot({path:`UIPROBLEMS/after/issue164-${before?'before':'after'}/${capture}-1280.png`,fullPage:true});
  };
  await check('doctors.php',null,'.doctor-directory',['Doctor','Specialty','Booking']);
  await check('doctor/home.php','doctor','.day-board',['Time','Patient','Status','Actions']);
  await check('patient/home.php','patient','.upcoming-appointments table',['Date','Time','Actions']);
  await check('admin/outbox.php','admin','.admin-outbox table',['Subject','Delivery status']);
  console.log(before?'OK: #164 reproduced pre-change horizontal table layouts and captured baseline':'OK: #164 mobile row fields visible at 320/360/390; keyboard actions and outbox expansion; no page overflow through 1280');
 } finally {await browser.close();}
});
