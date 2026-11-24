'use strict';
jQuery(function($){
 $('.sanro-mockup-matrix').each(function(){
  const panel=this,field=panel.querySelector('input[name="_sanro_studio_mockup_matrix"]'),body=panel.querySelector('tbody'),add=panel.querySelector('[data-add-mapping]');
  let rows,sizes;try{rows=JSON.parse(field.value);sizes=JSON.parse(panel.dataset.sizes);}catch(_error){return;}
  if(!Array.isArray(rows)||!Array.isArray(sizes))return;
  function sync(){field.value=JSON.stringify(rows);add.disabled=rows.length>=100;}
  function render(){body.replaceChildren();for(const row of rows){
   const tr=document.createElement('tr');body.append(tr);
   function cell(){const td=document.createElement('td');tr.append(td);return td;}
   const color=document.createElement('input');color.type='color';color.value=row.color;cell().append(color);color.addEventListener('input',()=>{row.color=color.value;sync();});
   const label=document.createElement('input');label.type='text';label.value=row.label;label.maxLength=40;label.setAttribute('aria-label','Nama warna');cell().append(label);label.addEventListener('input',()=>{row.label=label.value;sync();});
   const size=document.createElement('select');for(const value of ['*',...sizes]){const option=document.createElement('option');option.value=value;option.textContent=value==='*'?'Semua ukuran':value;size.append(option);}size.value=row.size;cell().append(size);size.addEventListener('change',()=>{row.size=size.value;sync();});
   for(const side of ['front','back']){
    const td=cell(),button=document.createElement('button');button.type='button';button.className='button';button.textContent=row[side]?'Ganti gambar #'+row[side]:'Pilih gambar';td.append(button);
    button.addEventListener('click',()=>{if(!window.wp||!wp.media)return;const frame=wp.media({title:side==='front'?'Mockup depan':'Mockup belakang',library:{type:'image'},multiple:false});frame.on('select',()=>{const selected=frame.state().get('selection').first().toJSON();if(!Number.isSafeInteger(selected.id)||selected.id<1)return;row[side]=selected.id;button.textContent='Ganti gambar #'+selected.id;sync();});frame.open();});
   }
   const remove=document.createElement('button');remove.type='button';remove.className='button';remove.textContent='Hapus baris';cell().append(remove);remove.addEventListener('click',()=>{rows=rows.filter(r=>r!==row);sync();render();});
  }}
  add.addEventListener('click',()=>{if(rows.length>=100)return;rows.push({color:'#ffffff',label:'Putih',size:'*',front:0,back:0});sync();render();});render();sync();
 });
});
