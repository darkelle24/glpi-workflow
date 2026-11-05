const {test,expect}=require('@playwright/test');
test.beforeEach(async({page})=>{page.on('pageerror',e=>{throw e;});});
async function open(page,extra=''){await page.goto('/tests/editor-fixture.php?pdf=1'+extra);await page.getByRole('button',{name:'Document PDF',exact:true}).click();}
async function saved(page){await page.locator('[data-wb-editor]').evaluate(f=>f.addEventListener('submit',e=>e.preventDefault()));await page.getByRole('button',{name:'Enregistrer le brouillon',exact:true}).click();return JSON.parse(await page.locator('[name="definition"]').inputValue());}
test('automatic and free PDF modes retain independent layouts and save field bindings',async({page})=>{
    await open(page);await expect(page.locator('.wb-pdf-zone')).toHaveCount(13);
    await page.getByRole('button',{name:'Composition automatique',exact:true}).click();
    await page.getByLabel('Titre du document',{exact:true}).fill('Ma fiche salarié');
    await expect(page.locator('.wb-auto-sheet-header h3')).toHaveText('Ma fiche salarié');
    await page.getByRole('button',{name:'Ajouter un groupe',exact:true}).click();
    const group=page.locator('.wb-auto-group').first();await group.getByLabel('Titre du groupe',{exact:true}).fill('Identité');
    await group.getByRole('button',{name:'Ajouter une information',exact:true}).click();
    let item=group.locator('.wb-auto-item').first();await item.getByLabel('Libellé de l’information',{exact:true}).fill('Nom du salarié');
    await item.getByLabel('Information du workflow',{exact:true}).selectOption('form.nom');
    item=group.locator('.wb-auto-item').first();await item.getByLabel('Largeur de l’information',{exact:true}).selectOption('12');
    await page.getByRole('button',{name:'Placement libre',exact:true}).click();await expect(page.locator('.wb-pdf-zone')).toHaveCount(13);
    await page.getByRole('button',{name:'Composition automatique',exact:true}).click();await expect(page.getByLabel('Titre du document',{exact:true})).toHaveValue('Ma fiche salarié');
    const d=await saved(page);expect(d.pdf.mode).toBe('automatic');expect(d.pdf.pages).toHaveLength(2);expect(d.pdf.automatic.groups[0]).toMatchObject({title:'Identité',items:[{label:'Nom du salarié',source:'form.nom',width:12}]});
});
test('apply a base model, reorder and duplicate groups, customize appearance and preview on mobile',async({page})=>{
    page.on('dialog',dialog=>dialog.accept());await open(page);await page.getByRole('button',{name:'Composition automatique',exact:true}).click();
    await page.getByLabel('Modèle de base',{exact:true}).selectOption('builtin_employee');await page.getByRole('button',{name:'Appliquer ce modèle',exact:true}).click();
    await expect(page.locator('.wb-auto-group')).toHaveCount(6);
    let first=page.locator('.wb-auto-group').first();await first.locator('summary').first().click();
    await first.getByRole('button',{name:'Déplacer vers le bas',exact:true}).first().click();
    await expect(page.locator('.wb-auto-group').first().locator('summary').first()).toContainText('Connexion de session');
    first=page.locator('.wb-auto-group').first();if(!await first.evaluate(n=>n.open))await first.locator('summary').first().click();
    await first.getByRole('button',{name:'Dupliquer',exact:true}).first().click();await expect(page.locator('.wb-auto-group')).toHaveCount(7);
    await page.locator('.wb-auto-settings>details>summary').click();await page.getByLabel('Palette',{exact:true}).selectOption('blue');await page.getByLabel('Police du document',{exact:true}).selectOption('dejavuserif');
    await page.getByLabel('Taille du texte (pt)',{exact:true}).fill('11.5');await page.getByLabel('Numéroter les groupes',{exact:true}).uncheck();
    await page.setViewportSize({width:390,height:844});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    const d=await saved(page);expect(d.pdf.automatic).toMatchObject({font:'dejavuserif',font_size:11.5,accent:'#206bc4',numbered:false});expect(d.pdf.automatic.groups).toHaveLength(7);
});
test('admin base form stores header groups logo and prefilled text separately from workflow data',async({page})=>{
    page.on('dialog',d=>d.accept());await page.goto('/tests/pdf-preset-fixture.php');
    await page.getByLabel('Modèle de base',{exact:true}).selectOption('builtin_employee');await page.getByRole('button',{name:'Appliquer ce modèle',exact:true}).click();
    await page.getByLabel('Titre du document',{exact:true}).fill('Accueil personnalisé');
    await page.getByLabel('Pied de page',{exact:true}).fill('Document interne — Service informatique');
    const group=page.locator('.wb-auto-group').first();await group.locator('summary').first().click();
    const item=group.locator('.wb-auto-item').nth(1);await item.getByLabel('Texte prérempli',{exact:true}).fill('Administration');
    const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4//8/AAX+Av5Y8msOAAAAAElFTkSuQmCC','base64');
    await page.getByLabel('Logo (PNG/JPEG)',{exact:true}).setInputFiles({name:'logo.png',mimeType:'image/png',buffer:png});await expect(page.locator('.wb-auto-sheet-header img')).toBeVisible();
    await page.locator('[data-wb-pdf-preset]').evaluate(f=>f.addEventListener('submit',e=>e.preventDefault()));await page.getByRole('button',{name:'Enregistrer le modèle de base',exact:true}).click();
    const auto=JSON.parse(await page.locator('[name="automatic"]').inputValue());expect(auto).toMatchObject({title:'Accueil personnalisé',footer:'Document interne — Service informatique'});expect(auto.logo).toMatch(/^data:image\/png;base64,/);expect(auto.groups[0].items[1]).toMatchObject({text:'Administration',source:''});
});
test('automatic PDF preview uses the current layout and handles refusals',async({page})=>{
    await page.route('**/pdf-preview-fixture.php',route=>route.fulfill({status:403,body:'Champ à associer.'}));await open(page);await page.getByRole('button',{name:'Composition automatique',exact:true}).click();
    await page.getByRole('button',{name:'Télécharger un aperçu PDF',exact:true}).click();await expect(page.getByText('Champ à associer.',{exact:true})).toBeVisible();
});
test('read only automatic configuration disables group mutations and mode selection',async({page})=>{
    await open(page,'&auto=1&readonly=1');await expect(page.getByRole('button',{name:'Placement libre',exact:true})).toBeDisabled();await expect(page.getByRole('button',{name:'Ajouter un groupe',exact:true})).toBeDisabled();
    const first=page.locator('.wb-auto-group').first();await first.locator('summary').first().click();await expect(first.getByLabel('Titre du groupe',{exact:true})).toBeDisabled();
});