(()=>{
'use strict';
const cfg=window.EDISDiagnosticAdmin||{};
const strings=cfg.strings||{};
let lastDiagnosticError=null;
const esc=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
function diagnosticData(payload,response){
 const data=payload&&typeof payload.data==='object'&&payload.data!==null?payload.data:{};
 if(!Object.prototype.hasOwnProperty.call(data,'diagnostic_available'))return null;
 return {
  publicCode:String(payload.code||''),
  message:String(payload.message||''),
  httpStatus:Number(data.status||response.status||0),
  diagnosticAvailable:Boolean(data.diagnostic_available),
  diagnosticId:typeof data.diagnostic_id==='string'?data.diagnostic_id:null,
  diagnosticsUrl:typeof data.diagnostics_url==='string'?data.diagnostics_url:null,
  persistenceCode:typeof data.diagnostic_persistence_code==='string'?data.diagnostic_persistence_code:null,
  safeResponseMetadata:data,
  observedAt:Date.now()
 };
}
function target(){
 let node=document.querySelector('#edis-diagnostic-error');
 if(node)return node;
 node=document.createElement('div');
 node.id='edis-diagnostic-error';
 node.setAttribute('aria-live','assertive');
 const form=document.querySelector('#edis-export-form');
 const job=document.querySelector('#edis-job-panel');
 const anchor=form||job||document.querySelector('.edis-admin');
 if(anchor&&anchor.parentNode)anchor.parentNode.insertBefore(node,anchor);
 return node;
}
function render(error){
 const node=target();
 if(!node)return;
 const id=error.diagnosticId;
 const url=error.diagnosticsUrl||(id?`${cfg.diagnosticsUrl}&diagnostic_id=${encodeURIComponent(id)}`:cfg.diagnosticsUrl);
 const controls=error.diagnosticAvailable&&id
  ?`<p><strong>${esc(strings.diagnosticId||'Diagnostic ID')}:</strong> <code>${esc(id)}</code></p><p><button type="button" class="button" data-edis-copy-diagnostic-id="${esc(id)}">${esc(strings.copyId||'Copy ID')}</button> <a class="button button-primary" href="${esc(url)}">${esc(strings.openDiagnostics||'Open Diagnostics')}</a></p>`
  :`<p><code>${esc(error.persistenceCode||'EDIS_DIAGNOSTIC_PERSISTENCE_FAILED')}</code> — ${esc(strings.artifactUnavailable||'EDIS could not persist a diagnostic artifact for this failure.')}</p>`;
 node.innerHTML=`<div class="notice notice-error inline"><p>${esc(error.message)}</p>${controls}</div>`;
 node.querySelector('[data-edis-copy-diagnostic-id]')?.addEventListener('click',async event=>{
  const value=event.currentTarget.getAttribute('data-edis-copy-diagnostic-id')||'';
  try{await navigator.clipboard.writeText(value);window.alert(strings.copied||'Diagnostic ID copied.');}
  catch(e){window.prompt(strings.copyId||'Copy ID',value);}
 });
}
const originalFetch=window.fetch.bind(window);
window.fetch=async(...args)=>{
 const response=await originalFetch(...args);
 try{
  const requestUrl=typeof args[0]==='string'?args[0]:(args[0]&&args[0].url)||'';
  if(!response.ok&&String(requestUrl).includes('/edis-evidence-exporter/v3/')){
   const payload=await response.clone().json();
   const error=diagnosticData(payload,response);
   if(error){
    lastDiagnosticError=error;
    window.EDISLastDiagnosticError=error;
    render(error);
    window.dispatchEvent(new CustomEvent('edis:diagnostic-error',{detail:error}));
   }
  }
 }catch(e){}
 return response;
};
const originalAlert=window.alert.bind(window);
window.alert=message=>{
 if(lastDiagnosticError&&Date.now()-lastDiagnosticError.observedAt<3000&&String(message)===lastDiagnosticError.message){
  render(lastDiagnosticError);
  return;
 }
 originalAlert(message);
};
document.addEventListener('click',async event=>{
 const button=event.target.closest('[data-edis-action="copy-canonical-diagnostic"]');
 if(!button)return;
 const text=document.querySelector('#edis-canonical-diagnostic-json')?.textContent||'';
 try{await navigator.clipboard.writeText(text);originalAlert(strings.copyJson||'Diagnostic JSON copied.');}
 catch(e){window.prompt(strings.copyJson||'Copy diagnostic JSON',text);}
});
})();
