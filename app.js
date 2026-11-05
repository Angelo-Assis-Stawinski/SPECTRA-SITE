async function api(action, options={}) {
  const url='api.php?action='+encodeURIComponent(action);
  const opts={credentials:'same-origin',...options};
  if (opts.body && !(opts.body instanceof FormData) && typeof opts.body!=='string') {
    opts.headers={...(opts.headers||{}),'Content-Type':'application/json'};
    opts.body=JSON.stringify(opts.body);
  }
  const r=await fetch(url,{cache:'no-store',...opts}); let data={}; try{data=await r.json();}catch(_){data={ok:false,error:'Resposta inválida do servidor'};}
  if(!r.ok || data.ok===false) throw new Error(data.error||('HTTP '+r.status)); return data;
}
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function fmtBytes(n){n=Number(n||0);if(n<1024)return n+' B';if(n<1048576)return (n/1024).toFixed(1)+' KB';if(n<1073741824)return (n/1048576).toFixed(1)+' MB';return (n/1073741824).toFixed(1)+' GB';}
async function refreshAccount(){try{const d=await api('me');document.querySelectorAll('[data-account-link]').forEach(a=>{if(d.user){a.textContent=d.user.role==='admin'?'Painel Admin':'Minha conta';a.href=d.user.role==='admin'?'admin.html':'conta.html';}else{a.textContent='Entrar / Criar conta';a.href='conta.html';}});}catch(_){}}
async function refreshMessages(){try{const d=await api('messages');if(d.messages?.length){const host=document.querySelector('.content,.portal-content');if(host){const box=document.createElement('div');box.className='note';box.innerHTML=d.messages.map(m=>`<strong>${esc(m.title)}</strong><p>${esc(m.body)}</p>`).join('');host.prepend(box);}}}catch(_){}}
document.addEventListener('DOMContentLoaded',()=>{refreshAccount();refreshMessages();});

function safeAttachmentUrl(value) {
  const url = String(value || '');
  if (/^uploads\/[a-f0-9]{16,32}\.[a-z0-9]+$/.test(url)) return 'download.php?type=attachment&f=' + url.slice(8);
  if (/^download\.php\?type=attachment&f=[a-f0-9]{16,32}\.[a-z0-9]+$/.test(url)) return url;
  return '';
}
