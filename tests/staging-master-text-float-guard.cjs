'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const buyer=fs.readFileSync(path.join(__dirname,'../plugins/sanro-studio/assets/buyer.js'),'utf8');
const start=buyer.indexOf('const comparableTextDesign=JSON.parse(JSON.stringify(localTextDesign));');
const end=buyer.indexOf('if(JSON.stringify(comparableTextDesign)!==JSON.stringify(serverTextDesign))',start);
assert(start>0&&end>start,'Actual buyer comparison must be present');
// Exercise the EXACT runtime normalization code, not a test reimplementation.
const normalize=new Function('localTextDesign','serverTextDesign',
  buyer.slice(start,end)+'return JSON.stringify(comparableTextDesign)===JSON.stringify(serverTextDesign);');
const original={
 front:{category:'A3',objects:[{type:'text',x:12.25,y:8.75,width:6,height:3}],
   text:'QA',font_size:16,text_color:'#000000'},
 shirt_color:'black',quantities:{M:1},long_sleeve:false
};
const clone=v=>JSON.parse(JSON.stringify(v));
let count=0;
function check(condition,label){assert(condition,label);count++;}
check(normalize(clone(original),clone(original)),'identical design');
const epsilon=clone(original);
epsilon.front.objects[0].x+=0.0000005;
epsilon.front.objects[0].y-=0.0000005;
check(normalize(epsilon,clone(original)),'sub-micro text x/y drift');
check(!normalize({...epsilon,shirt_color:'white'},clone(original)),'colour mismatch');
check(!normalize({...epsilon,quantities:{M:2}},clone(original)),'quantity mismatch');
check(!normalize({...epsilon,long_sleeve:true},clone(original)),'sleeve mismatch');
const changes=[
 ['text',d=>d.front.text='DIFFERENT'],
 ['category',d=>d.front.category='A4'],
 ['font',d=>d.front.font_size=17],
 ['text colour',d=>d.front.text_color='#ffffff'],
 ['width',d=>d.front.objects[0].width+=0.0000001],
 ['height',d=>d.front.objects[0].height+=0.0000001],
 ['large x drift',d=>d.front.objects[0].x+=0.00001],
 ['large y drift',d=>d.front.objects[0].y+=0.00001],
 ['new object',d=>d.front.objects.push({type:'image',x:1,y:1,width:2,height:2})],
 ['object type',d=>d.front.objects[0].type='image'],
 ['missing text',d=>delete d.front],
 ['unexpected back',d=>d.back={category:'A3',objects:[{type:'text',x:1,y:1,width:1,height:1}],text:'B',font_size:16,text_color:'#000000'}],
 ['extra property',d=>d.front.objects[0].z=0],
 ['non-finite x',d=>d.front.objects[0].x=null]
];
for(const [label,change] of changes){
 const candidate=clone(original);change(candidate);
 check(!normalize(candidate,clone(original)),label+' must fail');
}
check(JSON.stringify(epsilon)!==JSON.stringify(original),
  'normalization does not mutate the original input');
check(buyer.includes('const designHash=currentDraft.design_hash;'),
  'existing revision hash remains server owned');
console.log('PASS: '+count+' SANRO text x/y tolerance safeguards (offline actual runtime code)');
