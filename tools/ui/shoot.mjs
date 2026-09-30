import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { parseArgs, withServer, visit, slug, appBaseFor } from './lib.mjs';
const args=parseArgs(process.argv.slice(2));
if(!args.label || !args.paths.length)throw Error('usage: node tools/ui/shoot.mjs [--serve] --label <label> <pages|all>');
await withServer(args.serve,async(base)=>{
 const browser=await chromium.launch({headless:true});
 try {for(const p of args.paths){const page=await browser.newPage();try{await visit(page,p,process.env.UI_BASE_URL||base||appBaseFor(args.app));for(const width of [1280,390]){await page.setViewportSize({width,height:width===1280?800:844});await page.screenshot({path:resolve('UIPROBLEMS/after',args.label,`${slug(p)}-${width}.png`),fullPage:true});console.log(resolve('UIPROBLEMS/after',args.label,`${slug(p)}-${width}.png`));}} finally{await page.close();}}}
 finally{await browser.close();}
},args.app);
