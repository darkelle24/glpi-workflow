const {test,expect} = require('@playwright/test');
test('create fields and steps, preserve IDs, reorder and serialize',async({page})=>{
    await page.goto('/tests/editor-fixture.php');
    await expect(page.locator('.wb-tabs button')).toHaveCount(8);
    await page.getByRole('button',{name:'Formulaire',exact:true}).click();
    await page.getByRole('button',{name:'Ajouter un champ',exact:true}).click();
    const field=page.locator('.wb-pane:not([hidden]) > div > details').last();
    const id=await field.locator('input').first().inputValue();
    await field.locator('input').nth(1).fill('Adresse de livraison');
    await field.locator('select').selectOption('email');
    await expect(field).toHaveAttribute('open','');
    await expect(field.locator('input').first()).toHaveValue(id);
    await page.getByRole('button',{name:'Étapes',exact:true}).click();
    await page.getByRole('button',{name:'Ajouter une étape',exact:true}).click();
    const step=page.locator('.wb-step-editor').last();
    await expect(step).toHaveAttribute('open','');
    await step.locator('input').nth(1).fill('Contrôle de livraison');
    await step.locator('input').nth(1).blur();
    await step.getByRole('button',{name:'Déplacer vers le haut',exact:true}).first().click();
    await page.locator('[data-wb-editor]').evaluate(form=>form.addEventListener('submit',e=>e.preventDefault()));
    await page.getByRole('button',{name:'Enregistrer le brouillon',exact:true}).click();
    const json=JSON.parse(await page.locator('[name="definition"]').inputValue());
    expect(json.fields.at(-1)).toMatchObject({id:id,label:'Adresse de livraison',type:'email'});
    expect(json.steps[8].title).toBe('Contrôle de livraison');
});
test('preview evaluates conditions through PHP without creating a ticket',async({page})=>{
    await page.goto('/tests/editor-fixture.php');
    await page.getByRole('button',{name:'Prévisualisation',exact:true}).click();
    await expect(page.getByText('Installer le système — Non applicable',{exact:true})).toBeVisible();
    await page.locator('.wb-pane:not([hidden]) select').nth(0).selectOption('1');
    await page.locator('.wb-pane:not([hidden]) select').nth(1).selectOption('1');
    await page.getByRole('button',{name:'Tester les valeurs et conditions',exact:true}).click();
    await expect(page.getByText('Installer le système — En attente',{exact:true})).toBeVisible();
    await expect(page.getByText('Configurer les accès — En attente',{exact:true})).toBeVisible();
    await page.setViewportSize({width:390,height:844});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
});
test('native reference search widget selects and clears a result',async({page})=>{
    await page.goto('/tests/editor-fixture.php');
    await page.getByRole('button',{name:'Prévisualisation',exact:true}).click();
    await page.getByRole('searchbox',{name:'Rechercher Utilisateur destinataire'}).fill('test');
    await page.getByRole('button',{name:'Élément de test (#7)',exact:true}).click();
    await expect(page.locator('[name="values[demandeur]"]')).toHaveValue('7');
    await page.getByRole('button',{name:'Effacer',exact:true}).click();
    await expect(page.locator('[name="values[demandeur]"]')).toHaveValue('');
});

test('knowledge browser filters, pagination, association and removal', async({page}) => {
    const item = {id:17,label:'Procédure réseau',excerpt:'Contrôler le câble et le port.',url:'/front/knowbaseitem.form.php?id=17',faq:false,modified:'2026-11-04'};
    const requests = [];
    await page.route('**/lookup-fixture.php?**', async route => {
        const params = new URL(route.request().url()).searchParams;
        if (params.get('type') !== 'knowledge') return route.fulfill({json:{items:[],next:0}});
        requests.push(Object.fromEntries(params));
        const next = params.get('after') === '17';
        await route.fulfill({json:{items:next?[{...item,id:16,label:'Autre procédure'}]:[item],next:next?0:17}});
    });
    await page.goto('/tests/editor-fixture.php');
    await page.getByRole('button',{name:'Étapes',exact:true}).click();
    const step = page.locator('.wb-step-editor').first();
    await step.locator('summary').first().click();
    await expect(step.getByLabel('Instructions',{exact:true})).toHaveCount(0);
    await step.getByRole('button',{name:'Choisir une fiche',exact:true}).click();
    await expect(step.getByText('Contrôler le câble et le port.',{exact:true})).toBeVisible();
    await step.getByLabel('Rechercher des fiches',{exact:true}).fill('réseau');
    await step.getByLabel('Rechercher dans',{exact:true}).selectOption('title');
    await step.getByLabel('Type de fiche',{exact:true}).selectOption('internal');
    await step.getByLabel('Trier par',{exact:true}).selectOption('oldest');
    await step.getByLabel('Modifiées depuis le',{exact:true}).fill('2026-10-01');
    await step.getByLabel('Mes fiches uniquement',{exact:true}).check();
    await expect.poll(() => requests.at(-1)?.mine).toBe('1');
    expect(requests.at(-1)).toMatchObject({q:'réseau',scope:'title',kind:'internal',sort:'oldest',since:'2026-10-01',mine:'1'});
    await step.getByRole('button',{name:'Associer à l’étape',exact:true}).click();
    await expect(step.locator('.wb-knowledge-selected a')).toHaveText('Procédure réseau');
    await expect(step.getByRole('button',{name:'Déjà associée',exact:true})).toBeDisabled();
    await step.getByRole('button',{name:'Afficher la suite',exact:true}).click();
    await expect(step.getByText('Autre procédure',{exact:true})).toBeVisible();
    await expect(step.locator('.wb-knowledge-results article')).toHaveCount(2);
    await step.getByRole('button',{name:'Retirer la fiche #17',exact:true}).click();
    await expect(step.locator('.wb-knowledge-selected')).toHaveText('Aucune fiche associée.');
    await step.getByRole('button',{name:'Réinitialiser les filtres',exact:true}).click();
    await expect.poll(() => requests.at(-1)?.q).toBe('');
    await page.setViewportSize({width:390,height:844});
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

test('knowledge creation preserves legacy text and serializes the new association', async({page}) => {
    let posted;
    await page.route('**/knowledge-fixture.php', async route => {
        posted = route.request().postData();
        await route.fulfill({status:201,json:{item:{id:42,label:'Ma nouvelle procédure',url:'/front/knowbaseitem.form.php?id=42'}}});
    });
    await page.goto('/tests/editor-fixture.php');
    await page.getByRole('button',{name:'Étapes',exact:true}).click();
    const step = page.locator('.wb-step-editor').first();
    await step.locator('summary').first().click();
    await step.getByRole('button',{name:'Créer une fiche',exact:true}).click();
    await step.getByLabel('Titre de la nouvelle fiche',{exact:true}).fill('Ma nouvelle procédure');
    await step.getByRole('button',{name:'Créer et associer la fiche',exact:true}).click();
    await expect(step.getByRole('link',{name:'Compléter la fiche dans GLPI',exact:true})).toHaveAttribute('href','/front/knowbaseitem.form.php?id=42');
    expect(posted).toContain('Adapter les instructions');
    expect(posted).toContain('name="_wb_token"');
    await page.locator('[data-wb-editor]').evaluate(form => form.addEventListener('submit',e => e.preventDefault()));
    await page.getByRole('button',{name:'Enregistrer le brouillon',exact:true}).click();
    const model = JSON.parse(await page.locator('[name="definition"]').inputValue());
    expect(model.steps[0].knowbaseitems_id).toEqual([42]);
    expect(model.steps[0].instructions).toBe('');
    await page.getByRole('button',{name:'Général',exact:true}).click();
    await page.route('**/lookup-fixture.php?**',route => route.fulfill({json:{items:[{id:42,label:'Ma nouvelle procédure',url:'/front/knowbaseitem.form.php?id=42'}],next:0}}));
    await page.getByRole('button',{name:'Étapes',exact:true}).click();
    await page.locator('.wb-step-editor').first().locator('summary').first().click();
    await expect(page.locator('.wb-step-editor').first().locator('.wb-knowledge-selected a')).toHaveText('Ma nouvelle procédure');
});

test('knowledge permission errors preserve the existing draft and legacy instructions', async({page}) => {
    await page.route('**/knowledge-fixture.php',route => route.fulfill({status:403,json:{error:'Droit GLPI insuffisant.'}}));
    await page.goto('/tests/editor-fixture.php');
    await page.getByRole('button',{name:'Étapes',exact:true}).click();
    const step = page.locator('.wb-step-editor').first();
    await step.locator('summary').first().click();
    await step.getByRole('button',{name:'Créer une fiche',exact:true}).click();
    await step.getByRole('button',{name:'Créer et associer la fiche',exact:true}).click();
    await expect(step.getByText('Droit GLPI insuffisant.',{exact:true})).toBeVisible();
    await expect(step.getByRole('button',{name:'Créer et associer la fiche',exact:true})).toBeEnabled();
    await page.locator('[data-wb-editor]').evaluate(form => form.addEventListener('submit',e => e.preventDefault()));
    await page.getByRole('button',{name:'Enregistrer le brouillon',exact:true}).click();
    const model = JSON.parse(await page.locator('[name="definition"]').inputValue());
    expect(model.steps[0].knowbaseitems_id).toEqual([]);
    expect(model.steps[0].instructions).toContain('Adapter les instructions');
});

test('knowledge ignores stale searches when filters change', async({page}) => {
    await page.route('**/lookup-fixture.php?**', async route => {
        const q = new URL(route.request().url()).searchParams.get('q');
        if (q === 'ancien') await new Promise(resolve => setTimeout(resolve,800));
        await route.fulfill({json:{items:[{id:8,label:q==='ancien'?'Résultat périmé':'Résultat actuel',url:'/front/knowbaseitem.form.php?id=8'}],next:0}});
    });
    await page.goto('/tests/editor-fixture.php');
    await page.getByRole('button',{name:'Étapes',exact:true}).click();
    const step = page.locator('.wb-step-editor').first();
    await step.locator('summary').first().click();
    await step.getByRole('button',{name:'Choisir une fiche',exact:true}).click();
    const search = step.getByLabel('Rechercher des fiches',{exact:true});
    const oldRequest = page.waitForRequest(r => r.url().includes('q=ancien'));
    await search.fill('ancien'); await oldRequest;
    await search.fill('nouveau');
    await expect(step.getByText('Résultat actuel',{exact:true})).toBeVisible();
    await page.waitForTimeout(900);
    await expect(step.getByText('Résultat périmé',{exact:true})).toHaveCount(0);
});

test('three automatic message options default to checked and preserve disabled options after duplication', async({page}) => {
    await page.goto('/tests/editor-fixture.php');
    await page.getByRole('button',{name:'Étapes',exact:true}).click();
    await page.getByRole('button',{name:'Ajouter une étape',exact:true}).click();
    let step = page.locator('.wb-step-editor').last();
    for(const label of ['Étape disponible','Étape prise en charge','Étape terminée']) await expect(step.getByLabel(label,{exact:true})).toBeChecked();
    await step.getByLabel('Étape prise en charge',{exact:true}).uncheck();
    await step.getByRole('button',{name:'Dupliquer',exact:true}).first().click();
    step = page.locator('.wb-step-editor').last();
    await step.locator('summary').first().click();
    await expect(step.getByLabel('Étape prise en charge',{exact:true})).not.toBeChecked();
    await page.locator('[data-wb-editor]').evaluate(form=>form.addEventListener('submit',e=>e.preventDefault()));
    await page.getByRole('button',{name:'Enregistrer le brouillon',exact:true}).click();
    const model=JSON.parse(await page.locator('[name="definition"]').inputValue());
    expect(model.steps.at(-1)).toMatchObject({notify_available:true,notify_taken:false,notify_done:true});
});

test('launch team accepts multiple users and groups and submits removable selections', async({page}) => {
    await page.route('**/lookup-fixture.php?**',route=>{
        const type=new URL(route.request().url()).searchParams.get('type');
        return route.fulfill({json:{items:type==='user'?[{id:7,label:'Alice'},{id:8,label:'Bob'}]:[{id:12,label:'Support'}],next:0}});
    });
    await page.goto('/tests/launch-fixture.php');
    const user=page.getByRole('searchbox',{name:'Rechercher Utilisateurs du workflow',exact:true});
    await user.fill('al');await page.getByRole('button',{name:'Alice (#7)',exact:true}).click();
    await user.fill('bo');await page.getByRole('button',{name:'Bob (#8)',exact:true}).click();
    await page.getByRole('searchbox',{name:'Rechercher Groupes du workflow',exact:true}).fill('su');
    await page.getByRole('button',{name:'Support (#12)',exact:true}).click();
    expect(await page.locator('[name="assigned_users[]"]').evaluateAll(nodes=>nodes.map(n=>n.value))).toEqual(['7','8']);
    await expect(page.locator('[name="assigned_groups[]"]')).toHaveValue('12');
    await page.getByRole('button',{name:'Alice ×',exact:true}).click();
    await expect(page.locator('[name="assigned_users[]"]')).toHaveCount(1);
    await expect(page.locator('[name="assigned_users[]"]')).toHaveValue('8');
    await page.setViewportSize({width:390,height:844});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
});

test('ticket creation form selects native parameters and multiple actors for each role', async({page}) => {
    await page.route('**/lookup-fixture.php?**',route => route.fulfill({json:{items:[{id:7,label:'Alice'},{id:8,label:'Bob'}],next:0}}));
    await page.goto('/tests/launch-fixture.php');
    await page.getByLabel('Type',{exact:true}).selectOption('1');
    await page.getByLabel('Sévérité (urgence)',{exact:true}).selectOption('5');
    await page.getByLabel('Impact',{exact:true}).selectOption('4');
    await page.getByLabel('Date de fin souhaitée (échéance de résolution)',{exact:true}).fill('2026-12-20T17:30');
    for (const [role,label] of [['requester','Demandeurs'],['assign','Attribués à'],['observer','Observateurs (suivi)']]) {
        for (const [kind,word] of [['users','utilisateurs'],['groups','groupes']]) {
            const input = page.getByRole('searchbox',{name:'Rechercher '+label+' — '+word,exact:true});
            await input.fill('al');await page.getByRole('button',{name:'Alice (#7)',exact:true}).click();
            await input.fill('bo');await page.getByRole('button',{name:'Bob (#8)',exact:true}).click();
            expect(await page.locator('[name="ticket['+role+'_'+kind+'][]"]').evaluateAll(nodes => nodes.map(n => n.value))).toEqual(['7','8']);
        }
    }
    const data = await page.locator('form').evaluate(form => Object.fromEntries(new FormData(form)));
    expect(data).toMatchObject({'ticket[type]':'1','ticket[urgency]':'5','ticket[impact]':'4','ticket[time_to_resolve]':'2026-12-20T17:30'});
    await page.setViewportSize({width:390,height:844});
    expect(await page.evaluate(() => document.documentElement.scrollWidth<=innerWidth)).toBe(true);
});

test('multiple choice checkboxes drive conditions and submit all or no choices', async({page}) => {
    await page.goto('/tests/launch-fixture.php');
    const field = page.getByRole('group',{name:'Outils nécessaires',exact:true});
    await expect(field.getByLabel('Ordinateur',{exact:true})).toBeChecked();
    await expect(page.getByLabel('Numéro de téléphone',{exact:true})).toBeHidden();
    await field.getByLabel('Téléphone',{exact:true}).check();
    await field.getByLabel('Badge',{exact:true}).check();
    await expect(page.getByLabel('Numéro de téléphone',{exact:true})).toBeVisible();
    expect(await page.locator('form').evaluate(form => new FormData(form).getAll('values[tools][]'))).toEqual(['Ordinateur','Téléphone','Badge']);
    for(const choice of ['Ordinateur','Téléphone','Badge']) await field.getByLabel(choice,{exact:true}).uncheck();
    await expect(page.getByLabel('Numéro de téléphone',{exact:true})).toBeHidden();
    expect(await page.locator('form').evaluate(form => new FormData(form).getAll('values[tools][]'))).toEqual([]);
    expect(await page.locator('form').evaluate(form => new FormData(form).get('values[tools]'))).toBe('');
});

test('final summary defaults checked and keeps its disabled setting when saved', async({page}) => {
    await page.goto('/tests/editor-fixture.php');
    await page.getByRole('button',{name:'Paramètres',exact:true}).click();
    const option=page.getByLabel('Envoyer un récapitulatif complet dans le ticket à la fin du workflow',{exact:true});
    await expect(option).toBeChecked();await option.uncheck();
    await page.locator('[data-wb-editor]').evaluate(form => form.addEventListener('submit',e => e.preventDefault()));
    await page.getByRole('button',{name:'Enregistrer le brouillon',exact:true}).click();
    expect(JSON.parse(await page.locator('[name="definition"]').inputValue()).notify_summary).toBe(false);
});