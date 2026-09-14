(function(){
  'use strict';

  function normalize(value){
    return (value || '').toString().trim().toLocaleLowerCase('es')
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'');
  }

  function sortableValue(text){
    const value=text.trim();
    const date=value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2}))?/);
    if(date)return new Date(+date[3],+date[2]-1,+date[1],+(date[4]||0),+(date[5]||0)).getTime();

    const cleaned=value
      .replace(/US\$|ARS|USD|\$|%/gi,'')
      .replace(/\s/g,'')
      .replace(/\.(?=\d{3}(?:\D|$))/g,'')
      .replace(',','.');
    if(cleaned!=='' && /^-?\d+(?:\.\d+)?$/.test(cleaned))return Number(cleaned);

    const version=value.match(/^v(\d+)$/i);
    return version ? Number(version[1]) : normalize(value);
  }

  function enhance(table,index){
    if(table.dataset.erpTableReady==='1' || table.closest('form') || table.dataset.noTableTools!==undefined)return;
    const headers=Array.from(table.querySelectorAll('thead th'));
    const body=table.tBodies[0];
    if(!body || headers.length<2)return;

    const dataRows=()=>Array.from(body.rows).filter(row=>row.cells.length>1 && !row.querySelector('td[colspan]'));
    if(!dataRows().length)return;

    table.dataset.erpTableReady='1';
    const toolbar=document.createElement('div');
    toolbar.className='erp-table-tools';
    toolbar.innerHTML='<label class="erp-table-search"><span>Buscar</span><input type="search" placeholder="Buscar en esta tabla…" aria-label="Buscar en esta tabla"></label><span class="erp-table-count" aria-live="polite"></span>';
    table.parentNode.insertBefore(toolbar,table);

    const input=toolbar.querySelector('input');
    const count=toolbar.querySelector('.erp-table-count');
    function filter(){
      const query=normalize(input.value);
      let visible=0;
      dataRows().forEach(row=>{
        const show=!query || normalize(row.textContent).includes(query);
        row.hidden=!show;
        if(show)visible++;
      });
      count.textContent=visible+' registro'+(visible===1?'':'s');
    }
    input.addEventListener('input',filter);
    filter();

    headers.forEach((header,column)=>{
      const label=normalize(header.textContent);
      if(!label || /^(acciones?|opciones?)$/.test(label))return;
      header.classList.add('erp-sortable');
      header.tabIndex=0;
      header.setAttribute('role','button');
      header.title='Ordenar por '+header.textContent.trim();
      let direction=0;

      function sort(){
        direction=direction===1?-1:1;
        headers.forEach(other=>{if(other!==header){other.classList.remove('erp-sort-asc','erp-sort-desc');other.removeAttribute('aria-sort');}});
        header.classList.toggle('erp-sort-asc',direction===1);
        header.classList.toggle('erp-sort-desc',direction===-1);
        header.setAttribute('aria-sort',direction===1?'ascending':'descending');

        dataRows().sort((a,b)=>{
          const av=sortableValue(a.cells[column]?.textContent||'');
          const bv=sortableValue(b.cells[column]?.textContent||'');
          if(typeof av==='number' && typeof bv==='number')return (av-bv)*direction;
          return String(av).localeCompare(String(bv),'es',{numeric:true,sensitivity:'base'})*direction;
        }).forEach(row=>body.appendChild(row));
        filter();
      }

      header.addEventListener('click',sort);
      header.addEventListener('keydown',event=>{
        if(event.key==='Enter' || event.key===' '){event.preventDefault();sort();}
      });
    });
  }

  function init(){
    if(!document.getElementById('erp-table-tools-style')){
      const style=document.createElement('style');
      style.id='erp-table-tools-style';
      style.textContent='.erp-table-tools{display:flex;align-items:end;justify-content:space-between;gap:14px;margin:0 0 14px;min-width:260px}.erp-table-search{display:block;flex:1;max-width:440px;font-weight:650}.erp-table-search span{display:block;margin-bottom:6px}.erp-table-search input{width:100%;padding:10px 11px;border:1px solid #cbd1d7;border-radius:8px;background:#fff;font:inherit}.erp-table-count{color:var(--muted,#6e7781);white-space:nowrap}.erp-sortable{cursor:pointer;user-select:none;padding-right:24px!important;position:relative}.erp-sortable:after{content:"↕";position:absolute;right:7px;opacity:.35}.erp-sortable.erp-sort-asc:after{content:"↑";opacity:1;color:var(--o,#ff6702)}.erp-sortable.erp-sort-desc:after{content:"↓";opacity:1;color:var(--o,#ff6702)}tr[hidden]{display:none!important}@media(max-width:700px){.erp-table-tools{align-items:stretch;flex-direction:column}.erp-table-search{max-width:none}.erp-table-count{font-size:13px}}';
      document.head.appendChild(style);
    }
    document.querySelectorAll('table').forEach(enhance);
  }

  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);
  else init();
})();