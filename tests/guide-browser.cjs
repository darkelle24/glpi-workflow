'use strict';
const {chromium}=require('playwright');
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const base=process.env.WB_GUIDE_BASE_URL||'http://127.0.0.1:8766';
if(process.env.WORKFLOWBUILDER_ALLOW_GUIDE_DEMO!=='1'||!['localhost','127.0.0.1'].includes(new URL(base).hostname))throw new Error('Dedicated local guide demo required');
(async()=>{
    const browser=await chromium.launch({headless:true});
    const context=await browser.newContext({viewport:{width:1440,height:1000},locale:'fr-FR',colorScheme:'light'});
    const page=await context.newPage();
    try{
        const anonymous=await context.request.get(base+'/plugins/workflowbuilder/front/guide.php?format=md',{maxRedirects:0});
        assert.notEqual(anonymous.headers()['content-type']?.split(';')[0],'text/markdown');
        console.log('PASS anonymous requests cannot download the guide');
        await page.goto(base+'/index.php?noAUTO=1');
        await page.locator('input[type="text"]').first().fill('glpi');await page.locator('input[type="password"]').fill('glpi');
        await page.locator('button[type="submit"]').first().click();
        await page.goto(base+'/plugins/workflowbuilder/front/guide.php');
        await page.locator('.wb-guide h2').first().waitFor();
        assert.equal(await page.locator('.wb-guide img').count(),10);
        for(const img of await page.locator('.wb-guide img').all()){
            const src=await img.getAttribute('src');const response=await context.request.get(new URL(src,base).href);
            assert.equal(response.status(),200,src);assert.match(response.headers()['content-type'],/^image\/png/);
            await img.scrollIntoViewIfNeeded();
            assert.equal(await img.evaluate(async image=>{await image.decode();return image.naturalWidth>=600;}),true,src);
        }
        console.log('PASS the GLPI guide serves all ten screenshot URLs');
        const raw=await context.request.get(base+'/plugins/workflowbuilder/front/guide.php?format=md');
        assert.equal(raw.status(),200);assert.match(raw.headers()['content-type'],/^text\/markdown/);
        assert.deepEqual(await raw.body(),fs.readFileSync(path.join(__dirname,'../docs/user-guide.md')));
        console.log('PASS downloaded Markdown is byte-for-byte identical to the repository guide');
        await page.locator('.wb-guide a').filter({hasText:'Prendre en charge une étape'}).first().click();
        assert.match(decodeURIComponent(new URL(page.url()).hash),/prendre-en-charge-une-étape/);
        console.log('PASS French table of contents anchors navigate correctly');
        assert.equal(await page.locator('.wb-guide a.wb-guide-image[target="_blank"]').count(),10);
        const unsupported=await context.request.get(base+'/plugins/workflowbuilder/front/guide.php?format=../../config/config_db.php');
        assert.equal(unsupported.status(),400);
        console.log('PASS unsupported guide formats cannot read arbitrary files');
        await page.goto(base+'/plugins/workflowbuilder/front/index.php');
        assert.equal(await page.getByRole('link',{name:'Guide utilisateur',exact:true}).count(),1);
        await page.goto(base+'/front/ticket.form.php?id=1&forcetab=PluginWorkflowbuilderTicket%241');
        await page.locator('.wb-instance').waitFor();
        assert.equal(await page.locator('section.wb').getByRole('link',{name:'Guide utilisateur',exact:true}).count(),1);
        console.log('PASS guide links are available in the plugin and ticket tab');
        await page.goto(base+'/plugins/workflowbuilder/front/guide.php');
        await page.setViewportSize({width:390,height:844});
        await page.evaluate(()=>document.fonts.ready);
        await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth,{},{timeout:5000}).catch(async()=>{
            console.error(await page.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,overflow:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).slice(0,15).map(e=>({tag:e.tagName,cls:e.className,right:e.getBoundingClientRect().right,text:e.textContent.slice(0,60)}))})));
            throw new Error('Guide overflows the mobile viewport');
        });
        console.log('PASS guide fits a 390px screen without horizontal page overflow');
        await page.setViewportSize({width:1440,height:1000});
        await page.evaluate(()=>document.fonts.ready);
        const destination=process.env.WB_GUIDE_PREVIEW_PATH;
        if(destination){
            await page.locator('.wb-guide img').evaluateAll(async images=>{await Promise.all(images.map(image=>{image.loading='eager';return image.decode();}));});
            await page.locator('main.wb').screenshot({path:destination,animations:'disabled',style:'header{visibility:hidden!important}'});
        }
    }finally{await context.close();await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
