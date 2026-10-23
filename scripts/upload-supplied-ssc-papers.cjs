const fs = require('node:fs');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const base = 'http://127.0.0.1:8001/api';
(async () => {
  const login = await fetch(`${base}/login`, {method:'POST', headers:{'Content-Type':'application/json',Accept:'application/json'}, body:JSON.stringify({email:'admin@nonax.test',password:process.env.NOVAX_UPLOAD_PASSWORD})});
  assert.equal(login.status,200);
  const {token} = await login.json();
  async function request(path, data) {
    const multipart = data instanceof FormData;
    const r = await fetch(base+path,{method:data?'POST':'GET',headers:{Authorization:`Bearer ${token}`,Accept:'application/json',...(!multipart && data?{'Content-Type':'application/json'}:{})},body:data?(multipart?data:JSON.stringify(data)):undefined});
    const body=await r.json();
    assert(r.ok,JSON.stringify(body));
    return body;
  }
  const course=(await request('/courses')).data.find(c=>c.code==='SSC-CGL');
  assert(course,'SSC CGL course missing');
  let subject=course.subjects.find(s=>s.code==='SSC-MIXED-PAPERS');
  if(!subject) subject=(await request('/management/subjects',{course_id:course.id,name:'Full-length Practice Papers (All Subjects)',code:'SSC-MIXED-PAPERS'})).data;
  const existing=[];
  let page=1, last=1;
  do { const result=(await request(`/management/materials?page=${page}&per_page=100`)).data; existing.push(...result.data); last=result.last_page; page++; } while(page<=last);
  for(const shift of [1,2,3]) {
    const name=`SSC-CGL-T-I-Similar-Paper-Held-on-12-Sep-2025-S${shift}-English.pdf`;
    const bytes=fs.readFileSync(`C:/Users/hp/Downloads/${name}`);
    assert.equal(bytes.subarray(0,5).toString(),'%PDF-');
    let record=existing.find(m=>m.course_id===course.id && m.file_name===name);
    if(!record) {
      const form=new FormData();
      for(const [key,value] of Object.entries({course_id:course.id,subject_id:subject.id,title:`SSC CGL Tier 1 Similar Paper - 12 Sep 2025 - Shift ${shift} - English (with answers)`,type:'pdf',year:2025}))form.append(key,String(value));
      form.append('file',new Blob([bytes],{type:'application/pdf'}),name);
      record=(await request('/management/materials',form)).data;
    }
    const stored=fs.readFileSync(`D:/NonaX/storage/app/public/${record.file_path}`);
    assert.equal(crypto.createHash('sha256').update(stored).digest('hex'),crypto.createHash('sha256').update(bytes).digest('hex'));
    const notes=(await request(`/notes?course_id=${course.id}&subject_id=${subject.id}`)).data.data;
    assert(notes.some(n=>n.id===record.id));
    console.log(JSON.stringify({id:record.id,title:record.title,bytes:stored.length,verified:true}));
  }
})().catch(e=>{console.error(e);process.exitCode=1;});
