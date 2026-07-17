import{c as l,_ as B,d as s,e as n,f as a,j as H,J as L,g as m,w as S,F as v,l as y,m as b,t as c,r as A,n as T,u as q,T as z,s as _,H as u,K as R,G as V,p as N}from"./index-CVxkDkiX.js";import{C as O}from"./check-C6F7n6Kf.js";import{S as P}from"./sprout-CMZDyhv0.js";import{B as D}from"./book-open-dTMdFpRp.js";import{R as E}from"./rotate-ccw-C55WsdBd.js";import{F as I}from"./flame-lHnJoWQ5.js";/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const J=l("biceps-flexed",[["path",{d:"M12.409 13.017A5 5 0 0 1 22 15c0 3.866-4 7-9 7-4.077 0-8.153-.82-10.371-2.462-.426-.316-.631-.832-.62-1.362C2.118 12.723 2.627 2 10 2a3 3 0 0 1 3 3 2 2 0 0 1-2 2c-1.105 0-1.64-.444-2-1",key:"1pmlyh"}],["path",{d:"M15 14a5 5 0 0 0-7.584 2",key:"5rb254"}],["path",{d:"M9.964 6.825C8.019 7.977 9.5 13 8 15",key:"kbvsx9"}]]);/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const $=l("coffee",[["path",{d:"M10 2v2",key:"7u0qdc"}],["path",{d:"M14 2v2",key:"6buw04"}],["path",{d:"M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1",key:"pwadti"}],["path",{d:"M6 2v2",key:"colzsn"}]]);/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const j=l("egg",[["path",{d:"M12 2C8 2 4 8 4 14a8 8 0 0 0 16 0c0-6-4-12-8-12",key:"1le142"}]]);/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const G=l("heart",[["path",{d:"M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5",key:"mvr1a0"}]]);/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const K=l("house",[["path",{d:"M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8",key:"5wwlr5"}],["path",{d:"M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z",key:"r6nss1"}]]);/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const W=l("plane",[["path",{d:"M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z",key:"1v9wt8"}]]),Y={class:"onb"},Q={class:"onb-top"},U={class:"progress-track onb-progress"},X={class:"intro-icon"},Z={class:"onb-text"},ee={class:"q-title"},ae={class:"options"},se=["onClick"],te={class:"opt-icon"},oe={class:"opt-label"},ne={class:"opt-check"},le=["disabled"],ie={__name:"OnboardingFlow",setup(ce){const f=V(),{savePrefs:g}=R(),t=_(0),o=_({goal:null,level:null,minutes:null}),r=[{key:"intro",kind:"intro",icon:N,title:"Tervetuloa!",text:"Three quick questions and we'll tune SaunaSpeak to you. You'll learn real spoken Finnish - what people actually say in shops, buses and saunas."},{key:"goal",kind:"choice",title:"Why are you learning Finnish?",options:[{value:"move",icon:K,label:"Moving to Finland"},{value:"travel",icon:W,label:"Travel & visits"},{value:"family",icon:G,label:"Family & friends"},{value:"casual",icon:P,label:"Just curious"}]},{key:"level",kind:"choice",title:"How much Finnish do you know?",options:[{value:"none",icon:j,label:"Absolute beginner"},{value:"some",icon:D,label:"A few words"},{value:"rusty",icon:E,label:"Rusty - brushing up"}]},{key:"minutes",kind:"choice",title:"How much time per day?",options:[{value:2,icon:$,label:"2 min - a taste"},{value:5,icon:I,label:"5 min - steady"},{value:15,icon:J,label:"15 min - serious"}]}],e=u(()=>r[t.value]),d=u(()=>t.value===r.length-1),w=u(()=>Math.round(t.value/(r.length-1)*100)),C=u(()=>e.value.kind==="intro"?!0:o.value[e.value.key]!=null);function M(h,k){o.value[h]=k,d.value||setTimeout(p,180)}function p(){if(d.value)return x();t.value++}function F(){t.value>0&&t.value--}function x(){g({goal:o.value.goal,level:o.value.level,minutes:o.value.minutes}),f.push({name:o.value.level==="none"?"session":"dashboard"})}return(h,k)=>(s(),n("div",Y,[a("div",Q,[t.value>0?(s(),n("button",{key:0,class:"back",onClick:F,"aria-label":"Back"},"‹")):H("",!0),a("div",U,[a("div",{class:"progress-fill",style:L({width:w.value+"%"})},null,4)])]),m(z,{name:"fade",mode:"out-in"},{default:S(()=>[(s(),n("div",{key:e.value.key,class:"onb-step"},[e.value.kind==="intro"?(s(),n(v,{key:0},[a("div",X,[(s(),y(b(e.value.icon),{class:"intro-ico","aria-hidden":"true"}))]),a("h1",null,c(e.value.title),1),a("p",Z,c(e.value.text),1)],64)):(s(),n(v,{key:1},[a("h1",ee,c(e.value.title),1),a("div",ae,[(s(!0),n(v,null,A(e.value.options,i=>(s(),n("button",{key:i.value,class:T(["option",{selected:o.value[e.value.key]===i.value}]),onClick:ue=>M(e.value.key,i.value)},[a("span",te,[(s(),y(b(i.icon),{class:"opt-ico","aria-hidden":"true"}))]),a("span",oe,c(i.label),1),a("span",ne,[m(q(O),{class:"check-ico","aria-hidden":"true"})])],10,se))),128))])],64))]))]),_:1}),a("button",{class:"btn btn-primary btn-block onb-cta",disabled:!C.value,onClick:p},c(e.value.kind==="intro"?"Let's go":d.value?"Start my first session":"Continue"),9,le)]))}},me=B(ie,[["__scopeId","data-v-f0349989"]]);export{me as default};
