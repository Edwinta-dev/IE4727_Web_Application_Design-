import { chromium } from 'playwright';

const checks = process.argv[2]?.split(',') ?? [];
const urls = process.argv.slice(3);
if (!checks.length || !urls.length) throw new Error('usage: node tools/ui/audit.mjs palette,contrast,focus <urls>');
const browser = await chromium.launch({ headless: true });
const failures = [];
const rgb = (s) => { const m = s.match(/[\d.]+/g)?.map(Number); return m?.length >= 3 ? m.slice(0, 3) : [0, 0, 0]; };
const hsl = ([r, g, b]) => { r/=255; g/=255; b/=255; const max=Math.max(r,g,b), min=Math.min(r,g,b), d=max-min, l=(max+min)/2; if(!d)return [0,0,l]; const s=d/(1-Math.abs(2*l-1)); let h=0; if(max===r)h=((g-b)/d)%6; else if(max===g)h=(b-r)/d+2; else h=(r-g)/d+4; return [(h*60+360)%360,s,l]; };
const lum = ([r,g,b]) => [r,g,b].map(v=>v/255).map(v=>v<=.03928?v/12.92:((v+.055)/1.055)**2.4).reduce((a,v,i)=>a+v*[.2126,.7152,.0722][i],0);
for (const url of urls) {
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 } }); await page.goto(url, { waitUntil: 'networkidle' });
  if (checks.includes('palette')) { const p=await page.evaluate(()=>['body','header','button,.button,input[type=submit]','a','.accent'].map(s=>{const e=document.querySelector(s);if(!e)return null;const c=getComputedStyle(e);return {background:c.backgroundColor,color:c.color}})); const samples=p.flatMap(x=>x?[x.background,x.color]:[]).map(rgb).map(hsl).filter(x=>x[1]>=.12); const families=new Set(samples.map(x=>Math.round(x[0]/30))); const primary=p[2]?.background ? hsl(rgb(p[2].background))[0] : null; const bluePrimary=primary !== null && primary >= 205 && primary <= 255; if(families.size<2||bluePrimary) failures.push(`${url}: palette`); }
  if (checks.includes('contrast')) { const bad=await page.evaluate(()=>{const e=document.body,c=getComputedStyle(e); const f=x=>x.match(/[\d.]+/g).slice(0,3).map(Number); const L=x=>{x=x.map(v=>v/255).map(v=>v<=.03928?v/12.92:((v+.055)/1.055)**2.4);return .2126*x[0]+.7152*x[1]+.0722*x[2]}; const ratio=(Math.max(L(f(c.color)),L(f(c.backgroundColor)))+.05)/(Math.min(L(f(c.color)),L(f(c.backgroundColor)))+.05); return ratio < (parseFloat(c.fontSize)>=24 ? 3 : 4.5)}); if(bad) failures.push(`${url}: contrast`); }
  if (checks.includes('focus')) { const bad=await page.evaluate(()=>[...document.querySelectorAll('a,button,input,select,textarea,[role=button]')].filter(e=>{const s=getComputedStyle(e);return e.type!=='hidden'&&!e.disabled&&s.display!=='none'&&s.visibility!=='hidden'}).some(e=>{e.blur();const b=getComputedStyle(e);const before=[b.outlineStyle,b.outlineWidth,b.outlineColor,b.boxShadow,b.borderColor];e.focus({focusVisible:true});const f=getComputedStyle(e);const after=[f.outlineStyle,f.outlineWidth,f.outlineColor,f.boxShadow,f.borderColor];return before.every((v,i)=>v===after[i])})); if(bad) failures.push(`${url}: focus`); }
  await page.close();
}
await browser.close(); if(failures.length){ console.error(failures.join('\n')); process.exit(1); } console.log('OK');
