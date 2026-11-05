'use strict';
// Actual plugin components with fictional answers, served by tests/pdf-guide-demo.php.
const {chromium}=require('playwright');
const fs=require('node:fs');const path=require('node:path');const {execFileSync}=require('node:child_process');
const base=process.env.WB_PDF_GUIDE_BASE_URL||'http://127.0.0.1:8764';
if(process.env.WORKFLOWBUILDER_ALLOW_PDF_GUIDE_DEMO!=='1'||!['localhost','127.0.0.1'].includes(new URL(base).hostname))throw new Error('Enable the local PDF guide demonstration explicitly.');
const destination=path.resolve(__dirname,'../public/images/guide');const scratch=path.resolve(__dirname,'../build/pdf-guide');fs.mkdirSync(scratch,{recursive:true});
(async()=>{
    const browser=await chromium.launch({headless:true});const page=await browser.newPage({viewport:{width:1600,height:1100},deviceScaleFactor:1,locale:'fr-FR',colorScheme:'light'});
    page.on('dialog',d=>d.accept());page.on('pageerror',e=>{throw e;});
    async function snap(name,locator){await page.evaluate(()=>document.fonts.ready);await locator.screenshot({path:path.join(destination,name),animations:'disabled'});console.log('Captured '+name);}
    try{
        await page.goto(base+'/tests/editor-fixture.php?pdf=1');await page.getByRole('button',{name:'Document PDF',exact:true}).click();
        await snap('pdf-01-modes.png',page.locator('.wb-pdf-modes'));
        await page.goto(base+'/tests/editor-fixture.php?auto_example=1');await page.getByRole('button',{name:'Document PDF',exact:true}).click();
        await snap('pdf-02-automatic.png',page.locator('.wb-auto-layout'));
        const group=page.locator('.wb-auto-group').first();await group.locator('summary').first().click();
        await snap('pdf-03-field-binding.png',group);
        await page.goto(base+'/tests/pdf-preset-fixture.php');await page.getByLabel('Modèle de base',{exact:true}).selectOption('builtin_employee');await page.getByRole('button',{name:'Appliquer ce modèle',exact:true}).click();
        await snap('pdf-04-administration.png',page.locator('main'));
        await page.goto(base+'/tests/editor-fixture.php?pdf=1');await page.getByRole('button',{name:'Document PDF',exact:true}).click();await page.getByRole('button',{name:'Zone 1',exact:true}).click();
        await snap('pdf-05-free-placement.png',page.locator('.wb-pdf-workspace'));
        await page.goto(base+'/tests/pdf-guide-demo.php');await page.locator('[data-wb-ticket-link]').waitFor();
        const bounds=await page.locator('.wb-instance').boundingBox();await page.screenshot({path:path.join(destination,'pdf-06-download.png'),clip:{x:bounds.x,y:bounds.y,width:bounds.width,height:Math.min(bounds.height,590)}});
        console.log('Captured pdf-06-download.png');
        for(const [mode,name] of [['automatic','pdf-07-result-automatic.png'],['free','pdf-08-result-free.png']]){
            const response=await page.request.get(base+'/tests/pdf-guide-demo.php?format=pdf&mode='+mode);
            if(!response.ok()||!response.headers()['content-type']?.includes('application/pdf'))throw new Error(await response.text());
            const file=path.join(scratch,mode+'.pdf');fs.writeFileSync(file,await response.body());
            execFileSync(process.env.WB_GHOSTSCRIPT_PATH||'gs',['-q','-dBATCH','-dNOPAUSE','-dFirstPage=1','-dLastPage=1','-sDEVICE=png16m','-r110','-sOutputFile='+path.join(destination,name),file],{windowsHide:true});console.log('Rendered '+name);
        }
    }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});