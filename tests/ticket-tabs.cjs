const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../public/js/ticket-tabs.js'), 'utf8');

async function fixture({pretty = true, path = '/glpi/front/ticket.form.php?id=11&forcetab=Ticket%244#wb-instance-7', failed = false} = {}) {
    const origin = 'https://glpi.example';
    let current = new URL(path, origin);
    const events = {}, panelEvents = {}, historyCalls = [];
    let shown, fetches = 0, reloads = 0, anchorScrolls = 0;
    const location = {reload() { reloads++; }};
    ['pathname','search','hash','href','origin'].forEach(key => Object.defineProperty(location, key, {get() { return current[key]; }}));
    function link(key, active = false) {
        const attributes = key === '-1' ? {'data-show-all-tabs':'true'} : {'data-glpi-ajax-content':'/glpi/ajax/common.tabs.php?_glpi_tab=' + encodeURIComponent(key)};
        let href = '#'; return {active, get href() {return new URL(href, current).href;}, set href(value) {href=value;}, get classList() {return {contains: () => this.active};}, getAttribute(name) {return attributes[name] || null;}};
    }
    const links = [link('Ticket$4', true), link('PluginWorkflowbuilderTicket$1'), link('-1'), link('Change_Ticket$1'), link('PluginWorkflowbuilderTicket$2')];
    const panel = {querySelectorAll() {return links;}, querySelector() {return links.find(a=>a.active);}, addEventListener(name, fn) {panelEvents[name] = fn;}};
    const window = {addEventListener(name, fn) {events[name] = fn;}, jQuery(target) {return {
        on(name, selector, fn) {if (typeof selector === 'function') events[name] = selector; else shown = fn;},
        off(name) {delete events[name];},
        tab() {links.forEach(a => a.active = false); target.active = true; if (shown) shown.call(target);}
    };}};
    window.top = window.self = window;
    const tabs = Object.fromEntries(['Ticket$4','PluginWorkflowbuilderTicket$1','-1','Change_Ticket$1','PluginWorkflowbuilderTicket$2'].map((key,i) => [key, {url:pretty ? '/glpi/front/ticket/11/' + ['statistiques','workflow','tout','changements','mes-etapes'][i] : '/glpi/front/ticket.form.php?id=11&forcetab='+encodeURIComponent(key.endsWith('$1') ? key.slice(0,-2) : key)}]));
    const context = {
        window, location, URL, URLSearchParams, console,
        document: {readyState:'complete', getElementById(id) {return id === 'tabspanel' ? panel : id === 'wb-instance-7' ? {scrollIntoView(){anchorScrolls++;}} : null;}},
        history: Object.fromEntries(['replaceState','pushState'].map(method => [method, (state, title, url) => {current = new URL(url, current); historyCalls.push([method,current.href]);}])),
        fetch: async url => {fetches++; assert.equal(url,'/glpi/plugins/workflowbuilder/front/ticket-tabs.php?id=11'); if(failed) throw Error('offline'); return {ok:true, json:async()=>({tabs})};}
    };
    vm.runInNewContext(source, context);
    await new Promise(resolve => setImmediate(resolve));
    return {links, historyCalls, events, panelEvents, location, get fetches(){return fetches;}, get reloads(){return reloads;}, get anchorScrolls(){return anchorScrolls;}, show(i){shown.call(links[i]);}};
}
(async () => {
    const f = await fixture();
    assert.equal(f.location.href,'https://glpi.example/glpi/front/ticket/11/statistiques#wb-instance-7');
    assert.equal(f.historyCalls[0][0],'replaceState');
    f.show(1);
    assert.equal(f.location.pathname,'/glpi/front/ticket/11/workflow');
    assert.equal(f.location.hash,'');
    assert.equal(f.historyCalls[1][0],'pushState');
    f.show(1);
    assert.equal(f.historyCalls.length,2);
    f.show(2);
    assert.equal(f.location.pathname,'/glpi/front/ticket/11/tout');
    f.events.popstate();
    assert.equal(f.reloads,1);
    let stopped = false;
    f.panelEvents.click({target:{closest(){return f.links[0];}},ctrlKey:true, stopImmediatePropagation(){stopped=true;}});
    assert.equal(stopped,true);
    const native = await fixture({pretty:false,path:'/glpi/front/ticket.form.php?id=11&_openfollowup=1'});
    assert.equal(native.location.pathname,'/glpi/front/ticket.form.php');
    assert.equal(new URL(native.links[1].href).searchParams.get('_openfollowup'),'1');
    assert.equal(new URL(native.links[1].href).searchParams.get('forcetab'),'PluginWorkflowbuilderTicket');
    const short = await fixture({pretty:false,path:'/glpi/front/ticket.form.php?id=11&forcetab=Change_Ticket'});
    assert.equal(short.links[3].active,true);
    assert.equal(new URL(short.location.href).searchParams.get('forcetab'),'Change_Ticket');
    assert.equal(short.historyCalls.filter(c=>c[0]==='pushState').length,0);
    const old = await fixture({pretty:false,path:'/glpi/front/ticket.form.php?id=11&forcetab=Change_Ticket%241'});
    assert.equal(old.links[3].active,true);
    assert.equal(new URL(old.location.href).searchParams.get('forcetab'),'Change_Ticket');
    const hidden = await fixture({pretty:false,path:'/glpi/front/ticket.form.php?id=11&forcetab=Hidden'});
    assert.equal(hidden.links[0].active,true);
    const anchored = await fixture({pretty:false,path:'/glpi/front/ticket.form.php?id=11&forcetab=PluginWorkflowbuilderTicket#wb-instance-7'});
    assert.equal(anchored.links[1].active,true);
    assert.equal(anchored.location.hash,'#wb-instance-7');
    anchored.events['glpi.tab.loaded.wbTicketUrlAnchor']();
    assert.equal(anchored.anchorScrolls,1);
    assert.equal(anchored.events['glpi.tab.loaded.wbTicketUrlAnchor'],undefined);
    const mine = await fixture({pretty:false,path:'/glpi/front/ticket.form.php?id=11&forcetab=PluginWorkflowbuilderTicket%242'});
    assert.equal(mine.links[4].active,true);
    assert.equal(new URL(mine.location.href).searchParams.get('forcetab'),'PluginWorkflowbuilderTicket$2');
    const minePretty = await fixture({path:'/glpi/front/ticket.form.php?id=11&forcetab=PluginWorkflowbuilderTicket%242'});
    assert.equal(minePretty.location.pathname,'/glpi/front/ticket/11/mes-etapes');
    const unrelated = await fixture({path:'/glpi/front/problem.form.php?id=11'});
    assert.equal(unrelated.fetches,0);
    const creation = await fixture({path:'/glpi/front/ticket.form.php?id=0'});
    assert.equal(creation.fetches,0);
    const failed = await fixture({failed:true});
    assert.equal(failed.historyCalls.length,0);
    console.log('Ticket tab navigation checks passed (history, anchors, all, modifiers, subdirectory, fallback).');
})().catch(error=>{console.error(error);process.exitCode=1;});