(()=>{'use strict';document.querySelectorAll('[data-hsh-copy]').forEach(b=>b.addEventListener('click',async()=>{const status=b.parentElement.querySelector('[role=status]');try{await navigator.clipboard.writeText(b.dataset.hshCopy);status.textContent='URLをコピーしました。';}catch{status.textContent='コピーできませんでした。アドレス欄のURLをご利用ください。';}}));document.querySelectorAll('[data-hsh-share]').forEach(b=>{if(!navigator.share)return;b.hidden=false;b.addEventListener('click',async()=>{try{await navigator.share({title:document.title,url:b.parentElement.querySelector('[data-hsh-copy]').dataset.hshCopy});}catch(e){if(e.name!=='AbortError')b.parentElement.querySelector('[role=status]').textContent='共有できませんでした。URLのコピーをご利用ください。';}});});})();

/* Home photography is progressive enhancement: the first photo and links work without JS. */
(()=>{
 'use strict';
 const root=document.querySelector('[data-hsh-slider]');
 if(!root)return;
 const slides=[...root.querySelectorAll('[data-hsh-slide]')],links=[...root.querySelectorAll('[data-hsh-slide-link]')],dots=[...root.querySelectorAll('[data-hsh-slide-to]')];
 const status=root.querySelector('[data-hsh-slide-status]'),motion=matchMedia('(prefers-reduced-motion: reduce)');
 if(slides.length<2)return;
 let current=0,timer=0,primeTimer=0,paused=motion.matches,hovered=false,focused=false,onScreen=true,start=null,suppressClick=false;
 const loadSlide=i=>{const img=slides[i]?.querySelector('img[data-hsh-src]');if(!img)return;img.loading='eager';if(img.dataset.hshSrcset){img.srcset=img.dataset.hshSrcset;delete img.dataset.hshSrcset;}img.src=img.dataset.hshSrc;delete img.dataset.hshSrc;};
 const clear=()=>{clearTimeout(timer);clearTimeout(primeTimer);timer=0;};
 const schedule=()=>{clear();if(!paused&&(!hovered&&!focused)&&onScreen&&!document.hidden){primeTimer=setTimeout(()=>loadSlide((current+1)%slides.length),3000);timer=setTimeout(()=>show((current+1)%slides.length,false),7000);}};
 const show=(index,manual)=>{current=(index+slides.length)%slides.length;loadSlide(current);slides.forEach((slide,i)=>{slide.classList.toggle('is-active',i===current);slide.setAttribute('aria-hidden',String(i!==current));links[i].hidden=i!==current;dots[i].setAttribute('aria-pressed',String(i===current));});if(manual){paused=true;status.textContent=links[current].textContent.trim()+'の写真を表示しています。';}schedule();};
 root.querySelector('[data-hsh-slider-controls]').hidden=false;
 root.querySelector('[data-hsh-prev]').addEventListener('click',()=>show(current-1,true));
 root.querySelector('[data-hsh-next]').addEventListener('click',()=>show(current+1,true));
 dots.forEach((dot,i)=>dot.addEventListener('click',()=>show(i,true)));
 root.querySelectorAll('.hsh-slider-controls,.hsh-slide-links,.hsh-hero-copy a').forEach(el=>{el.addEventListener('mouseenter',()=>{hovered=true;clear();});el.addEventListener('mouseleave',()=>{hovered=false;schedule();});});
 root.addEventListener('focusin',()=>{focused=true;clear();});root.addEventListener('focusout',()=>{setTimeout(()=>{focused=root.contains(document.activeElement);schedule();},0);});
 root.addEventListener('keydown',e=>{if(e.key==='ArrowRight'||e.key==='ArrowLeft'){e.preventDefault();show(current+(e.key==='ArrowRight'?1:-1),true);}});
 root.addEventListener('pointerdown',e=>{if(e.pointerType==='touch')start={x:e.clientX,y:e.clientY};},{passive:true});
 root.addEventListener('pointercancel',()=>{start=null;});
 root.addEventListener('pointerup',e=>{if(!start)return;const x=e.clientX-start.x,y=e.clientY-start.y;start=null;if(Math.abs(x)>50&&Math.abs(x)>Math.abs(y)*1.3){suppressClick=true;show(current+(x<0?1:-1),true);setTimeout(()=>{suppressClick=false;},500);}},{passive:true});
 root.addEventListener('click',e=>{if(suppressClick){e.preventDefault();e.stopPropagation();}},true);
 document.addEventListener('visibilitychange',schedule);
 const changed=()=>{if(motion.matches)paused=true;schedule();};
 if(motion.addEventListener)motion.addEventListener('change',changed);else motion.addListener(changed);
 if('IntersectionObserver' in window){const observer=new IntersectionObserver(entries=>{onScreen=entries[0].isIntersecting;schedule();},{threshold:.1});observer.observe(root);}
 schedule();
 // Reveal only after the observer exists; content remains visible with JS disabled.
 if('IntersectionObserver' in window&&!motion.matches){const observer=new IntersectionObserver(entries=>{entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}});},{threshold:.05,rootMargin:'0px 0px 30px'});document.querySelectorAll('.hsh-front .hsh-featured,.hsh-front .hsh-topics,.hsh-front .hsh-articles,.hsh-front .hsh-about').forEach(el=>{el.classList.add('hsh-reveal-ready');observer.observe(el);});document.addEventListener('focusin',e=>e.target.closest('.hsh-reveal-ready')?.classList.add('is-visible'));}
})();
