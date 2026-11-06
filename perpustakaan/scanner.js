(function(){
'use strict';
const CDN='jsQR.js';
let stream=null,raf=null,detector=null,decoderPromise=null,lastCode='',lastAt=0;
const $=id=>document.getElementById(id);
function status(text,type){const el=$('scanStatus');if(!el)return;el.className='scan-status '+(type||'neutral');el.textContent=text}
async function loadDecoder(){
 if(window.BarcodeDetector){status('Pembaca QR: BarcodeDetector tersedia.','success');return true}
 if(window.jsQR){status('Pembaca QR: jsQR fallback siap.','success');return true}
 if(decoderPromise)return decoderPromise;
 decoderPromise=new Promise(resolve=>{
   status(navigator.onLine?'Menyiapkan pembaca QR fallback…':'Browser tidak memiliki pembaca QR bawaan. Gunakan scanner USB atau buka saat online untuk menyiapkan fallback.','neutral');
   const s=document.createElement('script');s.src=new URL(CDN,document.baseURI).href;s.async=true;
   s.onload=()=>{const ok=!!window.jsQR;status(ok?'Pembaca QR: jsQR fallback siap.':'Fallback QR gagal dimuat.','success');resolve(ok)};
   s.onerror=()=>{status('Fallback QR tidak tersedia. Gunakan scanner USB/manual.','error');resolve(false)};
   document.head.appendChild(s);
 });
 return decoderPromise;
}
function stop(){if(stream)stream.getTracks().forEach(t=>t.stop());stream=null;if(raf)cancelAnimationFrame(raf);raf=null;const r=$('reader');if(r)r.innerHTML='<div class="scan-placeholder"><strong>Kamera dihentikan</strong><span>Scanner USB/manual tetap dapat digunakan.</span></div>';const b=$('startCamera');if(b)b.disabled=false}
async function detect(){const sel=$('cameraSelect');if(!sel)return;if(!navigator.mediaDevices?.getUserMedia){status('Browser tidak menyediakan akses kamera.','error');return false}try{const p=await navigator.mediaDevices.getUserMedia({video:true,audio:false});p.getTracks().forEach(t=>t.stop());const ds=await navigator.mediaDevices.enumerateDevices();const cams=ds.filter(d=>d.kind==='videoinput');sel.innerHTML='';cams.forEach((d,i)=>{const o=document.createElement('option');o.value=d.deviceId;o.textContent=d.label||('Kamera '+(i+1));sel.appendChild(o)});sel.disabled=!cams.length;if($('cameraCount'))$('cameraCount').textContent=cams.length+' kamera terdeteksi';return !!cams.length}catch(e){status('Izin kamera belum tersedia atau kamera tidak dapat dibaca.','error');return false}}
async function decode(video,canvas,ctx,crop,cropCtx){let code=null;if(window.BarcodeDetector){try{detector=detector||new BarcodeDetector({formats:['qr_code']});const n=await detector.detect(canvas);if(n.length)code=n[0].rawValue}catch(e){}}if(!code&&window.jsQR){try{const im=ctx.getImageData(0,0,canvas.width,canvas.height),x=window.jsQR(im.data,im.width,im.height,{inversionAttempts:'attemptBoth'});if(x)code=x.data}catch(e){}if(!code){const side=Math.floor(Math.min(video.videoWidth,video.videoHeight)*.82);if(side>0){crop.width=Math.min(960,Math.max(320,side));crop.height=crop.width;cropCtx.drawImage(video,(video.videoWidth-side)/2,(video.videoHeight-side)/2,side,side,0,0,crop.width,crop.height);try{const im=cropCtx.getImageData(0,0,crop.width,crop.height),x=window.jsQR(im.data,im.width,im.height,{inversionAttempts:'attemptBoth'});if(x)code=x.data}catch(e){}}}}return code}
async function start(onCode){if(!navigator.mediaDevices?.getUserMedia){status('Kamera tidak didukung browser.','error');return}if(!$('cameraSelect')?.value){if(!(await detect()))return}if(!(await loadDecoder())){status('Pembaca QR belum tersedia. Gunakan scanner USB/manual.','error');return}stop();try{stream=await navigator.mediaDevices.getUserMedia({video:{deviceId:{exact:$('cameraSelect').value},width:{ideal:1280},height:{ideal:720}},audio:false});const r=$('reader');r.innerHTML='';const v=document.createElement('video');v.autoplay=true;v.playsInline=true;v.muted=true;v.srcObject=stream;r.appendChild(v);if($('startCamera'))$('startCamera').disabled=true;const c=document.createElement('canvas'),ctx=c.getContext('2d',{willReadFrequently:true}),crop=document.createElement('canvas'),cropCtx=crop.getContext('2d',{willReadFrequently:true});let busy=false,lastFrame=0;const tick=async()=>{if(!stream)return;const now=performance.now();if(now-lastFrame<120||busy){raf=requestAnimationFrame(tick);return}lastFrame=now;try{if(v.readyState>=2&&v.videoWidth){const scale=Math.min(1,960/v.videoWidth);c.width=Math.max(320,Math.round(v.videoWidth*scale));c.height=Math.max(240,Math.round(v.videoHeight*scale));ctx.drawImage(v,0,0,c.width,c.height);busy=true;const code=await decode(v,c,ctx,crop,cropCtx);busy=false;if(code&&(code!==lastCode||Date.now()-lastAt>2500)){lastCode=code;lastAt=Date.now();await onCode(code)}}}catch(e){busy=false}raf=requestAnimationFrame(tick)};tick();status(navigator.onLine?'Kamera aktif. Arahkan QR ke kamera.':'Kamera aktif dalam mode offline.','success')}catch(e){status('Kamera tidak dapat digunakan. Pastikan izin kamera dan perangkat kamera benar.','error')}}
function bindUsb(inputId,onCode){
 const input=$(inputId); if(!input||typeof onCode!=='function') return ()=>{};
 let buffer='',timer=null,lastAt=0,busy=false;
 const GAP=90,FLUSH=180;
 const clear=()=>{buffer='';if(timer){clearTimeout(timer);timer=null}};
 const fire=(value)=>{value=(value||'').trim();clear();if(!value||busy)return;busy=true;Promise.resolve(onCode(value)).finally(()=>{busy=false;if(input){input.value='';input.focus()}})};
 const schedule=()=>{if(timer)clearTimeout(timer);timer=setTimeout(()=>{if(buffer.trim().length>=3)fire(buffer);else clear()},FLUSH)};
 const keydown=e=>{
   if(e.target===input){
     if(e.key==='Enter'){e.preventDefault();fire(input.value);}
     return;
   }
   const t=e.target;
   if(t&&(t.matches?.('textarea,select,button,[contenteditable="true"]')))return;
   if(e.ctrlKey||e.altKey||e.metaKey)return;
   const now=performance.now();
   if(e.key==='Enter'){if(buffer){e.preventDefault();fire(buffer)}return;}
   if(typeof e.key!=='string'||e.key.length!==1)return;
   if(buffer && now-lastAt>GAP)clear();
   buffer+=e.key;lastAt=now;schedule();
 };
 const inputHandler=()=>{const v=input.value.trim();if(!v)return;if(timer)clearTimeout(timer);timer=setTimeout(()=>{if(input.value.trim().length>=3)fire(input.value);},FLUSH)};
 document.addEventListener('keydown',keydown,true);input.addEventListener('input',inputHandler);
 input.addEventListener('blur',()=>{if(buffer)clear()});
 input.focus();
 return ()=>{document.removeEventListener('keydown',keydown,true);input.removeEventListener('input',inputHandler);clear()};
}
window.PerpusScanner={start,stop,detect,loadDecoder,bindUsb};
})();
