'use strict';
// Reproduce the guide images on the dedicated, fictional dataset from tests/guide-demo.php.
const {chromium} = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.WB_GUIDE_BASE_URL || 'http://127.0.0.1:8766';
const url = new URL(base);
if(process.env.WORKFLOWBUILDER_ALLOW_GUIDE_DEMO !== '1' || !['localhost','127.0.0.1'].includes(url.hostname)) {
    throw new Error('Use the explicitly enabled local documentation demo only.');
}
const destination = path.resolve(__dirname,'../public/images/guide');
fs.mkdirSync(destination,{recursive:true});
(async()=>{
    const browser=await chromium.launch({headless:true});
    const context=await browser.newContext({viewport:{width:1440,height:1000},deviceScaleFactor:1,locale:'fr-FR',colorScheme:'light',reducedMotion:'reduce'});
    const page=await context.newPage();
    page.setDefaultTimeout(20000);
    const go=async(target)=>{await page.goto(base+target);await page.locator('.wb').waitFor();await page.evaluate(()=>document.fonts.ready);};
    const snap=async(name,locator)=>{await page.evaluate(()=>document.fonts.ready);await locator.screenshot({path:path.join(destination,name),animations:'disabled',caret:'hide',style:'header { visibility:hidden !important; }'});console.log('Captured '+name);};
    try {
        await page.goto(base+'/index.php?noAUTO=1');
        await page.locator('input[type="text"]').first().fill('glpi');
        await page.locator('input[type="password"]').fill('glpi');
        await page.locator('button[type="submit"]').first().click();
        await go('/plugins/workflowbuilder/front/index.php');
        await snap('01-workflows.png',page.locator('main.wb'));
        await go('/plugins/workflowbuilder/front/launch.php?template=1');
        await page.locator('[name="values[beneficiaire]"]').fill('Compte de démonstration');
        await page.locator('[name="values[date_souhaitee]"]').fill('2026-11-10');
        await page.locator('[name="values[reinstaller]"]').selectOption('0');
        await page.locator('[name="values[logiciels][]"]').selectOption(['Suite bureautique','Navigateur']);
        await page.locator('[name="values[commentaires]"]').fill('Prévoir une remise au service administratif.');
        await snap('02-lancement.png',page.locator('main.wb'));
        await page.goto(base+'/front/ticket.form.php?id=1&forcetab=PluginWorkflowbuilderTicket%241');
        await page.locator('.wb-instance').waitFor();
        await snap('03-suivi-ticket.png',page.locator('section.wb'));
        await snap('04-prise-en-charge.png',page.locator('.wb-step').nth(3));
        await go('/plugins/workflowbuilder/front/template.form.php?id=1');
        await page.getByRole('button',{name:'Formulaire',exact:true}).click();
        await page.locator('[data-field-id="beneficiaire"] > summary').click();
        await snap('05-formulaire.png',page.locator('[data-wb-editor]'));
        await page.getByRole('button',{name:'Étapes',exact:true}).click();
        await snap('06-etapes.png',page.locator('[data-wb-editor]'));
        await page.getByRole('button',{name:'Conditions',exact:true}).click();
        await snap('07-conditions.png',page.locator('[data-wb-editor]'));
        await snap('08-publication.png',page.locator('main.wb > section.card'));
        await go('/plugins/workflowbuilder/front/index.php');
        const importer=page.locator('main.wb > details').first();
        await importer.locator('summary').click();
        await importer.locator('[name="example"]').selectOption('preparation-ordinateur');
        await snap('09-import.png',importer);
        await go('/plugins/workflowbuilder/front/template.form.php?id=1');
        await page.getByRole('button',{name:'Prévisualisation',exact:true}).click();
        await page.getByText('Progression initiale : 0 %',{exact:true}).waitFor();
        const pane=page.locator('.wb-pane:not([hidden])');
        await pane.locator('[name="values[beneficiaire]"]').fill('Compte de démonstration');
        await pane.locator('[name="values[date_souhaitee]"]').fill('2026-11-10');
        await pane.locator('[name="values[reinstaller]"]').selectOption('0');
        await pane.locator('[name="values[logiciels][]"]').selectOption(['Suite bureautique','Navigateur']);
        const updated=page.waitForResponse(r=>r.url().includes('/preview.php')&&r.request().method()==='POST');
        await page.getByRole('button',{name:'Tester les valeurs et conditions',exact:true}).click();
        await updated;
        await pane.locator('[name="values[beneficiaire]"]').waitFor();
        await snap('10-previsualisation.png',pane);
        console.log('10 real GLPI screenshots saved to '+destination);
    } finally {await context.close();await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
