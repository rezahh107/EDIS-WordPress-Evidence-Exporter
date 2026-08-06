(()=>{'use strict';
const cfg=window.EDISDiagnosticAdmin||{},strings=cfg.strings||{};
const own=(value,key)=>Object.prototype.hasOwnProperty.call(value,key);
const object=value=>value!==null&&typeof value==='object'&&!Array.isArray(value);
const pick=(value,snake,camel)=>{
  const hasSnake=own(value,snake),hasCamel=own(value,camel);
  if(hasSnake&&hasCamel&&value[snake]!==value[camel])return{conflict:true,value:null};
  return{conflict:false,value:hasSnake?value[snake]:(hasCamel?value[camel]:undefined)};
};
const validId=value=>typeof value==='string'&&/^edis-diag-[a-f0-9]{32}$/.test(value);
const validCode=value=>typeof value==='string'&&/^[A-Z0-9_:-]{1,128}$/.test(value);
const validUrl=value=>value===null||value===undefined||value===''||typeof value==='string';
const sameOriginUrl=value=>{
  if(value===null||value===undefined||value==='')return true;
  if(typeof value!=='string')return false;
  try{
    const url=new URL(value,window.location?.href||'http://localhost/');
    return !window.location?.origin||url.origin===window.location.origin;
  }catch(error){return false;}
};
function diagnosticData(payload){
  if(!object(payload))return null;
  const outer=payload,data=object(payload.data)?payload.data:payload;
  const available=pick(data,'diagnostic_available','diagnosticAvailable');
  const id=pick(data,'diagnostic_id','diagnosticId');
  const persistence=pick(data,'diagnostic_persistence_code','diagnosticPersistenceCode');
  const url=pick(data,'diagnostics_url','diagnosticsUrl');
  const metadata=pick(data,'safe_response_metadata','safeResponseMetadata');
  if([available,id,persistence,url,metadata].some(item=>item.conflict))return null;
  if(available.value===undefined&&id.value===undefined&&persistence.value===undefined&&url.value===undefined)return null;
  if(typeof available.value!=='boolean'||!validUrl(url.value)||metadata.value!==undefined&&!object(metadata.value))return null;
  const publicCode=typeof outer.code==='string'?outer.code:(typeof data.public_code==='string'?data.public_code:(typeof data.publicCode==='string'?data.publicCode:null));
  const httpStatus=Number(object(outer.data)?outer.data.status:(data.http_status??data.httpStatus??0))||0;
  return{
    diagnosticAvailable:available.value,
    diagnosticId:id.value===undefined?null:id.value,
    diagnosticPersistenceCode:persistence.value===undefined?null:persistence.value,
    diagnosticsUrl:url.value||null,
    safeResponseMetadata:metadata.value||{},
    publicCode,
    httpStatus
  };
}
function classify(payload){
  const data=diagnosticData(payload);if(!data)return null;
  if(data.diagnosticAvailable===true){
    if(!validId(data.diagnosticId)||data.diagnosticPersistenceCode!==null||!sameOriginUrl(data.diagnosticsUrl))return null;
    return{state:'AVAILABLE',...data};
  }
  if(data.diagnosticId!==null||data.diagnosticsUrl!==null||!validCode(data.diagnosticPersistenceCode))return null;
  return{state:data.diagnosticPersistenceCode==='EDIS_DIAGNOSTIC_CAPACITY_REACHED'?'CAPACITY':'UNAVAILABLE',...data};
}
function diagnosticLink(result){
  const base=result.diagnosticsUrl||cfg.diagnosticsUrl||'';
  if(typeof base!=='string'||base==='')return null;
  try{
    const url=new URL(base,window.location?.href||'http://localhost/');
    if(window.location?.origin&&url.origin!==window.location.origin)return null;
    url.searchParams.set('diagnostic_id',result.diagnosticId);
    return url.toString();
  }catch(error){return null;}
}
function render(target,result){
  if(!target||!result)return false;
  const notice=document.createElement('div');notice.className='notice notice-error inline edis-diagnostic-envelope';
  const message=document.createElement('p');
  if(result.state==='AVAILABLE')message.textContent=strings.diagnosticAvailable||'A canonical diagnostic record is available for this failure.';
  else if(result.state==='CAPACITY')message.textContent=strings.diagnosticCapacity||'Diagnostic capacity was reached; no artifact was created.';
  else message.textContent=strings.diagnosticUnavailable||'EDIS could not persist a diagnostic artifact for this failure.';
  notice.appendChild(message);
  if(result.state==='AVAILABLE'){
    const identity=document.createElement('p');
    const code=document.createElement('code');code.textContent=result.diagnosticId;identity.appendChild(code);notice.appendChild(identity);
    const href=diagnosticLink(result);
    if(href){const link=document.createElement('a');link.className='button button-secondary';link.href=href;link.textContent=strings.openDiagnostics||'Open Diagnostics';notice.appendChild(link);}
  }else{
    const identity=document.createElement('p');const code=document.createElement('code');code.textContent=result.diagnosticPersistenceCode;identity.appendChild(code);notice.appendChild(identity);
  }
  target.replaceChildren(notice);return true;
}
window.EDISDiagnosticEnvelope={classify,diagnosticData,render};
})();
