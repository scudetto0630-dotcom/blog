(()=>{
'use strict';
// This is budget arithmetic, not a tax calculation. Null means not yet known.
function estimate(sales,cost,tax,reserve){
 const required=[sales,cost],optional=[tax,reserve];
 if(required.some(n=>!Number.isSafeInteger(n)||n<0||n>999999999)||optional.some(n=>n!==null&&(!Number.isSafeInteger(n)||n<0||n>999999999)))return null;
 const afterCosts=sales-cost,complete=tax!==null&&reserve!==null;
 return {afterCosts,complete,available:complete?afterCosts-tax-reserve:null};
}
if(typeof module!=='undefined'&&module.exports)module.exports={estimate};
if(typeof document==='undefined')return;
for(const app of document.querySelectorAll('[data-budget-app]')){
 const form=app.querySelector('[data-budget-form]'),out=app.querySelector('[data-budget-result]');
 if(!form||!out)continue;
 const fields=['sales','cost','tax','reserve'].map(k=>form.querySelector('[data-budget-field="'+k+'"]'));
 if(fields.some(f=>!f))continue;
 const initial=out.textContent,format=n=>n.toLocaleString('ja-JP')+'円';
 const value=f=>f.value.trim()===''?null:Number(f.value);
 const clearResult=()=>{out.textContent=initial;};
 form.addEventListener('submit',e=>{
  e.preventDefault();
  if(!form.reportValidity())return;
  const result=estimate(...fields.map(value));
  if(!result){out.textContent='売上と費用は必須です。金額は0〜999,999,999円の整数で入力してください。';return;}
  if(!result.complete){out.textContent='仕事の費用を引いた額：'+format(result.afterCosts)+'。税・保険用、または事業用の予算が未入力のため、生活に回す予算は未計算です。';return;}
  out.textContent='仕事の費用を引いた額：'+format(result.afterCosts)+'。生活に回す予算：'+format(result.available)+'。'+(result.available<0?'入力した費用と残す予算が、売上を上回っています。':'')+'これは予算の計算です。未入金の売上を、今使える現金として数えないでください。';
 });
 form.addEventListener('reset',clearResult);
 // Never retain a stale answer when an amount changes.
 form.addEventListener('input',clearResult);
 app.hidden=false;
}
})();
