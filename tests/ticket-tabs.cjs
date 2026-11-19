const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../public/js/ticket-tabs.js'), 'utf8');

async function fixture({pretty = true, path = '/glpi/front/ticket.form.php?id=11&forcetab=Ticket%244#wb-instance-7', failed = false} = {}) {
    const origin = 'https://glpi.example';
    let current = new URL(path, origin);
    const events = {}, panelEvents = {}, historyCalls = [];
    let shown, fetches = 0, reloads = 0;
    const location = {reload() { reloads++; }};
    ['pathname','search','hash','href','origin'].forEach(key => Object.defineProperty(location, key, {get() { return current[key]; }}));
    function link(key, active = false) {
        const attributes = key === '-1' ? {'data-show-all-tabs':'true'} : {'data-glpi-ajax-content':'/glpi/ajax/common.tabs.php?_glpi_tab=' + encodeURIComponent(key)};
        let href = '#'; return {active, get href() {return new URL(href, current).href;}, set href(value) {href=value;}, getAttribute(name) {return attributes[name] || null;}};
    }
    const links = [link('Ticket$4', true), link('PluginWorkflowbuilderTicket$1'), link('-1')];
    const panel = {querySelectorAll() {return links;}, querySelector() {return links.find(a=>a.active);}, addEventListener(name, fn) {panelEvents[name] = fn;}};
    const window = {addEventListener(name, fn) {events[name] = fn;}, jQuery() {return {on(name, selector, fn) {shown = fn;}};}};
    window.top = window.self = window;
    const tabs = Object.fromEntries(['Ticket$4','PluginWorkflowbuilderTicket$1','-1'].map((key,i) => [key, {url:pretty ? '/glpi/front/ticket/11/' + ['statistiques','workflow','tout'][i] : '/glpi/front/ticket.form.php?id=11&forcetab='+encodeURIComponent(key)}]));
    const context = {
        window, location, URL, URLSearchParams, console,
        document: {readyState:'complete', getElementById() {return panel;}},
        history: Object.fromEntries(['replaceState','pushState'].map(method => [method, (state, title, url) => {current = new URL(url, current); historyCalls.push([method,current.href]);}])),
        fetch: async url => {fetches++; assert.equal(url,'/glpi/plugins/workflowbuilder/front/ticket-tabs.php?id=11'); if(failed) throw Error('offline'); return {ok:true, json:async()=>({tabs})};}
    };
    vm.runInNewContext(source, context);
    await new Promise(resolve => setImmediate(resolve));
    return {links, historyCalls, events, panelEvents, location, get fetches(){return fetches;}, get reloads(){return reloads;}, show(i){shown.call(links[i]);}};
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
    assert.equal(new URL(native.links[1].href).searchParams.get('forcetab'),'PluginWorkflowbuilderTicket$1');
    const unrelated = await fixture({path:'/glpi/front/problem.form.php?id=11'});
    assert.equal(unrelated.fetches,0);
    const creation = await fixture({path:'/glpi/front/ticket.form.php?id=0'});
    assert.equal(creation.fetches,0);
    const failed = await fixture({failed:true});
    assert.equal(failed.historyCalls.length,0);
    console.log('Ticket tab navigation checks passed (history, anchors, all, modifiers, subdirectory, fallback).');
})().catch(error=>{console.error(error);process.exitCode=1;});