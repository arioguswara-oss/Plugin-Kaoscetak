'use strict';
jQuery(function($){
 $('.sanro-mockup-matrix').each(function(){
  const field=this.querySelector('input[name="_sanro_studio_mockup_matrix"]');
  const body=this.querySelector('tbody'),add=this.querySelector('[data-add-mapping]');
  const variable=this.dataset.variable==='1';
  let rows,sizes,variations;
  try{
   rows=JSON.parse(field.value);
   sizes=JSON.parse(this.dataset.sizes);
   variations=JSON.parse(this.dataset.variations);
  }catch(error){return;}
  if(!Array.isArray(rows)||!Array.isArray(sizes)||!Array.isArray(variations))return;

  const colors=new Map();
  for(const row of rows){
   if(!colors.has(row.color))colors.set(row.color,{...row,ids:{}});
   const group=colors.get(row.color);
   if(row.size==='*')Object.assign(group,{label:row.label,front:row.front,back:row.back});
   if(row.variation_id)group.ids[row.size]=row.variation_id;
  }
  const groups=Array.from(colors.values());
  const maxColors=Math.floor(100/(variable?sizes.length:1));
  function sync(){
   field.value=JSON.stringify(groups.flatMap(({color,label,front,back,ids})=>
    (variable?sizes:['*']).map(size=>({color,label,size,front,back,variation_id:ids[size]??null}))));
   add.disabled=groups.length>=maxColors;
  }
  function imageButton(group,side){
   const button=document.createElement('button');
   button.type='button';button.className='button';
   function update(){button.textContent=group[side]?'Ganti gambar #'+group[side]:'Pilih gambar';}
   update();
   button.addEventListener('click',()=>{
    if(!window.wp||!wp.media)return;
    const frame=wp.media({title:side==='front'?'Mockup depan':'Mockup belakang',library:{type:'image'},multiple:false});
    frame.on('select',()=>{
     const selected=frame.state().get('selection').first().toJSON();
     if(!Number.isSafeInteger(selected.id)||selected.id<1)return;
     group[side]=selected.id;update();sync();
    });
    frame.open();
   });
   return button;
  }
  function variationFields(group,cell){
   if(!variable){cell.textContent='Produk simple';return;}
   for(const size of sizes){
    const label=document.createElement('label'),select=document.createElement('select');
    label.textContent=size+' ';
    const option=document.createElement('option');
    option.value='';option.textContent='Pilih variasi';select.append(option);
    for(const item of variations){
     const choice=document.createElement('option');
     choice.value=String(item.id);choice.disabled=!item.available;
     choice.textContent='#'+item.id+' '+Object.values(item.attributes).join(' / ')+(item.available?'':' (tidak tersedia)');
     select.append(choice);
    }
    select.value=String(group.ids[size]??'');
    select.addEventListener('change',()=>{
     group.ids[size]=select.value?Number(select.value):null;
     sync();
    });
    label.append(select);
    const row=document.createElement('div');row.append(label);cell.append(row);
   }
  }
  function render(){
   body.replaceChildren();
   for(const group of groups){
    const tr=document.createElement('tr');body.append(tr);
    function cell(){const td=document.createElement('td');tr.append(td);return td;}
    const color=document.createElement('input');
    color.type='color';color.value=group.color;color.setAttribute('aria-label','Warna mockup');cell().append(color);
    color.addEventListener('input',()=>{group.color=color.value;sync();});
    const label=document.createElement('input');
    label.type='text';label.value=group.label;label.maxLength=40;label.setAttribute('aria-label','Nama warna');cell().append(label);
    label.addEventListener('input',()=>{group.label=label.value;sync();});
    cell().append(imageButton(group,'front'));cell().append(imageButton(group,'back'));
    variationFields(group,cell());
    const remove=document.createElement('button');
    remove.type='button';remove.className='button';remove.textContent='Hapus warna';cell().append(remove);
    remove.addEventListener('click',()=>{groups.splice(groups.indexOf(group),1);sync();render();});
   }
  }
  add.addEventListener('click',()=>{
   if(groups.length>=maxColors)return;
   groups.push({color:'#ffffff',label:'Putih',front:0,back:0,ids:{}});
   sync();render();
  });
  add.disabled=groups.length>=maxColors;
  render();
 });
});
