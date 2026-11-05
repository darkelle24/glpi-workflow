const {test,expect}=require('@playwright/test');
const fs=require('node:fs');const path=require('node:path');
test.skip(!process.env.WB_PDF_GUIDE_VENDOR,'Set isolated CommonMark/HttpFoundation dependencies for the guide controller demo.');
test('user guide links to the illustrated PDF tutorial with working images and identical Markdown',async({page,request})=>{
    await page.goto('/plugins/workflowbuilder/front/guide.php');
    await page.locator('.wb-guide').getByRole('link',{name:'tutoriel PDF détaillé et illustré',exact:true}).click();
    await expect(page).toHaveURL(/guide=pdf/);await expect(page.locator('.wb-guide h1')).toContainText('Tutoriel PDF');
    await expect(page.locator('.wb-guide img')).toHaveCount(8);
    for(const img of await page.locator('.wb-guide img').all()){await img.scrollIntoViewIfNeeded();expect(await img.evaluate(async i=>{await i.decode();return i.naturalWidth>=600;})).toBe(true);}
    const md=await request.get('/plugins/workflowbuilder/front/guide.php?guide=pdf&format=md');expect(md.ok()).toBe(true);expect(md.headers()['content-type']).toContain('text/markdown');
    expect(await md.body()).toEqual(fs.readFileSync(path.join(process.env.WB_PDF_GUIDE_REPO||process.cwd(),'docs/pdf-guide.md')));
    await page.setViewportSize({width:390,height:844});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
});
test('both JSON examples download from tutorial links and local file traversal is refused',async({page,request})=>{
    await page.goto('/plugins/workflowbuilder/front/guide.php?guide=pdf');
    const link=page.locator('.wb-guide').getByRole('link',{name:'Fiche salarié — placement libre',exact:true});
    const download=page.waitForEvent('download');await link.click();expect((await download).suggestedFilename()).toBe('fiche-connexion-salarie.json');
    for(const [name,mode]of[['fiche-connexion-automatique','automatic'],['fiche-connexion-salarie','free']]){const r=await request.get('/plugins/workflowbuilder/front/example.php?name='+name);expect(r.ok()).toBe(true);expect(r.headers()['content-type']).toContain('application/json');expect((await r.json()).pdf.mode).toBe(mode);}
    for(const url of ['/plugins/workflowbuilder/front/guide.php?guide=../../AGENTS','/plugins/workflowbuilder/front/example.php?name=../../AGENTS','/plugins/workflowbuilder/front/guide.php?guide=pdf&no_rights=1','/plugins/workflowbuilder/front/example.php?name=fiche-connexion-salarie&no_rights=1'])expect((await request.get(url)).status()).toBe(403);
});
test('launched workflow has a prominent direct native ticket button and preserves its Workflow tab link',async({page})=>{
    await page.goto('/tests/pdf-guide-demo.php');
    await expect(page.getByRole('link',{name:'Ouvrir le ticket #1',exact:true})).toHaveAttribute('href','/front/ticket.form.php?id=1');
    await expect(page.getByRole('link',{name:'Ouvrir dans le ticket',exact:true})).toHaveAttribute('href',/forcetab=PluginWorkflowbuilderTicket/);
    await expect(page.locator('nav.wb-toolbar').getByRole('link',{name:'Tutoriel PDF',exact:true})).toHaveAttribute('href','/plugins/workflowbuilder/front/guide.php?guide=pdf');
    await expect(page.locator('nav.wb-toolbar').getByRole('link',{name:'Administration PDF',exact:true})).toBeVisible();
    await page.route('**/front/ticket.form.php?*',route=>route.fulfill({contentType:'text/html',body:'<h1>Ticket #1</h1>'}));
    await page.getByRole('link',{name:'Ouvrir le ticket #1',exact:true}).click();await expect(page.getByRole('heading',{name:'Ticket #1',exact:true})).toBeVisible();
});