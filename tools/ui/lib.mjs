import { randomUUID } from 'node:crypto';
import { verifyTestConfig, testGuard } from './isolation.mjs';
import { spawn } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';
import { resolve, join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const root = resolve(fileURLToPath(new URL('../..', import.meta.url)));
export const appBase = 'http://127.0.0.1:8123/clinic-base/';
export const appBaseFor = (app='clinic-base') => {
  if (!['clinic-base','clinic-plus'].includes(app)) throw Error(`Unknown app ${app}`);
  return `http://127.0.0.1:8123/${app}/`;
};
const assetPages = new WeakSet();
async function waitForPageAssets(page) {
  await page.evaluate(async () => {
    for (const image of document.images) image.loading = 'eager';
    await Promise.all(Array.from(document.images, image => image.decode()));
  });
  await page.waitForLoadState('load');
}
export async function prepareStaticAssets(page, base=appBase) {
  if (assetPages.has(page)) return;
  // Observed local static transfers can reset or stop mid-response.
  // Fetch the same real bytes from the same server; only reset/interrupted
  // asset GET transfers receive bounded retries. HTTP errors, bad images and mutations are
  // never replaced with fixtures or a success response.
  let pending = Promise.resolve();
  await page.route(new URL('assets/', base).href + '**', async route => {
    if (route.request().method() !== 'GET') return route.continue();
    const transfer = pending.then(async () => {
      for (let attempt = 0; ; attempt++) {
        try { return await route.fetch({ maxRetries: 0 }); }
        catch (error) {
          // A reset after HTTP headers is reported as an aborted body by
          // Playwright, rather than ECONNRESET. Still require a complete real
          // response within three attempts; never retry an HTTP error status.
          if (attempt >= 2 || !/ECONNRESET|socket hang up|aborted/.test(error.message)) throw error;
        }
      }
    });
    pending = transfer.catch(() => {});
    try {
      const response = await transfer;
      if (!page.isClosed()) await route.fulfill({ response });
    } catch (error) {
      // A page may close while a lazy asset is still queued. Its disposed
      // request context is teardown, not a failed asset on an active page.
      if (!page.isClosed()) throw error;
    }
  });
  assetPages.add(page);
}
function childEnvironment() {
  // Windows environment names are case-insensitive, while Node's env object
  // can contain duplicate spellings (for example WS_PROXY and ws_proxy).
  // Windows rejects that env block when spawning PHP, so keep one spelling.
  const env = {};
  const seen = new Set();
  for (const [key, value] of Object.entries(process.env)) {
    const normalized = key.toLowerCase();
    if (seen.has(normalized)) continue;
    seen.add(normalized);
    env[key] = value;
  }
  return env;
}
const pages = ['index.php','doctors.php','doctor.php?id=1','book.php?doctor=1','register.php','patient/home.php','doctor/home.php','doctor/schedule.php','doctor/visit.php','admin/console.php','admin/outbox.php'];
export const slug = (p) => p.replace(/\.php.*/, '').replace(/[/?=&]+/g, '-').replace(/^-|-$/g, '').replace(/\.php$/, '') || 'home';
export function parseArgs(args) {
  const out={serve:false,label:null,within:null,app:'clinic-base',paths:[]};
  for(let i=0;i<args.length;i++){ if(args[i]==='--serve')out.serve=true; else if(args[i]==='--label')out.label=args[++i]; else if(args[i]==='--app')out.app=args[++i]; else if(args[i]==='--within')out.within=args[++i]; else if(args[i].startsWith('--within='))out.within=args[i].slice(9); else if(args[i].startsWith('--')){} else out.paths.push(args[i]); }
  appBaseFor(out.app);
  if(out.within==='' || (out.within===undefined))throw Error('--within needs a CSS selector, e.g. --within .site-header');
  if(out.paths.some(x=>/^https?:\/\//i.test(x))){if(out.paths.some(x=>/:8000(?:\/|$)/.test(x)))throw Error('Port 8000 is the stale XAMPP copy. Use --serve on 127.0.0.1:8123.'); throw Error('Pass app paths (for example patient/home.php), not URLs.');}
  if(out.serve && process.env.UI_BASE_URL && /:8000(?:\/|$)/.test(process.env.UI_BASE_URL))throw Error('Port 8000 is the stale XAMPP copy; --serve uses 127.0.0.1:8123.');
  out.paths=out.paths.flatMap(p=>p==='all'?pages:[p]); return out;
}
export async function withTestServer(enabled, callback, app='clinic-base') {
  if (!enabled || app !== 'clinic-base' || process.env.UI_BASE_URL)
    throw Error('Mutation tests require their own clinic-base server; UI_BASE_URL is forbidden.');
  const env = verifyTestConfig();
  return withServer(enabled, callback, app, { env, mutation: true });
}
export async function withServer(enabled, callback, app='clinic-base', isolation={}) {
  // The all-page capture/audit commands need the same resolved-DB evidence as
  // mutation regressions when explicitly run against the isolated test copy.
  if (enabled && !isolation.mutation && process.env.CLINIC_DB_NAME === 'ie4727db_test') {
    isolation = { env: verifyTestConfig(), mutation: true };
  }
  const base=appBaseFor(app);
  let child, sessionDir;
  const token = randomUUID();
  try {
    if(enabled){
      // PHP on developer machines often inherits an XAMPP-only session path.
      // Give this isolated server a writable per-run path so real form logins work.
      // Keep the path free of Windows 8.3 aliases: PHP parses `~` in its
      // -d value as INI syntax instead of as a filesystem character.
      sessionDir=await mkdtemp(join(root,'.ui-sessions-'));
      const adminBootstrap=join(sessionDir,'admin-config.php');
      await writeFile(adminBootstrap,`<?php
${isolation.mutation ? `require ${JSON.stringify(testGuard.replaceAll('\\', '/'))};` : ''}
header('X-UI-Run: ${token}');
${isolation.mutation ? "header('X-UI-Database: ' . DB_NAME);" : ''}
if (!is_file(${JSON.stringify(join(root,app,'config.local.php'))})) {
    defined('ADMIN_USER') || define('ADMIN_USER', getenv('UI_ADMIN') ?: 'admin');
    defined('ADMIN_HASH') || define('ADMIN_HASH', password_hash(getenv('UI_PASSWORD') ?: 'Password123', PASSWORD_DEFAULT));
}
`);
      const iniPath = (path) => path.replaceAll('\\', '/');
      child=spawn('php',[...(isolation.mutation ? ['-d','disable_functions=mail'] : []),'-d',`session.save_path=${iniPath(sessionDir)}`,'-d',`auto_prepend_file=${iniPath(adminBootstrap)}`,'-S','127.0.0.1:8123','-t',root],{cwd:root,stdio:['ignore','ignore','pipe'],windowsHide:true,env:isolation.env || childEnvironment()});
      child.stderr.on('data', data => { if (process.env.UI_SERVER_DIAGNOSTICS === '1') process.stderr.write(data); });
      let ready=false;
      for(let n=0;n<80;n++){try{const r=await fetch(base+'index.php');const body=await r.text();if(child.exitCode===null && r.headers.get('X-UI-Run')===token && (!isolation.mutation || r.headers.get('X-UI-Database')==='ie4727db_test') && r.status===200&&!/Fatal error|Failed opening required/.test(body)){ready=true;break;}}catch{} if(child.exitCode!==null)break;await delay(250);}
      if(!ready)throw Error(`PHP server did not serve /${app}/index.php with HTTP 200.`);
    }
    const selectedBase=process.env.UI_BASE_URL || base;
    if(/:8000(?:\/|$)/.test(selectedBase))throw Error('Port 8000 is the stale XAMPP copy; use --serve on 127.0.0.1:8123.');
    if(isolation.mutation) console.log('Verified child PHP database: ie4727db_test; mail disabled');
    return await callback(selectedBase);
  } finally {
    if(child){child.kill();await Promise.race([new Promise(r=>child.once('exit',r)),delay(1500)]);}
    if(sessionDir)await rm(sessionDir,{recursive:true,force:true});
  }
}
export async function login(page, role, base=appBase) {
  await prepareStaticAssets(page, base);
  const user=process.env[`UI_${role.toUpperCase()}`] || ({patient:'alextan',doctor:'drsmith',admin:'admin'})[role];
  const pass=process.env.UI_PASSWORD || 'Password123';
  await page.goto(base+'index.php',{waitUntil:'domcontentloaded'});
  await waitForPageAssets(page);
  await page.locator('input[name="username_or_email"]').fill(user);
  await page.locator('input[name="password"]').fill(pass);
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('form button[type="submit"]').click()]);
  await waitForPageAssets(page);
  const body=await page.locator('body').innerText();
  const expected={patient:'/patient/home.php',doctor:'/doctor/home.php',admin:'/admin/console.php'}[role];
  if(body.match(/Session expired|could not sign you in|form could not be verified/i)||page.url().includes('/actions/login.php')||!page.url().includes(expected))throw Error(`Login failed for ${role} role (${user}); protected pages skipped: ${body.match(/Session expired|could not sign you in|form could not be verified/i)?.[0]||'expected role home did not open'}.`);
}
export async function prepare(page,path,base=appBase) {
  const role=path.startsWith('patient/')?'patient':path.startsWith('doctor/')?'doctor':path.startsWith('admin/')?'admin':null;
  if(role)await login(page,role,base);
  if(path==='doctor/visit.php'){
    let href=null;
    for(let day=0;day<31&&!href;day++){
      const date=new Date();date.setDate(date.getDate()+day);
      await page.goto(base+'doctor/home.php?date='+date.toISOString().slice(0,10),{waitUntil:'networkidle'});
      href=await page.locator('a[href*="visit.php?appt="]').first().getAttribute('href').catch(()=>null);
    }
    if(!href)throw Error('No Future appointment link found on doctor home for doctor/visit.php.');
      path=href.replace(/^.*\/(?:clinic-base|clinic-plus)\//,'');
  }
  return base+path.replace(/^\//,'');
}
export const allPages=pages;
export async function visit(page,path,base=appBase){await prepareStaticAssets(page,base);const url=await prepare(page,path,base);await page.goto(url,{waitUntil:'domcontentloaded'});await waitForPageAssets(page);return url;}
