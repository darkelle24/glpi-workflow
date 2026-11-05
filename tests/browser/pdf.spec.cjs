const {test,expect}=require('@playwright/test');
test.beforeEach(async({page})=>{page.on('pageerror',e=>{throw e;});});
const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4//8/AAX+Av5Y8msOAAAAAElFTkSuQmCC','base64');
async function open(page,url='/tests/editor-fixture.php?pdf=1'){
    await page.goto(url);await page.getByRole('button',{name:'Document PDF',exact:true}).click();
}
async function saved(page){
    await page.locator('[data-wb-editor]').evaluate(f=>f.addEventListener('submit',e=>e.preventDefault()));
    await page.getByRole('button',{name:'Enregistrer le brouillon',exact:true}).click();
    return JSON.parse(await page.locator('[name="definition"]').inputValue());
}
test('PDF zones retain styles, field mapping, precise coordinates and keyboard and pointer moves',async({page})=>{
    await open(page);await page.getByRole('button',{name:'Zone 1',exact:true}).click();
    await page.getByLabel('Texte de la zone',{exact:true}).fill('Bonjour {{form.prenom}}');
    await page.getByLabel('Police',{exact:true}).selectOption('dejavuserif');
    await page.getByLabel('Taille de police (pt)',{exact:true}).fill('18');
    await page.getByLabel('Couleur du texte',{exact:true}).fill('#ff0000');
    await page.getByLabel('Position X (mm)',{exact:true}).fill('20');
    await page.getByLabel('Position X (mm)',{exact:true}).blur();
    const zone=page.getByRole('button',{name:'Zone 1',exact:true});
    await zone.focus();await zone.press('ArrowRight');
    const r=await zone.boundingBox();await page.mouse.move(r.x+10,r.y+10);await page.mouse.down();await page.mouse.move(r.x+35,r.y+30,{steps:5});await page.mouse.up();
    await expect(page.getByLabel('Position X (mm)',{exact:true})).not.toHaveValue('21');
    await page.getByLabel('Insérer une information',{exact:true}).selectOption('steps.comptes.email');
    const d=await saved(page),b=d.pdf.pages[0].boxes[0];
    expect(b).toMatchObject({text:'Bonjour {{form.prenom}}{{steps.comptes.email}}',font:'dejavuserif',font_size:18,color:'#ff0000'});
    expect(b.x).toBeGreaterThan(21);expect(b.y).toBeGreaterThan(18);
});
test('PDF imports images as portable pages, rejects bad files and preserves page order and boxes',async({page})=>{
    await open(page,'/tests/editor-fixture.php');
    await page.getByLabel('Activer le téléchargement PDF en fin de workflow',{exact:true}).check();
    await page.getByLabel('Importer des images comme pages',{exact:true}).setInputFiles([{name:'fond.png',mimeType:'image/png',buffer:png},{name:'fond2.png',mimeType:'image/png',buffer:png}]);
    await expect(page.locator('.wb-pdf-canvas>img')).toBeVisible();
    await page.getByRole('button',{name:'Ajouter une zone',exact:true}).click();
    await page.getByLabel('Texte de la zone',{exact:true}).fill('Sur image');
    await page.getByLabel('Texte de la zone',{exact:true}).blur();
    await page.getByRole('button',{name:'Page précédente ↑',exact:true}).click();
    await page.getByLabel('Importer des images comme pages',{exact:true}).setInputFiles({name:'bad.svg',mimeType:'image/svg+xml',buffer:Buffer.from('<svg/>')});
    await expect(page.getByRole('status')).toContainText('PNG/JPEG');
    const d=await saved(page);expect(d.pdf.pages).toHaveLength(2);expect(d.pdf.pages[0].boxes[0].text).toBe('Sur image');expect(d.pdf.pages[0].background).toMatch(/^data:image\/png;base64,/);
});
test('PDF duplicates deletes and bounds zones when changing orientation; mobile layout fits',async({page})=>{
    await open(page);await page.getByRole('button',{name:'Ajouter une page blanche',exact:true}).click();
    await page.getByRole('button',{name:'Ajouter une zone',exact:true}).click();
    await page.getByLabel('Position Y (mm)',{exact:true}).fill('280');await page.getByLabel('Position Y (mm)',{exact:true}).blur();
    await page.getByLabel('Orientation de la page',{exact:true}).selectOption('landscape');
    expect(Number(await page.getByLabel('Position Y (mm)',{exact:true}).inputValue())).toBeLessThanOrEqual(195);
    await page.getByRole('button',{name:'Dupliquer la zone',exact:true}).click();await expect(page.locator('.wb-pdf-zone')).toHaveCount(2);
    await page.getByRole('button',{name:'Supprimer la zone',exact:true}).click();await expect(page.locator('.wb-pdf-zone')).toHaveCount(1);
    await page.setViewportSize({width:390,height:844});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    const d=await saved(page);expect(d.pdf.pages).toHaveLength(3);expect(d.pdf.pages[2].orientation).toBe('landscape');
});
test('PDF preview posts current layout and CSRF, downloads a blob and reports server errors',async({page})=>{
    let data;
    await page.route('**/pdf-preview-fixture.php',async route=>{data=route.request().postData();await route.fulfill({contentType:'application/pdf',body:'%PDF-test'});});
    await open(page);
    const download=page.waitForEvent('download');await page.getByRole('button',{name:'Télécharger un aperçu PDF',exact:true}).click();await download;
    expect(data).toContain('name="_wb_token"');expect(data).toContain('fiche-connexion-salarie');
    await page.route('**/pdf-preview-fixture.php',route=>route.fulfill({status:403,body:'Accès refusé.'}));
    await page.getByRole('button',{name:'Télécharger un aperçu PDF',exact:true}).click();await expect(page.getByRole('status')).toContainText('Accès refusé.');
});
test('PDF read only view cannot mutate layout',async({page})=>{
    await open(page,'/tests/editor-fixture.php?pdf=1&readonly=1');
    await expect(page.getByRole('button',{name:'Ajouter une page blanche',exact:true})).toBeDisabled();
    await page.getByRole('button',{name:'Zone 1',exact:true}).click();
    await expect(page.getByLabel('Texte de la zone',{exact:true})).toBeDisabled();
    await expect(page.getByLabel('Position X (mm)',{exact:true})).toHaveValue('18');
});