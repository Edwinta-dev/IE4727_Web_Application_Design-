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
export async function withServer(enabled, callback, app='clinic-base') {
  const base=appBaseFor(app);
  let child, sessionDir;
  try {
    if(enabled){
      // PHP on developer machines often inherits an XAMPP-only session path.
      // Give this isolated server a writable per-run path so real form logins work.
      // Keep the path free of Windows 8.3 aliases: PHP parses `~` in its
      // -d value as INI syntax instead of as a filesystem character.
      sessionDir=await mkdtemp(join(root,'.ui-sessions-'));
      const adminBootstrap=join(sessionDir,'admin-config.php');
      await writeFile(adminBootstrap,`<?php
if (!is_file(${JSON.stringify(join(root,app,'config.local.php'))})) {
    defined('ADMIN_USER') || define('ADMIN_USER', getenv('UI_ADMIN') ?: 'admin');
    defined('ADMIN_HASH') || define('ADMIN_HASH', password_hash(getenv('UI_PASSWORD') ?: 'Password123', PASSWORD_DEFAULT));
}
`);
      const iniPath = (path) => path.replaceAll('\\', '/');
      child=spawn('php',['-d',`session.save_path=${iniPath(sessionDir)}`,'-d',`auto_prepend_file=${iniPath(adminBootstrap)}`,'-S','127.0.0.1:8123','-t',root],{cwd:root,stdio:'ignore',windowsHide:true,env:childEnvironment()});
      let ready=false;
      for(let n=0;n<80;n++){try{const r=await fetch(base+'index.php');const body=await r.text();if(r.status===200&&!/Fatal error|Failed opening required/.test(body)){ready=true;break;}}catch{} if(child.exitCode!==null)break;await delay(250);}
      if(!ready)throw Error(`PHP server did not serve /${app}/index.php with HTTP 200.`);
    }
    const selectedBase=process.env.UI_BASE_URL || base;
    if(/:8000(?:\/|$)/.test(selectedBase))throw Error('Port 8000 is the stale XAMPP copy; use --serve on 127.0.0.1:8123.');
    return await callback(selectedBase);
  } finally {
    if(child){child.kill();await Promise.race([new Promise(r=>child.once('exit',r)),delay(1500)]);}
    if(sessionDir)await rm(sessionDir,{recursive:true,force:true});
  }
}
export async function login(page, role, base=appBase) {
  const user=process.env[`UI_${role.toUpperCase()}`] || ({patient:'alextan',doctor:'drsmith',admin:'admin'})[role];
  const pass=process.env.UI_PASSWORD || 'Password123';
  await page.goto(base+'index.php',{waitUntil:'networkidle'});
  await page.locator('input[name="username_or_email"]').fill(user);
  await page.locator('input[name="password"]').fill(pass);
  await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),page.locator('form button[type="submit"]').click()]);
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
export async function visit(page,path,base=appBase){const url=await prepare(page,path,base);await page.goto(url,{waitUntil:'networkidle'});return url;}
