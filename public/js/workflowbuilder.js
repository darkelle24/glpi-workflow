/* SPDX-License-Identifier: GPL-3.0-or-later */
(function () {
    'use strict';
    const labels = {text:'Texte court',textarea:'Texte long',number:'Nombre',date:'Date',datetime:'Date et heure',checkbox:'Case à cocher',select:'Liste déroulante',radio:'Boutons radio',multiselect:'Choix multiples',boolean:'Oui / Non',email:'Adresse e-mail',url:'URL',user:'Utilisateur GLPI',group:'Groupe GLPI',entity:'Entité GLPI',asset:'Ordinateur GLPI',document:'Document du ticket',section:'Section'};
    const states = {waiting:'En attente',available:'Disponible',progress:'En cours',done:'Terminée',blocked:'Bloquée',na:'Non applicable'};
    const operators = {eq:'Égal à',ne:'Différent de',contains:'Contient',not_contains:'Ne contient pas',gt:'Supérieur à',lt:'Inférieur à',empty:'Est vide',not_empty:'N’est pas vide',true:'Est vrai',false:'Est faux'};
    let counter = 0;
    const uid = function (prefix) { return prefix + '_' + Date.now().toString(36) + '_' + (++counter); };
    const copy = function (data) { return JSON.parse(JSON.stringify(data)); };
    function el(tag, text, className) {
        const node = document.createElement(tag);
        if (text !== undefined && text !== null) node.textContent = text;
        if (className) node.className = className;
        return node;
    }
    function button(text, action, parent) {
        const node = el('button', text, 'btn btn-sm btn-outline-secondary');
        node.type = 'button'; node.addEventListener('click', action);
        if (parent) parent.append(node);
        return node;
    }
    function control(parent, label, value, change, type) {
        const wrap = el('label', null, 'wb-control');
        wrap.append(el('span', label, 'form-label'));
        const node = el(type === 'textarea' ? 'textarea' : 'input', null, 'form-control');
        if (type !== 'textarea') node.type = type || 'text';
        if (type === 'checkbox') { node.checked = !!value; node.className = 'form-check-input'; }
        else node.value = value === null || value === undefined ? '' : value;
        node.addEventListener('change', function () { change(type === 'checkbox' ? node.checked : node.value); });
        wrap.append(node); parent.append(wrap);
        return node;
    }
    function select(parent, label, options, value, change, multiple) {
        const wrap = el('label', null, 'wb-control');
        wrap.append(el('span', label, 'form-label'));
        const node = el('select', null, 'form-select');
        node.multiple = !!multiple; node.setAttribute('aria-label',label);
        Object.entries(options).forEach(function (pair) {
            const opt = el('option', pair[1]); opt.value = pair[0];
            opt.selected = multiple ? (value || []).includes(pair[0]) : String(value) === pair[0];
            node.append(opt);
        });
        node.addEventListener('change', function () { change(multiple ? Array.from(node.selectedOptions, o => o.value) : node.value); });
        wrap.append(node); parent.append(wrap);
        return node;
    }
    function scalar(v) {
        if (v === true || v === 'true') return '1';
        if (v === false || v === 'false') return '0';
        return v === null || v === undefined || Array.isArray(v) ? '' : String(v);
    }
    function evaluate(node, context) {
        if (!node) return true;
        if (node.all) return node.all.every(n => evaluate(n, context));
        if (node.any) return node.any.some(n => evaluate(n, context));
        const a = context[node.field] === undefined ? null : context[node.field];
        const b = node.value === undefined ? null : node.value;
        const empty = a === null || a === '' || (Array.isArray(a) && !a.length);
        const contains = Array.isArray(a) ? a.map(scalar).includes(scalar(b)) : scalar(a).includes(scalar(b));
        const numeric = function (v) { return typeof v !== 'boolean' && v !== null && !Array.isArray(v) && String(v).trim() !== '' && Number.isFinite(Number(v)); };
        switch (node.op) {
        case 'eq': return !Array.isArray(a) && scalar(a) === scalar(b);
        case 'ne': return Array.isArray(a) || scalar(a) !== scalar(b);
        case 'contains': return contains;
        case 'not_contains': return !contains;
        case 'empty': return empty;
        case 'not_empty': return !empty;
        case 'true': return [true,1,'1','true'].includes(a);
        case 'false': return [false,0,'0','false'].includes(a);
        case 'gt': return numeric(a) && numeric(b) && Number(a) > Number(b);
        case 'lt': return numeric(a) && numeric(b) && Number(a) < Number(b);
        default: return false;
        }
    }
    // Exposed for condition parity tests; execution authority remains on the server.
    globalThis.WorkflowBuilderConditions = {evaluate:evaluate};
    if (typeof document === 'undefined') return;

    function readValue(box) {
        const f = JSON.parse(box.dataset.wbField);
        const inputs = Array.from(box.querySelectorAll('input[name^="values["],select[name^="values["],textarea[name^="values["]'));
        if (!inputs.length) return null;
        if (f.type === 'checkbox') return inputs.find(i => i.type === 'checkbox').checked;
        if (f.type === 'radio') return (inputs.find(i => i.checked) || {}).value || null;
        if (f.type === 'multiselect') return Array.from(inputs[0].selectedOptions, o => o.value);
        const value = inputs[0].value;
        if (f.type === 'boolean') return value === '' ? null : value === '1';
        if (f.type === 'number') return value === '' ? null : Number(value);
        return value === '' ? null : value;
    }
    function runtime(form) {
        if (form.dataset.wbRuntimeReady) return;
        form.dataset.wbRuntimeReady = '1';
        const refresh = function () {
            const context = {};
            Object.entries(JSON.parse(form.dataset.formValues || '{}')).forEach(p => { context['form.' + p[0]] = p[1]; });
            form.querySelectorAll('[data-wb-field]').forEach(function (box) {
                const field = JSON.parse(box.dataset.wbField);
                const visible = evaluate(field.condition, context);
                box.hidden = !visible;
                box.querySelectorAll('input,select,textarea,button').forEach(i => { i.disabled = !visible; });
                const value = visible ? readValue(box) : null;
                context['self.' + field.id] = value;
                if (form.hasAttribute('data-initial-form')) context['form.' + field.id] = value;
            });
        };
        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        refresh();
    }
    async function search(endpoint, type, q, after, ticket) {
        const url = new URL(endpoint, location.href);
        url.searchParams.set('type',type); url.searchParams.set('q',q);
        if (after) url.searchParams.set('after',after);
        if (ticket) url.searchParams.set('ticket',ticket);
        const response = await fetch(url, {credentials:'same-origin', headers:{Accept:'application/json'}});
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Recherche impossible.');
        return data;
    }
    function bindSearch(parent, type, choose, ticket) {
        const root = parent.closest('[data-wb-root]') || document.querySelector('[data-wb-root]');
        const input = parent.querySelector('input[type="search"]') || el('input', null, 'form-control');
        input.type = 'search'; input.placeholder = 'Rechercher (2 caractères minimum)…';
        if (!input.parentNode) parent.append(input);
        const results = parent.querySelector('[data-reference-results]') || el('div', null, 'wb-search-results');
        if (!results.parentNode) parent.append(results);
        let timer, serial = 0;
        async function run(after, request) {
            try {
                const data = await search(root.dataset.lookup, type, input.value, after, ticket);
                if (request !== serial) return;
                if (!after) results.replaceChildren();
                results.querySelector('[data-more]')?.remove();
                if (!data.items.length && !after) results.append(el('p', data.message || 'Aucun résultat accessible.'));
                data.items.forEach(function (item) {
                    button(item.label + ' (#' + item.id + ')', function () { choose(item); results.replaceChildren(); input.value = ''; }, results);
                });
                if (data.next) {
                    const more = button('Résultats suivants', function () { run(data.next, request); }, results);
                    more.dataset.more = '1';
                }
            } catch (error) { if (request === serial) results.replaceChildren(el('p', error.message, 'text-danger')); }
        }
        input.addEventListener('input', function () {
            clearTimeout(timer); serial++;
            results.replaceChildren();
            if (input.value.trim().length < 2) return;
            const request = serial;
            timer = setTimeout(function () { run(0, request); }, 250);
        });
    }
    function references(box) {
        if (box.dataset.wbReferenceReady) return;
        box.dataset.wbReferenceReady = '1';
        const hidden = box.querySelector('input[type="hidden"]');
        const label = box.querySelector('[data-reference-label]');
        bindSearch(box,box.dataset.referenceType,function (item) {
            hidden.value = item.id; label.textContent = item.label;
            hidden.dispatchEvent(new Event('change',{bubbles:true}));
        },box.dataset.ticket);
        box.querySelector('[data-clear-reference]').addEventListener('click', function () {
            hidden.value = ''; label.textContent = 'Aucun élément sélectionné';
            hidden.dispatchEvent(new Event('change',{bubbles:true}));
        });
    }
    function picker(parent, label, type, ids, change, multiple, initialNames = {}) {
        const box = el('div', null, 'wb-picker');
        box.append(el('strong', label));
        const selected = el('div', null, 'wb-toolbar'); box.append(selected); parent.append(box);
        const names = new Map(Object.entries(initialNames).map(([id,name]) => [Number(id),name]));
        let current = ids.slice();
        const draw = function () {
            selected.replaceChildren();
            current.forEach(function (id) {
                button((names.get(id) || '#' + id) + ' ×', function () {
                    current = current.filter(v => v !== id); change(current); draw();
                },selected);
            });
        };
        bindSearch(box,type,function (item) {
            names.set(item.id,item.label);
            current = multiple ? Array.from(new Set(current.concat(item.id))) : [item.id];
            change(current); draw();
        });
        box.querySelector('input[type="search"]').setAttribute('aria-label','Rechercher ' + label);
        draw();
    }
    function knowledgePicker(parent, step, model, form) {
        const box = el('section', null, 'wb-knowledge');
        box.setAttribute('aria-label', 'Procédures de l’étape');
        parent.append(box);
        box.append(el('h4', 'Procédures — base de connaissances GLPI'));
        box.append(el('p', 'Associer une fiche existante ou créer une procédure à compléter. Le technicien pourra l’ouvrir depuis le ticket.', 'text-muted'));
        const selected = el('div', null, 'wb-knowledge-selected'); box.append(selected);
        const toolbar = el('div', null, 'wb-toolbar'); box.append(toolbar);
        const browser = el('div', null, 'wb-knowledge-browser'); browser.hidden = true; box.append(browser);
        const creator = el('div', null, 'wb-knowledge-creator'); creator.hidden = true; box.append(creator);
        const notice = el('p', '', 'text-muted'); notice.setAttribute('role', 'status'); box.append(notice);
        const names = new Map();
        const root = form.closest('[data-wb-root]');
        const current = () => step.knowbaseitems_id || [];
        const readonly = form.dataset.readonly === '1';
        let loaded = false, serial = 0, timer, hydrated = false;
        const lookup = async function (params) {
            const url = new URL(root.dataset.lookup, location.href);
            url.searchParams.set('type', 'knowledge');
            Object.entries(params).forEach(([key,value]) => url.searchParams.set(key,value));
            const response = await fetch(url, {credentials:'same-origin',headers:{Accept:'application/json'}});
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Recherche impossible.');
            return data;
        };
        function link(item, label) {
            const a = el('a', label || item.label, 'btn btn-sm btn-outline-primary');
            a.href = item.url; a.target = '_blank'; a.rel = 'noopener'; return a;
        }
        function drawSelected() {
            selected.replaceChildren();
            if (!current().length) selected.append(el('p', 'Aucune fiche associée.', 'text-muted'));
            current().forEach(function (id) {
                const row = el('div', null, 'wb-knowledge-card');
                const item = names.get(id);
                row.append(item ? link(item) : el('span', (hydrated ? 'Fiche supprimée ou inaccessible' : 'Fiche associée') + ' (#' + id + ')'));
                const remove = button('Retirer', function () {
                    step.knowbaseitems_id = current().filter(v => v !== id); drawSelected(); updateResults();
                }, row);
                remove.setAttribute('aria-label', 'Retirer la fiche #' + id); remove.disabled = readonly;
                selected.append(row);
            });
        }
        function choose(item) {
            if (readonly) return;
            if (!current().includes(item.id) && current().length >= 200) { notice.textContent = '200 fiches maximum par étape.'; return; }
            names.set(item.id, item);
            step.knowbaseitems_id = Array.from(new Set(current().concat(item.id)));
            drawSelected(); updateResults();
        }
        function updateResults() {
            browser.querySelectorAll('[data-knowledge-id]').forEach(function (b) {
                const chosen = current().includes(Number(b.dataset.knowledgeId));
                b.disabled = readonly || chosen; b.textContent = chosen ? 'Déjà associée' : 'Associer à l’étape';
            });
        }
        async function hydrate() {
            if (hydrated || !current().length) return;
            try {
                const data = await lookup({ids:current().join(',')});
                data.items.forEach(item => names.set(item.id,item)); hydrated = true; drawSelected();
            } catch (error) { notice.textContent = error.message; }
        }
        const stepCard = parent.closest('details');
        stepCard?.addEventListener('toggle', () => { if (stepCard.open) hydrate(); });
        if (stepCard?.open) hydrate();
        button('Choisir une fiche', function () {
            browser.hidden = !browser.hidden; creator.hidden = true;
            if (!browser.hidden && !loaded) refresh();
        }, toolbar);
        const createToggle = button('Créer une fiche', function () {
            creator.hidden = !creator.hidden; browser.hidden = true;
            if (!creator.hidden) title.value = (model.name + ' — ' + step.title).slice(0,255);
        }, toolbar);
        if (readonly || !form.dataset.knowledgeCreate) { createToggle.disabled = true; createToggle.title = 'Création indisponible dans cette page.'; }
        const searchInput = control(browser, 'Rechercher des fiches', '', function () {}, 'search');
        searchInput.placeholder = 'Titre, mots de la procédure…'; searchInput.maxLength = 100;
        const filters = el('div', null, 'wb-knowledge-filters'); browser.append(filters);
        const scope = select(filters, 'Rechercher dans', {all:'Titre et contenu',title:'Titre uniquement'}, 'all', refresh);
        const kind = select(filters, 'Type de fiche', {all:'Toutes les fiches',internal:'Connaissances internes',faq:'FAQ'}, 'all', refresh);
        const sort = select(filters, 'Trier par', {newest:'Création : plus récentes',oldest:'Création : plus anciennes'}, 'newest', refresh);
        const since = control(filters, 'Modifiées depuis le', '', refresh, 'date');
        const mine = control(filters, 'Mes fiches uniquement', false, refresh, 'checkbox');
        const status = el('p', '', 'text-muted'); status.setAttribute('role','status'); browser.append(status);
        const results = el('div', null, 'wb-knowledge-results'); browser.append(results);
        const moreHost = el('div', null, 'wb-toolbar'); browser.append(moreHost);
        const reset = button('Réinitialiser les filtres', function () {
            searchInput.value = ''; scope.value = 'all'; kind.value = 'all'; sort.value = 'newest'; since.value = ''; mine.checked = false; refresh();
        }, browser);
        reset.classList.add('wb-knowledge-reset');
        function refresh() {
            clearTimeout(timer); const request = ++serial;
            results.replaceChildren(); moreHost.replaceChildren(); loaded = true;
            status.textContent = 'Recherche en cours…';
            timer = setTimeout(() => run(0,request), 250);
        }
        async function run(after, request) {
            try {
                const data = await lookup({q:searchInput.value,scope:scope.value,kind:kind.value,sort:sort.value,since:since.value,mine:mine.checked?'1':'0',after:after||0});
                if (request !== serial) return;
                if (!after) results.replaceChildren();
                moreHost.replaceChildren();
                data.items.forEach(function (item) {
                    names.set(item.id,item);
                    const card = el('article', null, 'wb-knowledge-card');
                    card.append(el('strong',item.label), el('small',(item.faq?'FAQ':'Connaissance interne') + ' · #' + item.id + (item.modified?' · Modifiée le '+item.modified:''),'text-muted'));
                    card.append(el('p',item.excerpt || 'Aucun aperçu disponible.'));
                    const actions = el('div',null,'wb-toolbar'); card.append(actions);
                    actions.append(link(item,'Consulter la fiche'));
                    button('Associer à l’étape', () => choose(item), actions).dataset.knowledgeId = item.id;
                    results.append(card);
                });
                drawSelected(); updateResults();
                status.textContent = results.children.length ? results.children.length + ' fiche(s) affichée(s).' : 'Aucune fiche accessible avec ces filtres.';
                if (data.next) button('Afficher la suite', function (event) {
                    event.currentTarget.disabled = true; run(data.next,request);
                }, moreHost);
            } catch (error) {
                if (request !== serial) return;
                status.textContent = error.message;
                moreHost.replaceChildren();
                button('Réessayer la recherche', () => run(after,request), moreHost);
            }
        }
        searchInput.addEventListener('input', refresh);
        const title = control(creator, 'Titre de la nouvelle fiche', '', function () {});
        title.maxLength = 255;
        const visibility = select(creator, 'Visibilité initiale', {entity:'Techniciens de l’entité du workflow (droits GLPI requis)',private:'Personnelle — partager ensuite dans GLPI'}, 'entity', function () {});
        creator.append(el('p', 'Modèle : Objectif · Prérequis · Procédure détaillée · Contrôles et résultat attendu · En cas de problème / retour arrière · Références et pièces jointes.'));
        const legacy = el('p', step.instructions ? 'L’ancien texte d’instructions sera repris dans la rubrique Procédure détaillée.' : '', 'text-muted');
        creator.append(legacy);
        creator.append(el('p', 'La fiche est créée immédiatement dans GLPI. Compléter son contenu, puis enregistrer le brouillon du workflow pour conserver l’association.', 'text-muted'));
        const create = button('Créer et associer la fiche', async function () {
            if (!title.value.trim() || !step.title.trim()) { notice.textContent = 'Renseigner le titre de la fiche et de l’étape.'; return; }
            if (current().length >= 200) { notice.textContent = '200 fiches maximum par étape.'; return; }
            create.disabled = true; createToggle.disabled = true; notice.textContent = 'Création de la fiche…';
            const body = new FormData();
            Object.entries({_wb_token:form.querySelector('[name="_wb_token"]').value,template:form.querySelector('[name="id"]')?.value||0,
                entity:form.querySelector('[name="entities_id"]')?.value||0,title:title.value,workflow:model.name,step:step.title,
                description:step.description||'',legacy:step.instructions||'',visibility:visibility.value}).forEach(([key,value]) => body.set(key,value));
            try {
                const response = await fetch(form.dataset.knowledgeCreate,{method:'POST',body:body,credentials:'same-origin',headers:{Accept:'application/json'}});
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Création impossible.');
                choose(data.item); step.instructions = ''; legacy.textContent = ''; creator.hidden = true;
                notice.replaceChildren(el('span','Fiche créée et associée. '),link(data.item,'Compléter la fiche dans GLPI'));
            } catch (error) { notice.textContent = error.message; }
            finally { create.disabled = false; createToggle.disabled = false; }
        }, creator);
        drawSelected();
    }


    function variables(model, localFields) {
        const out = {};
        model.fields.filter(f => f.type !== 'section').forEach(f => { out['form.'+f.id] = f.label + ' (formulaire)'; });
        (localFields || []).filter(f => f.type !== 'section').forEach(f => { out['self.'+f.id] = f.label + ' (étape / formulaire courant)'; });
        return out;
    }
    function condition(parent, value, vars, change, depth) {
        const host = el('div', null, 'wb-condition'); parent.append(host);
        function draw(node) {
            host.replaceChildren();
            if (!node) {
                host.append(el('span','Toujours '));
                button('Ajouter une condition',function () {
                    const first = Object.keys(vars)[0];
                    if (!first) { alert('Ajouter un champ utilisable dans une condition.'); return; }
                    const next = {field:first,op:'eq',value:''}; change(next); draw(next);
                },host);
                return;
            }
            if (node.all || node.any) {
                const key = node.all ? 'all' : 'any';
                select(host,'Groupe',{all:'ET — toutes',any:'OU — au moins une'},key,function (newKey) {
                    const next = {}; next[newKey] = node[key]; change(next); draw(next);
                });
                node[key].forEach(function (child,index) {
                    const row = el('div',null,'wb-condition-child'); host.append(row);
                    condition(row,child,vars,function (next) {
                        if (next) node[key][index] = next; else node[key].splice(index,1);
                        const result = node[key].length ? node : null;
                        change(result); draw(result);
                    },depth+1);
                });
                if (node[key].length < 20) button('Ajouter une règle',function () {
                    node[key].push({field:Object.keys(vars)[0],op:'eq',value:''}); change(node); draw(node);
                },host);
            } else {
                select(host,'Variable',vars,node.field,v => { node.field=v; change(node); });
                select(host,'Opérateur',operators,node.op,v => { node.op=v; change(node); });
                control(host,'Valeur attendue (oui : 1, non : 0)',node.value,v => { node.value=v; change(node); });
                if (depth < 6) button('Grouper avec ET / OU',function () {
                    const next = {all:[node]}; change(next); draw(next);
                },host);
            }
            button('Supprimer la condition',function () { change(null); draw(null); },host);
        }
        draw(value);
    }
    function reorderTools(parent, list, index, render, duplicate, remove) {
        const bar = el('div',null,'wb-toolbar'); parent.append(bar);
        button('↑',function () { if(index>0){ [list[index-1],list[index]]=[list[index],list[index-1]]; render(); } },bar).setAttribute('aria-label','Déplacer vers le haut');
        button('↓',function () { if(index+1<list.length){ [list[index+1],list[index]]=[list[index],list[index+1]]; render(); } },bar).setAttribute('aria-label','Déplacer vers le bas');
        button('Dupliquer',duplicate,bar);
        button('Supprimer',function () { if(confirm('Supprimer cet élément du brouillon ?')) remove(); },bar);
        parent.draggable = true;
        parent.addEventListener('dragstart',function (event) {
            if (event.target !== parent) return;
            event.stopPropagation(); event.dataTransfer.setData('text/plain',String(index));
            parent.parentElement.dataset.wbDragSource = String(index);
        });
        parent.addEventListener('dragover',event => { event.preventDefault(); event.stopPropagation(); });
        parent.addEventListener('drop',function (event) {
            event.preventDefault(); event.stopPropagation();
            const holder=parent.parentElement;
            if (!holder.hasAttribute('data-wb-drag-source')) return;
            const source=Number(holder.dataset.wbDragSource);
            holder.removeAttribute('data-wb-drag-source');
            const moved=list.splice(source,1)[0];list.splice(index,0,moved);render();
        });
        parent.addEventListener('dragend',function () { parent.parentElement?.removeAttribute('data-wb-drag-source'); });
    }
    function fieldEditor(parent, fields, model, initial) {
        const host = el('div'); parent.append(host);
        const render = function () {
            const openIds = new Set(Array.from(host.querySelectorAll(':scope > details[open]'), n => n.dataset.fieldId));
            host.replaceChildren();
            fields.forEach(function (field,index) {
                const card=el('details',null,'wb-editor-card');card.dataset.fieldId=field.id;if(openIds.has(field.id))card.open=true;host.append(card);
                const title=el('summary',field.label+' — '+(labels[field.type]||field.type));card.append(title);
                reorderTools(card,fields,index,render,function () {
                    const next=copy(field);next.id=uid('f');next.label+=' (copie)';fields.splice(index+1,0,next);render();
                },function () { fields.splice(index,1);render(); });
                control(card,'Identifiant stable',field.id,function () {}).readOnly=true;
                control(card,'Libellé',field.label,function (v) { field.label=v;title.textContent=v+' — '+labels[field.type]; });
                select(card,'Type',labels,field.type,function (v) {
                    field.type=v;field.default=null;field.choices=field.choices||[];
                    field.maxlength=v==='textarea'?10000:255;render();
                });
                control(card,'Description',field.description||'',v => { field.description=v; },'textarea');
                control(card,'Obligatoire',field.required,v => { field.required=v; },'checkbox');
                if (['select','radio','multiselect'].includes(field.type)) {
                    control(card,'Choix (une valeur par ligne)',(field.choices||[]).join('\n'),v => {
                        field.choices=v.split('\n').map(s => s.trim()).filter(Boolean);
                    },'textarea');
                }
                if (!['user','group','entity','asset','document','section','multiselect'].includes(field.type)) {
                    if (field.type==='boolean') {
                        select(card,'Valeur par défaut',{'':'Aucune','1':'Oui','0':'Non'},field.default===null||field.default===undefined?'':(field.default?'1':'0'),v => { field.default=v===''?null:v==='1'; });
                    } else {
                        control(card,'Valeur par défaut',field.default,v => { field.default=field.type==='checkbox'?v:v||null; },field.type==='checkbox'?'checkbox':field.type==='number'?'number':field.type==='date'?'date':field.type==='datetime'?'datetime-local':'text');
                    }
                }
                if (field.type==='number') {
                    ['min','max'].forEach(k => control(card,k==='min'?'Minimum':'Maximum',field[k],v => { field[k]=v===''?null:Number(v); },'number'));
                }
                control(card,'Longueur maximale',field.maxlength||255,v => { field.maxlength=Number(v); },'number');
                card.append(el('h4','Condition d’affichage'));
                let vars=variables(model,fields.slice(0,index));
                if(initial) {
                    vars={};fields.slice(0,index).forEach(f => { vars['form.'+f.id]=f.label;vars['self.'+f.id]=f.label+' (local)'; });
                }
                condition(card,field.condition||null,vars,v => { field.condition=v; },0);
            });
            button('Ajouter un champ',function () {
                fields.push({id:uid('f'),label:'Nouveau champ',type:'text',required:false,description:'',choices:[],condition:null,maxlength:255});render();
                host.lastElementChild?.previousElementSibling?.setAttribute('open','');
            },host);
        };
        render();
    }
    function editor(form) {
        if(form.dataset.wbEditorReady)return;
        form.dataset.wbEditorReady='1';
        const model=JSON.parse(form.dataset.definition);
        const host=form.querySelector('[data-wb-editor-ui]');
        const nav=el('nav',null,'wb-tabs');nav.setAttribute('aria-label','Onglets de l’éditeur');host.append(nav);
        const panes=[];
        const tabs=['Général','Formulaire','Étapes','Conditions','Permissions et affectations','Paramètres','Prévisualisation'];
        let current=0;
        const views=tabs.map(function (title,index) {
            const pane=el('section',null,'wb-pane');pane.hidden=index!==0;host.append(pane);panes.push(pane);
            const b=button(title,function () {
                current=index;panes.forEach((p,i) => {p.hidden=i!==index;});Array.from(nav.children).forEach((n,i)=>n.setAttribute('aria-current',i===index?'page':'false'));
                draw(index,pane);
            },nav);
            b.setAttribute('aria-current',index===0?'page':'false');
            return pane;
        });
        function draw(index,pane) {
            const openSteps = new Set(Array.from(pane.querySelectorAll('.wb-step-editor[open]'), n => n.dataset.stepId));
            pane.replaceChildren();
            if(index===0) {
                control(pane,'Nom',model.name,v=>{model.name=v;});
                control(pane,'Description',model.description,v=>{model.description=v;},'textarea');
                control(pane,'Catégorie',model.category,v=>{model.category=v;});
                control(pane,'Icône Tabler (ex. ti ti-checklist)',model.icon,v=>{model.icon=v;});
                pane.append(el('p','Les changements sont enregistrés dans le brouillon. Publier une version pour la rendre disponible au lancement.'));
            } else if(index===1) {
                pane.append(el('p','Les sections regroupent visuellement les champs. Utiliser les flèches ou le glisser-déposer pour modifier l’ordre.'));
                fieldEditor(pane,model.fields,model,true);
            } else if(index===2) {
                const list=el('div');pane.append(list);
                model.steps.forEach(function (step,i) {
                    const card=el('details',null,'wb-editor-card wb-step-editor');card.dataset.stepId=step.id;if(openSteps.has(step.id))card.open=true;list.append(card);
                    const title=el('summary',step.title);card.append(title);
                    const redraw=()=>draw(2,pane);
                    reorderTools(card,model.steps,i,redraw,function () {
                        const next=copy(step);next.id=uid('s');next.title+=' (copie)';model.steps.splice(i+1,0,next);redraw();
                    },function () {
                        if(model.steps.some(s=>(s.depends_on||[]).includes(step.id))||model.rules.some(r=>r.step===step.id)){
                            alert('Retirer les dépendances et règles qui ciblent cette étape avant de la supprimer.');return;
                        }
                        model.steps.splice(i,1);redraw();
                    });
                    control(card,'Identifiant stable',step.id,function () {}).readOnly=true;
                    control(card,'Titre',step.title,v=>{step.title=v;title.textContent=v;});
                    control(card,'Description',step.description||'',v=>{step.description=v;},'textarea');
                    knowledgePicker(card,step,model,form);

                    control(card,'Étape obligatoire',step.required!==false,v=>{step.required=v;},'checkbox');
                    const messages = el('fieldset',null,'wb-step-messages'); card.append(messages);
                    messages.append(el('legend','Messages automatiques dans le ticket'));
                    messages.append(el('p','Suivis internes avec lien du workflow, intervenants et progression. Le message de fin inclut les données renseignées.','text-muted'));
                    [['notify_available','Étape disponible'],['notify_taken','Étape prise en charge'],['notify_done','Étape terminée']].forEach(([key,label]) => {
                        control(messages,label,step[key] !== false,value => {step[key] = value;},'checkbox');
                    });
                    picker(card,'Utilisateur responsable','user',step.users_id?[step.users_id]:[],ids=>{step.users_id=ids[0]||0;},false);
                    picker(card,'Groupe responsable','group',step.groups_id?[step.groups_id]:[],ids=>{step.groups_id=ids[0]||0;},false);
                    const options={};model.steps.filter(s=>s.id!==step.id).forEach(s=>{options[s.id]=s.title;});
                    select(card,'Dépendances (Ctrl / Cmd pour plusieurs)',options,step.depends_on||[],v=>{step.depends_on=v;},true);
                    select(card,'Les dépendances empêchent…',{start:'Le démarrage',complete:'La validation uniquement'},step.dependency_mode||'start',v=>{step.dependency_mode=v;});
                    select(card,'État initial',{available:'Disponible',blocked:'Bloquée'},step.initial_state||'available',v=>{step.initial_state=v;});
                    card.append(el('h4','Afficher cette étape si…'));condition(card,step.condition||null,variables(model),v=>{step.condition=v;},0);
                    card.append(el('h4','Champs de l’étape'));step.fields=step.fields||[];fieldEditor(card,step.fields,model,false);
                });
                button('Ajouter une étape',function () {
                    model.steps.push({id:uid('s'),title:'Nouvelle étape',description:'',required:true,notify_available:true,notify_taken:true,notify_done:true,users_id:0,groups_id:0,depends_on:[],dependency_mode:'start',initial_state:'available',condition:null,knowbaseitems_id:[],fields:[]});
                    draw(2,pane);pane.querySelectorAll('.wb-step-editor')[model.steps.length-1]?.setAttribute('open','');
                },pane);
            } else if(index===3) {
                pane.append(el('p','Afficher et activer exigent que la condition soit vraie ; bloquer s’applique lorsqu’elle est vraie. Les règles utilisent les informations figées du lancement.'));
                model.rules.forEach(function (rule,i) {
                    const card=el('div',null,'wb-editor-card');pane.append(card);
                    const options={};model.steps.forEach(s=>{options[s.id]=s.title;});
                    select(card,'Étape ciblée',options,rule.step,v=>{rule.step=v;});
                    select(card,'Action',{show:'Afficher si vrai, masquer sinon',enable:'Activer si vrai, bloquer sinon',block:'Bloquer si vrai'},rule.action,v=>{rule.action=v;});
                    condition(card,rule.when,variables(model),v=>{rule.when=v;},0);
                    button('Supprimer cette règle',function () {model.rules.splice(i,1);draw(3,pane);},card);
                });
                button('Ajouter une règle',function () {
                    if(!model.steps.length||!model.fields.length){alert('Ajouter des champs et des étapes avant une règle.');return;}
                    model.rules.push({step:model.steps[0].id,action:'show',when:{field:'form.'+model.fields[0].id,op:'true',value:''}});draw(3,pane);
                },pane);
            } else if(index===4) {
                pane.append(el('p','Aucune sélection : tous les utilisateurs disposant des droits du plugin et de l’entité. Les restrictions utilisateurs et groupes sont réunies avec OU. Les droits GLPI restent obligatoires.'));
                [['launch','Peuvent lancer'],['execute','Peuvent exécuter'],['manage','Peuvent gérer']].forEach(function (pair) {
                    const scope=pair[0];pane.append(el('h3',pair[1]));
                    picker(pane,'Groupes','group',model[scope+'_groups'],v=>{model[scope+'_groups']=v;},true);
                    picker(pane,'Utilisateurs','user',model[scope+'_users'],v=>{model[scope+'_users']=v;},true);
                });
            } else if(index===5) {
                control(pane,'Autoriser la création de ticket',model.allow_create,v=>{model.allow_create=v;},'checkbox');
                control(pane,'Autoriser l’association à un ticket existant',model.allow_attach,v=>{model.allow_attach=v;},'checkbox');
                control(pane,'Exiger les étapes obligatoires avant résolution et clôture',model.require_before_close,v=>{model.require_before_close=v;},'checkbox');
                control(pane,'Titre du ticket',model.ticket_title,v=>{model.ticket_title=v;});
                control(pane,'Description du ticket',model.ticket_description,v=>{model.ticket_description=v;},'textarea');
                pane.append(el('p','Variables : {{workflow}}, {{summary}} et {{identifiant_du_champ}}. Les identifiants stables sont visibles dans chaque bloc de champ. Aucun code n’est exécuté.'));
                pane.append(el('p','Progression : seules les étapes obligatoires applicables comptent. Sans étape obligatoire applicable, la progression est de 100 %. Les étapes facultatives peuvent être complétées tant que le ticket reste ouvert.'));
            } else {
                const notice=el('p','Simulation sans création de ticket ni enregistrement.');pane.append(notice);
                const simulation=el('div');pane.append(simulation);
                const status=el('div');pane.append(status);
                let previewValues={};
                async function preview() {
                    const body=new FormData();body.set('_wb_token',form.querySelector('[name="_wb_token"]').value);
                    body.set('definition',JSON.stringify(model));body.set('input',JSON.stringify(previewValues));
                    try {
                        const response=await fetch(form.dataset.preview,{method:'POST',body:body,credentials:'same-origin'});
                        const data=await response.json();if(!response.ok)throw new Error(data.error);
                        simulation.innerHTML=data.html; // Trusted server-rendered escaped fields only.
                        simulation.querySelectorAll('.wb-reference').forEach(references);
                        status.replaceChildren(el('p','Progression initiale : '+data.progress.percent+' %'));
                        data.steps.forEach(s=>status.append(el('p',s.title+' — '+states[s.state]+(s.required?'':' (facultative)'))));
                    } catch(error) {status.replaceChildren(el('p',error.message,'text-danger'));}
                }
                button('Tester les valeurs et conditions',function () {
                    simulation.querySelectorAll('[data-wb-field]').forEach(box=>{const f=JSON.parse(box.dataset.wbField);if(f.type!=='section')previewValues[f.id]=readValue(box);});
                    preview();
                },pane);preview();
            }
        }
        form.addEventListener('submit',function (event) {
            if(form.dataset.readonly==='1'){event.preventDefault();return;}
            // Flush the focused edit before serializing.
            document.activeElement?.dispatchEvent(new Event('change',{bubbles:true}));
            form.querySelector('[name="definition"]').value=JSON.stringify(model);
        });
        draw(0,views[0]);
    }
    function launchAssignments(box) {
        if (box.dataset.wbAssigneesReady) return;
        box.dataset.wbAssigneesReady = '1';
        const team = JSON.parse(box.dataset.assignees);
        const labels = JSON.parse(box.dataset.assigneeLabels || '{}');
        const ui = box.querySelector('[data-assignees-ui]');
        const inputs = box.querySelector('[data-assignees-inputs]');
        function sync() {
            inputs.replaceChildren();
            ['users','groups'].forEach(key => team[key].forEach(function (id) {
                const input = el('input'); input.type = 'hidden';
                input.name = 'assigned_' + key + '[]'; input.value = id; inputs.append(input);
            }));
        }
        picker(ui,'Utilisateurs du workflow','user',team.users, ids => {team.users = ids; sync();},true,labels.users);
        picker(ui,'Groupes du workflow','group',team.groups, ids => {team.groups = ids; sync();},true,labels.groups);
        sync();
    }

    function init(root) {
        root.querySelectorAll('[data-wb-editor]').forEach(editor);
        root.querySelectorAll('[data-wb-assignees]').forEach(launchAssignments);
        root.querySelectorAll('[data-wb-runtime]').forEach(runtime);
        root.querySelectorAll('.wb-reference').forEach(references);
    }
    document.addEventListener('click',function (event) {
        const target=event.target.closest('[data-confirm]');
        if(target && !confirm(target.dataset.confirm))event.preventDefault();
    });
    function openLinkedStep() {
        if (!location.hash.startsWith('#wb-step-')) return;
        const step = document.getElementById(location.hash.slice(1));
        if (step?.matches('details.wb-step')) step.open = true;
    }
    globalThis.addEventListener('hashchange',openLinkedStep);
    function boot() {
        openLinkedStep();
        init(document);
        let pending=false;
        new MutationObserver(function () {
            if(pending)return;pending=true;
            queueMicrotask(function () {pending=false;init(document);});
        }).observe(document.body,{childList:true,subtree:true});
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
