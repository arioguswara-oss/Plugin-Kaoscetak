'use strict';
const assert=require('node:assert/strict');
const g=require('../prototypes/editor-geometry.js');
const area={width:32,height:48};
let checks=0;
function eq(a,b){assert.deepEqual(a,b);checks++}
eq(g.toCm({x:25,y:25,width:50,height:50},area),{x:8,y:12,width:16,height:24});
eq(g.checkSide([{type:'image',x:0,y:0,width:40,height:40}],'A5',area).valid,true);
eq(g.checkSide([{type:'image',x:0,y:0,width:60,height:40}],'A5',area).error,'category_exceeded');
eq(g.checkSide([{type:'image',x:0,y:0,width:40,height:40},{type:'text',x:60,y:60,width:40,height:40}],'A4',area).error,'category_exceeded');
eq(g.checkSide([{type:'image',x:0,y:0,width:20,height:20},{type:'text',x:10,y:10,width:20,height:20}],'A5',area).valid,true);
eq(g.checkSide([{type:'image',x:0,y:0,width:20,height:20},{type:'image',x:20,y:20,width:20,height:20}],'A5',area).error,'invalid_object_type');
eq(g.checkSide([{type:'image',x:0,y:0,width:20,height:20}],'A5',null).error,'invalid_safe_area');
eq(g.checkSide([{type:'image',x:95,y:0,width:20,height:20}],'A5',area).error,'outside_safe_area');
eq(g.checkSide([],'A3+',area).empty,true);
eq(g.checkSide([],'A2',area).error,'invalid_category');
console.log('SANRO editor geometry: '+checks+' assertions PASS, 0 FAIL');
