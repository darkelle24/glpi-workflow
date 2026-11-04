'use strict';
const assert = require('node:assert/strict');
require('../public/js/workflowbuilder.js');
const evaluate = globalThis.WorkflowBuilderConditions.evaluate;
const cases = [
 ['eq',true,'1',true],['ne',3,'2',true],['contains',['a','b'],'b',true],
 ['contains','abcd','bc',true],['not_contains','abcd','z',true],['gt',12,'10',true],
 ['lt',1,2,true],['empty',null,'',true],['not_empty',false,'',true],
 ['true','1','',true],['false',false,'',true],['false',null,'',false],
 ['gt','abc',1,false],['eq',false,'0',true],['eq',0,1,false],['empty',[],'',true],['ne',[],null,true]
];
for (const [op,a,b,expected] of cases) assert.equal(evaluate({field:'x',op:op,value:b},{x:a}),expected,op);
assert.equal(evaluate({all:[{field:'a',op:'true'},{any:[{field:'b',op:'empty'},{field:'a',op:'true'}]}]},{a:true,b:'z'}),true);
console.log('18 browser condition cases passed');
