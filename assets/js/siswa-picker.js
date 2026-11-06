(function(){
  'use strict';
  function esc(v){return String(v??'').replace(/[&<>'"]/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[m]});}
  function init(root){
    let items=[];
    try{items=JSON.parse(root.getAttribute('data-items')||'[]')}catch(e){items=[]}
    const hidden=root.querySelector('[data-student-value]');
    const search=root.querySelector('[data-student-search]');
    const cls=root.querySelector('[data-student-class]');
    const results=root.querySelector('[data-student-results]');
    const selected=root.querySelector('[data-student-selected]');
    const empty=root.querySelector('[data-student-empty]');
    const initial=String(hidden?.value||'0');
    const toolbar=root.querySelector('.student-picker-toolbar');
    root.classList.toggle('student-picker-has-toolbar', !!toolbar);

    const classMap=new Map();
    items.forEach(x=>{
      const key=String(x.kelas_id??x.kelas??'').trim();
      const label=String(x.kelas_nama??x.kelas??'').trim();
      if(key)classMap.set(key,label||key);
    });
    const classes=[...classMap.entries()].sort((a,b)=>a[1].localeCompare(b[1],'id',{numeric:true}));
    if(cls){
      classes.forEach(([value,label])=>{
        const o=document.createElement('option');
        o.value=value;o.textContent=label;cls.appendChild(o);
      });
    }

    // Compact helper is created once so selecting a class does not dump a long list
    // into the form. The user searches first, then sees a short dropdown of matches.
    let summary=root.querySelector('[data-student-summary]');
    if(!summary){
      summary=document.createElement('div');
      summary.className='student-picker-summary';
      summary.setAttribute('data-student-summary','');
      if(toolbar)toolbar.insertAdjacentElement('afterend',summary);
      else if(search?.parentElement)search.parentElement.insertAdjacentElement('afterend',summary);
    }

    function current(){return items.find(x=>String(x.id)===String(hidden?.value||''))||null}
    function classItems(){
      const c=String(cls?.value||'');
      return c?items.filter(x=>String(x.kelas_id??x.kelas??'')===c):[];
    }
    function renderSelected(){
      const s=current();
      if(!selected)return;
      if(s){
        selected.innerHTML='<div class="student-picker-selected-card"><div class="student-picker-avatar"><i class="bi bi-person-fill"></i></div><div><strong>'+esc(s.nama)+'</strong><div>'+esc(s.nis||'-')+' · '+esc(s.kelas_nama??s.kelas??'Belum ada kelas')+'</div></div><button type="button" class="student-picker-clear" aria-label="Ganti siswa"><i class="bi bi-x-lg"></i></button></div>';
        selected.querySelector('button').onclick=function(){
          hidden.value='0';
          if(search)search.value='';
          renderSelected();
          renderResults();
          if(search && cls?.value){search.focus();}
        };
      }else selected.innerHTML='';
    }
    function setEmptyState(show){if(empty)empty.hidden=!show;}
    function renderResults(){
      if(!results)return;
      const q=String(search?.value||'').trim().toLowerCase();
      const c=String(cls?.value||'');
      const available=classItems();

      if(search)search.disabled=!c;
      results.innerHTML='';
      results.hidden=true;
      setEmptyState(false);

      if(!c){
        summary.textContent='Pilih kelas terlebih dahulu.';
        summary.className='student-picker-summary is-muted';
        return;
      }

      summary.className='student-picker-summary';
      summary.innerHTML='<i class="bi bi-people-fill"></i> '+available.length+' siswa di kelas <strong>'+esc(classMap.get(c)||c)+'</strong> · ketik nama, NIS, atau NISN untuk memilih.';

      // Important UX rule: choosing a class does NOT render all students.
      // Results only appear after a meaningful search term is entered.
      if(q.length<2)return;

      let found=available.filter(x=>[x.nama,x.nis,x.nisn].some(v=>String(v||'').toLowerCase().includes(q)));
      found=found.slice(0,8);
      results.hidden=false;
      if(!found.length){
        setEmptyState(true);
        return;
      }
      found.forEach(x=>{
        const b=document.createElement('button');
        b.type='button';
        b.className='student-picker-result';
        b.innerHTML='<span class="student-picker-avatar"><i class="bi bi-person"></i></span><span><strong>'+esc(x.nama)+'</strong><small>'+esc(x.nis||'-')+' · '+esc(x.nisn||'-')+' · '+esc(x.kelas_nama??x.kelas??'Belum ada kelas')+'</small></span><i class="bi bi-chevron-right"></i>';
        b.onclick=function(){
          hidden.value=String(x.id);
          search.value='';
          results.hidden=true;
          renderSelected();
          renderResults();
        };
        results.appendChild(b);
      });
      if(available.length>8){
        const note=document.createElement('div');
        note.className='student-picker-more';
        note.textContent='Menampilkan maksimal 8 hasil. Persempit pencarian untuk hasil lainnya.';
        results.appendChild(note);
      }
    }

    if(search)search.addEventListener('input',renderResults);
    if(cls)cls.addEventListener('change',function(){
      if(hidden)hidden.value='0';
      if(search)search.value='';
      renderSelected();
      renderResults();
    });

    if(initial && initial!=='0' && hidden)hidden.value=initial;
    const initialStudent=current();
    if(cls && initialStudent){
      const initialClass=initialStudent.kelas_id??initialStudent.kelas??'';
      if(initialClass)cls.value=String(initialClass);
    }
    renderSelected();
    renderResults();
  }
  document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('[data-student-picker]').forEach(init)});
})();
