import{c as l,_ as B,d as s,e as o,f as a,j as q,I as A,g as y,w as H,F as p,l as b,m as _,t as c,r as L,n as S,u as T,T as z,q as f,G as r,J as R,E as V}from"./index-DjVmvwzK.js";import{_ as E}from"./LoylyIcon-B8KHK8TI.js";import{C as I}from"./check-BE7kFGoU.js";import{S as N}from"./sprout-2jLJ-OGR.js";import{B as O}from"./book-open-qAZV6oID.js";import{R as P}from"./rotate-ccw-DJDHAMcl.js";import{F as D}from"./flame-DXAWg0Tr.js";/**
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
 */const W=l("house",[["path",{d:"M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8",key:"5wwlr5"}],["path",{d:"M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z",key:"r6nss1"}]]);/**
 * @license lucide-vue-next v1.0.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Y=l("plane",[["path",{d:"M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z",key:"1v9wt8"}]]),K={class:"onb"},Q={class:"onb-top"},U={class:"progress-track onb-progress"},X={class:"intro-icon"},Z={class:"onb-text"},ee={class:"q-title"},ae={class:"options"},se=["onClick"],te={class:"opt-icon"},ne={class:"opt-label"},oe={class:"opt-check"},le=["disabled"],ie={__name:"OnboardingFlow",setup(ce){const h=V(),{savePrefs:g}=R(),t=f(0),n=f({goal:null,level:null,minutes:null}),d=[{key:"intro",kind:"intro",icon:E,title:"Tervetuloa!",text:"Three quick questions and we'll tune SaunaSpeak to you. You'll learn real spoken Finnish - what people actually say in shops, buses and saunas."},{key:"goal",kind:"choice",title:"Why are you learning Finnish?",options:[{value:"move",icon:W,label:"Moving to Finland"},{value:"travel",icon:Y,label:"Travel & visits"},{value:"family",icon:G,label:"Family & friends"},{value:"casual",icon:N,label:"Just curious"}]},{key:"level",kind:"choice",title:"How much Finnish do you know?",options:[{value:"none",icon:j,label:"Absolute beginner"},{value:"some",icon:O,label:"A few words"},{value:"rusty",icon:P,label:"Rusty - brushing up"}]},{key:"minutes",kind:"choice",title:"How much time per day?",options:[{value:2,icon:$,label:"2 min - a taste"},{value:5,icon:D,label:"5 min - steady"},{value:15,icon:J,label:"15 min - serious"}]}],e=r(()=>d[t.value]),v=r(()=>t.value===d.length-1),w=r(()=>Math.round(t.value/(d.length-1)*100)),C=r(()=>e.value.kind==="intro"?!0:n.value[e.value.key]!=null);function M(u,m){n.value[u]=m,v.value||setTimeout(k,180)}function k(){if(v.value)return x();t.value++}function F(){t.value>0&&t.value--}function x(){const u=n.value.level==="none";g({goal:n.value.goal,level:n.value.level,minutes:n.value.minutes},{placement:!1}),u?h.push({name:"session"}):h.push({name:"checkpoint",params:{level:"A1"},query:{intake:"1"}})}return(u,m)=>(s(),o("div",K,[a("div",Q,[t.value>0?(s(),o("button",{key:0,class:"back",onClick:F,"aria-label":"Back"},"‹")):q("",!0),a("div",U,[a("div",{class:"progress-fill",style:A({width:w.value+"%"})},null,4)])]),y(z,{name:"fade",mode:"out-in"},{default:H(()=>[(s(),o("div",{key:e.value.key,class:"onb-step"},[e.value.kind==="intro"?(s(),o(p,{key:0},[a("div",X,[(s(),b(_(e.value.icon),{class:"intro-ico","aria-hidden":"true"}))]),a("h1",null,c(e.value.title),1),a("p",Z,c(e.value.text),1)],64)):(s(),o(p,{key:1},[a("h1",ee,c(e.value.title),1),a("div",ae,[(s(!0),o(p,null,L(e.value.options,i=>(s(),o("button",{key:i.value,class:S(["option",{selected:n.value[e.value.key]===i.value}]),onClick:ue=>M(e.value.key,i.value)},[a("span",te,[(s(),b(_(i.icon),{class:"opt-ico","aria-hidden":"true"}))]),a("span",ne,c(i.label),1),a("span",oe,[y(T(I),{class:"check-ico","aria-hidden":"true"})])],10,se))),128))])],64))]))]),_:1}),a("button",{class:"btn btn-primary btn-block onb-cta",disabled:!C.value,onClick:k},c(e.value.kind==="intro"?"Let's go":v.value?"Start my first session":"Continue"),9,le)]))}},ye=B(ie,[["__scopeId","data-v-0d79715d"]]);export{ye as default};
