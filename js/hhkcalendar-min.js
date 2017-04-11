/**
 * hhkcalendar-min.js
 *
 * @preserve
 * FullCalendar v1.5.4
 * http://arshaw.com/fullcalendar/
 * 
 * Use fullcalendar.css for basic styling.
 * For event drag & drop, requires jQuery UI draggable.
 * For event resizing, requires jQuery UI resizable.
 *
 * Copyright (c) 2011 Adam Shaw
 * Dual licensed under the MIT and GPL licenses, located in
 * MIT-LICENSE.txt and GPL-LICENSE.txt respectively.
 *
 * Date: Tue Sep 4 23:38:33 2012 -0700
 *
 *-------------------------------------------------------------------
 * Substantially modified by Eric Crane, Non-Profit Software Corp.
 * Copyright 2017, Non Profit Software Corp.
 *
 *
 */
!function(a,b){function g(b){a.extend(!0,c,b)}function h(c,d,e){function H(a){o?(R(),Q(),aa(),O(a)):I()}function I(){p=d.theme?"ui":"fc",c.addClass("fc"),d.isRTL&&c.addClass("fc-rtl"),d.theme&&c.addClass("ui-widget"),o=a("<div class='fc-content' style='position:relative'/>").prependTo(c),m=new i(g,d),n=m.render(),n&&c.prepend(n),N(d.defaultView),a(window).resize(T),M()||J()}function J(){setTimeout(function(){!t.start&&M()&&O()},0)}function K(){a(window).unbind("resize",T),m.destroy(),o.remove(),c.removeClass("fc fc-rtl ui-widget")}function L(){return 0!==k.offsetWidth}function M(){return 0!==a("body")[0].offsetWidth}function N(b){if(!t||b!=t.name){B++,ca();var d,c=t;c?((c.beforeHide||Z)(),Y(o,o.height()),c.element.hide()):Y(o,1),o.css("overflow","hidden"),t=u[b],t?t.element.show():t=u[b]=new f[b](d=y=a("<div class='fc-view fc-view-"+b+"' style='position:absolute'/>").appendTo(o),g),c&&m.deactivateButton(c.name),m.activateButton(b),O(),o.css("overflow",""),c&&Y(o,1),d||(t.afterShow||Z)(),B--}}function O(a){if(L()){B++,ca(),x===b&&R();var d=!1;!t.start||a||C<t.start||C>=t.end?(t.render(C,a||0),S(!0),d=!0):t.sizeDirty?(t.clearEvents(),S(),d=!0):t.eventsDirty&&(t.clearEvents(),d=!0),t.sizeDirty=!1,t.eventsDirty=!1,V(d),v=c.outerWidth(),m.updateTitle(t.title);var e=new Date;e>=t.start&&e<t.end?m.disableButton("today"):m.enableButton("today"),B--,t.trigger("viewDisplay",k)}}function P(){Q(),L()&&(R(),S(),ca(),t.clearEvents(),t.renderEvents(D),t.sizeDirty=!1)}function Q(){a.each(u,function(a,b){b.sizeDirty=!0})}function R(){x=d.contentHeight?d.contentHeight:d.height?d.height-(n?n.height():0)-U(o):Math.round(o.width()/Math.max(d.aspectRatio,.5))}function S(a){B++,t.setHeight(x,a),y&&(y.css("position","relative"),y=null),t.setWidth(o.width(),a),B--}function T(){if(!B)if(t.start){var a=++z;setTimeout(function(){a==z&&!B&&L()&&v!=(v=c.outerWidth())&&(B++,P(),t.trigger("windowResize",k),B--)},200)}else J()}function V(a){!d.lazyFetching||h(t.visStart,t.visEnd)?W():a&&_()}function W(){j(t.visStart,t.visEnd)}function X(a){D=a,_()}function $(a){_(a)}function _(a){aa(),L()&&(t.clearEvents(),t.renderEvents(D,a),t.eventsDirty=!1)}function aa(){a.each(u,function(a,b){b.eventsDirty=!0})}function ba(a,c,d){t.select(a,c,d===b||d)}function ca(){t&&t.unselect()}function da(){O(-1)}function ea(){O(1)}function fa(){q(C,-1),O()}function ga(){q(C,1),O()}function ha(){C=new Date,O()}function ia(){W()}function ja(a,b,c){a instanceof Date?C=w(a):A(C,a,b,c),O()}function ka(a,c,d){a!==b&&q(C,a),c!==b&&r(C,c),d!==b&&s(C,d),O()}function la(){return w(C)}function ma(){return t}function na(a,c){if(c===b)return d[a];"height"!=a&&"contentHeight"!=a&&"aspectRatio"!=a||(d[a]=c,P())}function oa(a,b){if(d[a])return d[a].apply(b||k,Array.prototype.slice.call(arguments,2))}var g=this;g.options=d,g.render=H,g.destroy=K,g.refetchEvents=W,g.reportEvents=X,g.reportEventChange=$,g.rerenderEvents=_,g.changeView=N,g.select=ba,g.unselect=ca,g.prev=da,g.next=ea,g.prevYear=fa,g.nextYear=ga,g.today=ha,g.refresh=ia,g.gotoDate=ja,g.incrementDate=ka,g.formatDate=function(a,b){return E(a,b,d)},g.formatDates=function(a,b,c){return F(a,b,c,d)},g.getDate=la,g.getView=ma,g.option=na,g.trigger=oa,l.call(g,d,e);var m,n,o,p,t,v,x,y,G,h=g.isFetchNeeded,j=g.fetchEvents,k=c[0],u={},z=0,B=0,C=new Date,D=[];A(C,d.year,d.month,d.date),d.droppable&&a(document).bind("dragstart",function(b,c){var e=b.target,f=a(e);if(!f.parents(".fc").length){var g=d.dropAccept;(a.isFunction(g)?g.call(e,f):f.is(g))&&(G=e,t.dragStart(G,b,c))}}).bind("dragstop",function(a,b){G&&(t.dragStop(G,a,b),G=null)})}function i(b,c){function h(){if(g=c.theme?"ui":"fc",c.header)return e=a("<table class='fc-header' style='width:100%'/>").append(a("<tr/>").append(j("left")).append(j("center")).append(j("right")))}function i(){e.remove()}function j(d){var e=a("<td class='fc-header-"+d+"'/>"),h=c.header[d];return h&&a.each(h.split(" "),function(d){d>0&&e.append("<span class='fc-header-space'/>");var h;a.each(this.split(","),function(d,i){if("title"==i)e.append("<span class='fc-header-title'><h2>&nbsp;</h2></span>"),h&&h.addClass(g+"-corner-right"),h=null;else if("goto"==i){e.append('<label for="gotoDate">Go To Date: </label>');var j=a('<input type="text" id="gotoDate" class="ckdate" value=""/>');j&&j.change(function(){var a=j.datepicker("getDate");a&&(b.gotoDate(a),j.val(""))}).datepicker().appendTo(e)}else{var k;if(b[i]?k=b[i]:f[i]&&(k=function(){n.removeClass(g+"-state-hover"),b.changeView(i)}),k){var l=c.theme?ba(c.buttonIcons,i):null,m=ba(c.buttonText,i),n=a("<span class='fc-button fc-button-"+i+" "+g+"-state-default'><span class='fc-button-inner'><span class='fc-button-content'>"+(l?"<span class='fc-icon-wrap'><span class='ui-icon ui-icon-"+l+"'/></span>":m)+"</span><span class='fc-button-effect'><span></span></span></span></span>");n&&(n.click(function(){n.hasClass(g+"-state-disabled")||k()}).mousedown(function(){n.not("."+g+"-state-active").not("."+g+"-state-disabled").addClass(g+"-state-down")}).mouseup(function(){n.removeClass(g+"-state-down")}).hover(function(){n.not("."+g+"-state-active").not("."+g+"-state-disabled").addClass(g+"-state-hover")},function(){n.removeClass(g+"-state-hover").removeClass(g+"-state-down")}).appendTo(e),h||n.addClass(g+"-corner-left"),h=n)}}}),h&&h.addClass(g+"-corner-right")}),e}function k(a){e.find("h2").html(a)}function l(a){e.find("span.fc-button-"+a).addClass(g+"-state-active")}function m(a){e.find("span.fc-button-"+a).removeClass(g+"-state-active")}function n(a){e.find("span.fc-button-"+a).addClass(g+"-state-disabled")}function o(a){e.find("span.fc-button-"+a).removeClass(g+"-state-disabled")}var d=this;d.render=h,d.destroy=i,d.updateTitle=k,d.activateButton=l,d.deactivateButton=m,d.disableButton=n,d.enableButton=o;var g,e=a([])}function l(c,d){function u(a,b){return!n||a<n||b>o}function v(a,b){n=a,o=b,s=[];var c=++p,d=m.length;q=d;for(var e=0;e<d;e++)x(m[e],c)}function x(a,b){y(a,function(c){if(b==p){if(c){for(var d=0;d<c.length;d++)c[d].source=a,J(c[d]);s=s.concat(c)}q--,q||i(s)}})}function y(b,d){var f,h,g=e.sourceFetchers;for(f=0;f<g.length;f++){if(!0===(h=g[f](b,n,o,d)))return;if("object"==typeof h)return void y(h,d)}var i=b.events;if(i)a.isFunction(i)?(H(),i(w(n),w(o),function(a){d(a),I()})):a.isArray(i)?d(i):d();else{if(b.url){var l=b.success,m=b.error,p=b.complete,q=a.extend({},b.data||{}),r=ja(b.startParam,c.startParam),s=ja(b.endParam,c.endParam);r&&(q[r]=Math.round(+n/1e3)),s&&(q[s]=Math.round(+o/1e3)),H(),a.ajax(a.extend({},j,b,{data:q,success:function(b){b=b||[];var c=ia(l,this,arguments);a.isArray(c)&&(b=c),d(b)},error:function(){ia(m,this,arguments),d()},complete:function(){ia(p,this,arguments),I()}}))}else d()}}function z(a){(a=A(a))&&(q++,x(a,p))}function A(b){if(a.isFunction(b)||a.isArray(b)?b={events:b}:"string"==typeof b&&(b={url:b}),"object"==typeof b)return K(b),m.push(b),b}function C(b){m=a.grep(m,function(a){return!L(a,b)}),s=a.grep(s,function(a){return!L(a.source,b)}),i(s)}function D(a){var b,d,c=s.length,e=h().defaultEventEnd,f=a.start-a._start,g=a.end?a.end-(a._end||e(a)):0;for(b=0;b<c;b++)d=s[b],d._id==a._id&&d!=a&&(d.start=new Date(+d.start+f),a.end?d.end?d.end=new Date(+d.end+g):d.end=new Date(+e(d)+g):d.end=null,d.title=a.title,d.url=a.url,d.allDay=a.allDay,d.className=a.className,d.editable=a.editable,d.color=a.color,d.backgroudColor=a.backgroudColor,d.borderColor=a.borderColor,d.borderStyle=a.borderStyle,d.textColor=a.textColor,J(d));J(a),i(s)}function E(a,b){J(a),a.source||(b&&(l.events.push(a),a.source=l),s.push(a)),i(s)}function F(b){if(b){if(!a.isFunction(b)){var d=b+"";b=function(a){return a._id==d}}s=a.grep(s,b,!0);for(var c=0;c<m.length;c++)a.isArray(m[c].events)&&(m[c].events=a.grep(m[c].events,b,!0))}else{s=[];for(var c=0;c<m.length;c++)a.isArray(m[c].events)&&(m[c].events=[])}i(s)}function G(b){return a.isFunction(b)?a.grep(s,b):b?(b+="",a.grep(s,function(a){return a._id==b})):s}function H(){r++||g("loading",null,!0)}function I(){--r||g("loading",null,!1)}function J(a){var d=a.source||{},e=ja(d.ignoreTimezone,c.ignoreTimezone);a._id=a._id||(a.id===b?"_fc"+k++:a.id+""),a.date&&(a.start||(a.start=a.date),delete a.date),a._start=w(a.start=B(a.start,e)),a.end=B(a.end,e),a.end&&a.end<=a.start&&(a.end=null),a._end=a.end?w(a.end):null,a.allDay===b&&(a.allDay=ja(d.allDayDefault,c.allDayDefault)),a.className?"string"==typeof a.className&&(a.className=a.className.split(/\s+/)):a.className=[]}function K(a){a.className?"string"==typeof a.className&&(a.className=a.className.split(/\s+/)):a.className=[];for(var b=e.sourceNormalizers,c=0;c<b.length;c++)b[c](a)}function L(a,b){return a&&b&&M(a)==M(b)}function M(a){return("object"==typeof a?a.events||a.url:"")||a}var f=this;f.isFetchNeeded=u,f.fetchEvents=v,f.addEventSource=z,f.removeEventSource=C,f.updateEvent=D,f.renderEvent=E,f.removeEvents=F,f.clientEvents=G,f.normalizeEvent=J;for(var n,o,g=f.trigger,h=f.getView,i=f.reportEvents,l={events:[]},m=[l],p=0,q=0,r=0,s=[],t=0;t<d.length;t++)A(d[t])}function q(a,b,c){return a.setFullYear(a.getFullYear()+b),c||v(a),a}function r(a,b,c){if(+a){var d=a.getMonth()+b,e=w(a);for(e.setDate(1),e.setMonth(d),a.setMonth(d),c||v(a);a.getMonth()!=e.getMonth();)a.setDate(a.getDate()+(a<e?1:-1))}return a}function s(a,b,c){if(+a){var d=a.getDate()+b,e=w(a);e.setHours(9),e.setDate(d),a.setDate(d),c||v(a),t(a,e)}return a}function t(a,b){if(+a)for(;a.getDate()!=b.getDate();)a.setTime(+a+(a<b?1:-1)*o)}function u(a,b){return a.setMinutes(a.getMinutes()+b),a}function v(a){return a.setHours(0),a.setMinutes(0),a.setSeconds(0),a.setMilliseconds(0),a}function w(a,b){return b?v(new Date(+a)):new Date(+a)}function z(a,b){return Math.round((w(a,!0)-w(b,!0))/n)}function A(a,c,d,e){c!==b&&c!=a.getFullYear()&&(a.setDate(1),a.setMonth(0),a.setFullYear(c)),d!==b&&d!=a.getMonth()&&(a.setDate(1),a.setMonth(d)),e!==b&&a.setDate(e)}function B(a,c){return"object"==typeof a?a:"number"==typeof a?new Date(1e3*a):"string"==typeof a?a.match(/^\d+(\.\d+)?$/)?new Date(1e3*parseFloat(a)):(c===b&&(c=!0),C(a,c)||(a?new Date(a):null)):null}function C(a,b){var c=a.match(/^([0-9]{4})(-([0-9]{2})(-([0-9]{2})([T ]([0-9]{2}):([0-9]{2})(:([0-9]{2})(\.([0-9]+))?)?(Z|(([-+])([0-9]{2})(:?([0-9]{2}))?))?)?)?)?$/);if(!c)return null;var d=new Date(c[1],0,1);if(b||!c[13]){var e=new Date(c[1],0,1,9,0);c[3]&&(d.setMonth(c[3]-1),e.setMonth(c[3]-1)),c[5]&&(d.setDate(c[5]),e.setDate(c[5])),t(d,e),c[7]&&d.setHours(c[7]),c[8]&&d.setMinutes(c[8]),c[10]&&d.setSeconds(c[10]),c[12]&&d.setMilliseconds(1e3*Number("0."+c[12])),t(d,e)}else if(d.setUTCFullYear(c[1],c[3]?c[3]-1:0,c[5]||1),d.setUTCHours(c[7]||0,c[8]||0,c[10]||0,c[12]?1e3*Number("0."+c[12]):0),c[14]){var f=60*Number(c[16])+(c[18]?Number(c[18]):0);f*="-"==c[15]?1:-1,d=new Date(+d+60*f*1e3)}return d}function D(a){if("number"==typeof a)return 60*a;if("object"==typeof a)return 60*a.getHours()+a.getMinutes();var b=a.match(/(\d+)(?::(\d+))?\s*(\w+)?/);if(b){var c=parseInt(b[1],10);return b[3]&&(c%=12,"p"==b[3].toLowerCase().charAt(0)&&(c+=12)),60*c+(b[2]?parseInt(b[2],10):0)}}function E(a,b,c){return F(a,null,b,c)}function F(a,b,d,e){e=e||c;var h,j,k,l,f=a,g=b,i=d.length,m="";for(h=0;h<i;h++)if("'"==(j=d.charAt(h))){for(k=h+1;k<i;k++)if("'"==d.charAt(k)){f&&(m+=k==h+1?"'":d.substring(h+1,k),h=k);break}}else if("("==j){for(k=h+1;k<i;k++)if(")"==d.charAt(k)){var n=E(f,d.substring(h+1,k),e);parseInt(n.replace(/\D/,""),10)&&(m+=n),h=k;break}}else if("["==j){for(k=h+1;k<i;k++)if("]"==d.charAt(k)){var o=d.substring(h+1,k),n=E(f,o,e);n!=E(g,o,e)&&(m+=n),h=k;break}}else if("{"==j)f=b,g=a;else if("}"==j)f=a,g=b;else{for(k=i;k>h;k--)if(l=G[d.substring(h,k)]){f&&(m+=l(f,e)),h=k-1;break}k==h&&f&&(m+=j)}return m}function H(a){return a.end?I(a.end,a.allDay):s(w(a.start),1)}function I(a,b){return a=w(a),b||a.getHours()||a.getMinutes()?s(a,1):v(a)}function J(a,b){return 100*(b.msLength-a.msLength)+(a.event.start-b.event.start)}function K(a,b){return a.end>b.start&&a.start<b.end}function L(a,b,c,d){var f,h,i,j,k,l,m,n,e=[],g=a.length;for(f=0;f<g;f++)h=a[f],i=h.start,(j=b[f])>c&&i<d&&(i<c?(k=w(c),m=!1):(k=i,m=!0),j>d?(l=w(d),n=!1):(l=j,n=!0),e.push({event:h,start:k,end:l,isStart:m,isEnd:n,msLength:l-k}));return e.sort(J)}function M(a){var c,e,f,g,h,b=[],d=a.length;for(c=0;c<d;c++){for(e=a[c],f=0;;){if(g=!1,b[f])for(h=0;h<b[f].length;h++)if(K(b[f][h],e)){g=!0;break}if(!g)break;f++}b[f]?b[f].push(e):b[f]=[e]}return b}function N(c,d,e){c.unbind("mouseover").mouseover(function(c){for(var g,h,i,f=c.target;f!=this;)g=f,f=f.parentNode;(h=g._fci)!==b&&(g._fci=b,i=d[h],e(i.event,i.element,i),a(c.target).trigger(c)),c.stopPropagation()})}function O(b,c,d){for(var f,e=0;e<b.length;e++)f=a(b[e]),f.width(Math.max(0,c-Q(f,d)))}function Q(a,b){return R(a)+T(a)+(b?S(a):0)}function R(b){return(parseFloat(a.css(b[0],"paddingLeft",!0))||0)+(parseFloat(a.css(b[0],"paddingRight",!0))||0)}function S(b){return(parseFloat(a.css(b[0],"marginLeft",!0))||0)+(parseFloat(a.css(b[0],"marginRight",!0))||0)}function T(b){return(parseFloat(a.css(b[0],"borderLeftWidth",!0))||0)+(parseFloat(a.css(b[0],"borderRightWidth",!0))||0)}function U(a,b){return V(a)+X(a)+(b?W(a):0)}function V(b){return(parseFloat(a.css(b[0],"paddingTop",!0))||0)+(parseFloat(a.css(b[0],"paddingBottom",!0))||0)}function W(b){return(parseFloat(a.css(b[0],"marginTop",!0))||0)+(parseFloat(a.css(b[0],"marginBottom",!0))||0)}function X(b){return(parseFloat(a.css(b[0],"borderTopWidth",!0))||0)+(parseFloat(a.css(b[0],"borderBottomWidth",!0))||0)}function Y(a,b){b="number"==typeof b?b+"px":b,a.each(function(a,c){c.style.cssText+=";min-height:"+b+";_height:"+b})}function Z(){}function $(a,b){return a-b}function _(a){return Math.max.apply(Math,a)}function aa(a){return(a<10?"0":"")+a}function ba(a,c){if(a[c]!==b)return a[c];for(var f,d=c.split(/(?=[A-Z])/),e=d.length-1;e>=0;e--)if((f=a[d[e].toLowerCase()])!==b)return f;return a[""]}function ca(a){return a.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/'/g,"&#039;").replace(/"/g,"&quot;").replace(/\n/g,"<br />")}function da(a){return a.id+"/"+a.className+"/"+a.style.cssText.replace(/(^|;)\s*(top|left|width|height)\s*:[^;]*/gi,"")}function ea(a){a.attr("unselectable","on").css("MozUserSelect","none").bind("selectstart.ui",function(){return!1})}function fa(a){a.children().removeClass("fc-first fc-last").filter(":first-child").addClass("fc-first").end().filter(":last-child").addClass("fc-last")}function ga(a,b){a.each(function(a,c){c.className=c.className.replace(/^fc-\w*/,"fc-"+m[b.getDay()])})}function ha(a,b){var c=a.source||{},d=a.color,e=c.color,f=b("eventColor"),g=a.backgroundColor||d||c.backgroundColor||e||b("eventBackgroundColor")||f,h=a.borderColor||d||c.borderColor||e||b("eventBorderColor")||f,i=a.borderStyle,j=a.textColor||c.textColor||b("eventTextColor"),k=[];return g&&k.push("background-color:"+g),h&&k.push("border-color:"+h),i&&k.push("border-style:"+i),j&&k.push("color:"+j),k.join(";")}function ia(b,c,d){if(a.isFunction(b)&&(b=[b]),b){var e,f;for(e=0;e<b.length;e++)f=b[e].apply(c,d)||f;return f}}function ja(){for(var a=0;a<arguments.length;a++)if(arguments[a]!==b)return arguments[a]}function ka(a,b){function f(a,f){var g,h,i,j,k;f&&s(a,7*f),k=a.getDay()>3?0:7,b.options.viewDays>21?(g=s(w(a),-((a.getDay()-d("firstDay")+7)%7+k)),h=s(w(g),b.options.viewDays)):(g=s(w(a),-(a.getDay()-d("firstDay")+7)%7),h=s(w(g),b.options.viewDays)),i=w(g),j=w(h),c.title=F(i,s(w(j),-1),d("titleFormat")),c.start=g,c.end=h,c.visStart=i,c.visEnd=j,e(1,1,b.options.viewDays,!0)}var c=this;c.render=f,la.call(c,a,b,"twoweeks");var d=c.opt,e=c.renderBasic}function la(b,c,d){function Q(a,b,c,d){D=b,E=c,R();var e=!q;e?S(a,d):h(),T(e)}function R(){I=f("isRTL"),I?(J=-1,K=E-1):(J=1,K=0),L=f("firstDay"),M=f("weekends")?0:1,N=f("theme")?"ui":"fc",P=f("columnFormat")}function S(c,d){var e,h,i,j,f=N+"-widget-header",g=N+"-widget-content";for(e="<table class='fc-border-separate' style='width:100%' cellspacing='0'><thead><tr>",h=0;h<E;h++)e+="<th class='fc- "+f+"'/>";for(e+="</tr></thead><tbody>",h=0;h<c;h++){for(e+="<tr class='fc-week"+h+"'>",i=0;i<E;i++)e+="<td class='fc- "+g+" fc-day"+(h*E+i)+"'><div>"+(d?"<div class='fc-day-number'/>":"")+"<div class='fc-day-content'><div style='position:relative'>&nbsp;</div></div></div></td>";e+="</tr>"}for(e+="</tbody><tfoot><tr>",h=0;h<E;h++)e+="<th class='fc- "+f+"'/>";e+="</tr></tfoot></table>",j=a(e).appendTo(b),m=j.find("thead"),n=m.find("th"),o=j.find("tfoot"),p=o.find("th"),q=j.find("tbody"),r=q.find("tr"),t=q.find("td"),u=t.filter(":first-child"),x=r.eq(0).find("div.fc-day-content div"),fa(m.add(m.find("tr"))),fa(r),r.eq(0).addClass("fc-first"),X(t),y=a("<div style='position:absolute;top:0;left:0'/>").appendTo(b)}function T(b){var g,h,i,c=b||1==D,d=e.start.getMonth(),f=v(new Date);c&&(n.each(function(b,c){g=a(c),h=wa(b),g.html(l(h,P)),ga(g,h)}),p.each(function(b,c){g=a(c),h=wa(b),g.html(l(h,P)),ga(g,h)})),t.each(function(b,e){g=a(e),h=wa(b),h.getMonth()==d?g.removeClass("fc-other-month"):g.addClass("fc-other-month"),+h==+f?g.addClass(N+"-state-highlight fc-today"):g.removeClass(N+"-state-highlight fc-today"),g.find("div.fc-day-number").text(h.getDate()),c&&ga(g,h)}),r.each(function(b,c){i=a(c),b<D?(i.show(),b==D-1?i.addClass("fc-last"):i.removeClass("fc-last")):i.hide()})}function V(b){B=b;var d,e,g,c=B-m.height();"variable"==f("weekMode")?d=e=Math.floor(c/(1==D?2:6)):(d=Math.floor(c/D),e=c-d*(D-1)),u.each(function(b,c){b<D&&(g=a(c),Y(g.find("> div"),(b==D-1?e:d)-U(g)))})}function W(a){A=a,H.clear(),C=Math.floor(A/E),O(n.slice(0,-1),C)}function X(a){a.click(Z).mousedown(k)}function Z(a){if(!f("selectable")){var b=parseInt(this.className.match(/fc\-day(\d+)/)[1]),c=wa(b);g("dayClick",this,c,!0,a)}}function $(a,b,c){c&&F.build();for(var d=w(e.visStart),f=s(w(d),E),g=0;g<D;g++){var h=new Date(Math.max(d,a)),i=new Date(Math.min(f,b));if(h<i){var j,k;I?(j=z(i,d)*J+K+1,k=z(h,d)*J+K+1):(j=z(h,d),k=z(i,d)),X(_(g,j,g,k-1))}s(d,21),s(f,21)}}function _(a,c,d,e){var f=F.rect(a,c,d,e,b);return i(f,b)}function aa(a,b){return w(a)}function ba(a,b,c){$(a,s(w(b),1),!0)}function ca(){j()}function da(a,b,c){var d=na(a),e=t[d.row*E+d.col];g("dayClick",e,a,b,c)}function ha(a,b,c){G.start(function(a){j(),a&&_(a.row,a.col,a.row,a.col)},b)}function ia(a,b,c){var d=G.stop();if(j(),d){var e=pa(d);g("drop",a,e,!0,b,c)}}function ja(a){return w(a.start)}function ka(a){return H.left(a)}function la(a){return H.right(a)}function na(a){return{row:Math.floor(z(a,e.visStart)/21),col:xa(z(a,e.visStart))}}function pa(a){return va(a.row,a.col)}function va(a,b){return s(w(e.visStart),21*a+b*J+K)}function wa(a){return va(Math.floor(a/E),a%E)}function xa(a){return(a-Math.max(L,M)+E)%E*J+K}function ya(a){return r.eq(a)}function za(a){return{left:0,right:A}}var e=this;e.renderBasic=Q,e.setHeight=V,e.setWidth=W,e.renderDayOverlay=$,e.defaultSelectionEnd=aa,e.renderSelection=ba,e.clearSelection=ca,e.reportDayClick=da,e.dragStart=ha,e.dragStop=ia,e.defaultEventEnd=ja,e.getHoverListener=function(){return G},e.colContentLeft=ka,e.colContentRight=la,e.dayOfWeekCol=xa,e.dateCell=na,e.cellDate=pa,e.cellIsAllDay=function(){return!0},e.allDayRow=ya,e.allDayBounds=za,e.getRowCnt=function(){return D},e.getColCnt=function(){return E},e.getColWidth=function(){return C},e.getDaySegmentContainer=function(){return y},oa.call(e,b,c,d),ra.call(e),qa.call(e),ma.call(e);var m,n,o,p,q,r,t,u,x,y,A,B,C,D,E,F,G,H,I,J,K,L,M,N,P,f=e.opt,g=e.trigger,h=e.clearEvents,i=e.renderOverlay,j=e.clearOverlays,k=e.daySelectionMousedown,l=c.formatDate;ea(b.addClass("fc-grid")),F=new sa(function(b,c){var d,e,f;n.each(function(b,g){d=a(g),e=d.offset().left,b&&(f[1]=e),f=[e],c[b]=f}),f[1]=e+d.outerWidth(),r.each(function(c,g){c<D&&(d=a(g),e=d.offset().top,c&&(f[1]=e),f=[e],b[c]=f)}),f[1]=e+d.outerHeight()}),G=new ta(F),H=new ua(function(a){return x.eq(a)})}function ma(){function v(a,b){g(a),t(y(a),b)}function x(){h(),m().empty()}function y(c){var i,j,k,l,m,n,d=q(),e=r(),f=w(b.visStart),g=s(w(f),e),h=a.map(c,H),o=[];for(i=0;i<d;i++){for(j=M(L(c,h,f,g)),k=0;k<j.length;k++)for(l=j[k],m=0;m<l.length;m++)n=l[m],n.row=i,o.push(n);s(f,21),s(g,21)}return o}function z(a,b,c){e(a)&&A(a,b),c.isEnd&&f(a)&&u(a,b,c),i(a,b)}function A(a,b){var f,e=n();b.draggable({zIndex:9,delay:50,opacity:c("dragOpacity"),revertDuration:c("dragRevertDuration"),start:function(g,h){d("eventDragStart",b,a,g,h),k(a,b),e.start(function(d,e,g,h){b.draggable("option","revert",!d||!g&&!h),p(),d?(f=21*g+h*(c("isRTL")?-1:1),o(s(w(a.start),f),s(H(a),f))):f=0},g,"drag")},stop:function(c,g){e.stop(),p(),d("eventDragStop",b,a,c,g),f?l(this,a,f,0,a.allDay,c,g):(b.css("filter",""),j(a,b))}})}var b=this;b.renderEvents=v,b.compileDaySegs=y,b.clearEvents=x,b.bindDaySeg=z,pa.call(b);var c=b.opt,d=b.trigger,e=b.isEventDraggable,f=b.isEventResizable,g=b.reportEvents,h=b.reportEventClear,i=b.eventElementHandlers,j=b.showEvents,k=b.hideEvents,l=b.eventDrop,m=b.getDaySegmentContainer,n=b.getHoverListener,o=b.renderDayOverlay,p=b.clearOverlays,q=b.getRowCnt,r=b.getColCnt,t=b.renderDaySegs,u=b.resizableDayEvent}function oa(a,c,d){function m(a,b){var c=l[a];return"object"==typeof c?ba(c,b||d):c}function n(a,b){return c.trigger.apply(c,[a,b||e].concat(Array.prototype.slice.call(arguments,2),[e]))}function o(a){return q(a)&&!m("disableDragging")}function p(a){return q(a)&&!m("disableResizing")}function q(a){return ja(a.editable,(a.source||{}).editable,m("editable"))}function r(a){i={};var b,d,c=a.length;for(b=0;b<c;b++)d=a[b],i[d._id]?i[d._id].push(d):i[d._id]=[d]}function t(a){return a.end?w(a.end):f(a)}function v(a,b){j.push(b),k[a._id]?k[a._id].push(b):k[a._id]=[b]}function x(){j=[],k={}}function y(a,b){b.click(function(c){if(!b.hasClass("ui-draggable-dragging")&&!b.hasClass("ui-resizable-resizing"))return n("eventClick",this,a,c)}).hover(function(b){n("eventMouseover",this,a,b)},function(b){n("eventMouseout",this,a,b)})}function z(a,b){B(a,b,"show")}function A(a,b){B(a,b,"hide")}function B(a,b,c){var e,d=k[a._id],f=d.length;for(e=0;e<f;e++)b&&d[e][0]==b[0]||d[e][c]()}function C(a,b,c,d,e,f,g){var j=b.allDay,k=b._id;E(i[k],c,d,e),n("eventDrop",a,b,c,d,e,function(){E(i[k],-c,-d,j),h(k)},f,g),h(k)}function D(a,b,c,d,e,f){var g=b._id;F(i[g],c,d),n("eventResize",a,b,c,d,function(){F(i[g],-c,-d),h(g)},e,f),h(g)}function E(a,c,d,e){d=d||0;for(var f,h=a.length,i=0;i<h;i++)f=a[i],e!==b&&(f.allDay=e),u(s(f.start,c,!0),d),f.end&&(f.end=u(s(f.end,c,!0),d)),g(f,l)}function F(a,b,c){c=c||0;for(var d,e=a.length,f=0;f<e;f++)d=a[f],d.end=u(s(t(d),b,!0),c),g(d,l)}var e=this;e.element=a,e.calendar=c,e.name=d,e.opt=m,e.trigger=n,e.isEventDraggable=o,e.isEventResizable=p,e.reportEvents=r,e.eventEnd=t,e.reportEventElement=v,e.reportEventClear=x,e.eventElementHandlers=y,e.showEvents=z,e.hideEvents=A,e.eventDrop=C,e.eventResize=D;var f=e.defaultEventEnd,g=c.normalizeEvent,h=c.reportEventChange,i={},j=[],k={},l=c.options}function pa(){function G(a,b){var d,h,j,k,o,p,q,c=y(),e=m(),f=n(),g=0,l=a.length;for(c[0].innerHTML=I(a),J(a,c.children()),K(a),L(a,c,b),M(a),O(a),P(a),d=R(),h=0;h<e;h++){for(0,j=[],k=0;k<f;k++)j[k]=0;for(;g<l&&(o=a[g]).row==h;){for(p=_(j.slice(o.startCol,o.endCol)),o.top=p,p+=o.outerHeight,q=o.startCol;q<o.endCol;q++)j[q]=p;g++}d[h].height(_(j))}T(a,S(d))}function H(b,c,d){var f,h,j,e=a("<div/>"),g=y(),i=b.length;for(e[0].innerHTML=I(b),f=e.children(),g.append(f),J(b,f),M(b),O(b),P(b),T(b,S(R())),f=[],h=0;h<i;h++)(j=b[h].element)&&(b[h].row===c&&j.css("top",d),f.push(j[0]));return a(f)}function I(a){var e,i,j,k,l,p,s,v,w,x,b=d("isRTL"),h=a.length,m=q(),n=m.left,o=m.right,y="";for(e=0;e<h;e++)i=a[e],j=i.event,l=["fc-event","fc-event-skin","fc-event-hori"],f(j)&&l.push("fc-event-draggable"),b?(i.isStart&&l.push("fc-corner-right"),i.isEnd&&l.push("fc-corner-left"),p=u(i.end.getDay()-1),s=u(i.start.getDay()),v=i.isEnd?r(p):n,w=i.isStart?t(s):o):(i.isStart&&l.push("fc-corner-left"),i.isEnd&&l.push("fc-corner-right"),p=u(z(i.start,c.visStart)),s=u(z(i.end,c.visStart)-1),v=i.isStart?r(p):n,w=i.isEnd?t(s):o),l=l.concat(j.className),j.source&&(l=l.concat(j.source.className||[])),k=j.url,x=ha(j,d),y+=k?"<a href='"+ca(k)+"'":"<div",y+=" class='"+l.join(" ")+"' style='position:absolute;z-index:1;left:"+v+"px;"+x+"'><div class='fc-event-inner fc-event-skin'"+(x?" style='"+x+"'":"")+">",!j.allDay&&i.isStart&&(y+="<span class='fc-event-time'>"+ca(B(j.start,j.end,d("timeFormat")))+"</span>"),y+="<span class='fc-event-title'>"+j.title+"</span></div>",i.isEnd&&g(j)&&(y+="<div class='ui-resizable-handle ui-resizable-"+(b?"w":"e")+"'>&nbsp;&nbsp;&nbsp;</div>"),y+="</"+(k?"a":"div")+">",i.left=v,i.outerWidth=w-v,i.startCol=p,i.endCol=s+1;return y}function J(b,c){var d,g,h,i,j,f=b.length;for(d=0;d<f;d++)g=b[d],h=g.event,i=a(c[d]),j=e("eventRender",h,h,i,F),!1===j?i.remove():(j&&!0!==j&&(j=a(j).css({position:"absolute",left:g.left}),i.replaceWith(j),i=j),g.element=i)}function K(a){var b,d,e,c=a.length;for(b=0;b<c;b++)d=a[b],(e=d.element)&&i(d.event,e)}function L(a,b,c){var d,f,g,h,e=a.length;for(d=0;d<e;d++)f=a[d],(g=f.element)&&(h=f.event,h._id===c?A(h,g,f):g[0]._fci=d);N(b,a,A)}function M(a){var c,e,f,g,h,d=a.length,i={};for(c=0;c<d;c++)e=a[c],(f=e.element)&&(g=e.key=da(f[0]),h=i[g],h===b&&(h=i[g]=Q(f,!0)),e.hsides=h)}function O(a){var b,d,e,c=a.length;for(b=0;b<c;b++)d=a[b],(e=d.element)&&(e[0].style.width=Math.max(0,d.outerWidth-d.hsides)+"px")}function P(a){var c,e,f,g,h,d=a.length,i={};for(c=0;c<d;c++)e=a[c],(f=e.element)&&(g=e.key,h=i[g],h===b&&(h=i[g]=W(f)),e.outerHeight=f[0].offsetHeight+h)}function R(){var a,b=m(),c=[];for(a=0;a<b;a++)c[a]=p(a).find("td:first div.fc-day-content > div");return c}function S(a){var b,c=a.length,d=[];for(b=0;b<c;b++)d[b]=a[b][0].offsetTop;return d}function T(a,b){var c,f,g,h,d=a.length;for(c=0;c<d;c++)f=a[c],(g=f.element)&&(g[0].style.top=b[f.row]+f.event.level*(g[0].offsetHeight+6)+"px",h=f.event,e("eventAfterRender",h,h,g))}function U(b,f,g){var i=d("isRTL"),o=i?"w":"e",p=f.find("div.ui-resizable-"+o),q=!1;ea(f),f.mousedown(function(a){a.preventDefault()}).click(function(a){q&&(a.preventDefault(),a.stopImmediatePropagation())}),p.mousedown(function(d){function I(c){e("eventResizeStop",this,b,c),a("body").css("cursor",""),p.stop(),D(),A&&l(this,b,A,0,c),setTimeout(function(){q=!1},0)}if(1==d.which){q=!0;var A,B,p=c.getHoverListener(),r=m(),t=n(),u=i?-1:1,y=i?t-1:0,z=f.css("top"),F=a.extend({},b),G=v(b.start);E(),a("body").css("cursor",o+"-resize").one("mouseup",I),e("eventResizeStart",this,b,d),p.start(function(a,c){if(a){var d=Math.max(G.row,a.row),e=a.col;1==r&&(d=0),d==G.row&&(e=i?Math.min(G.col,e):Math.max(G.col,e)),A=21*d+e*u+y-(21*c.row+c.col*u+y);var f=s(h(b),A,!0);if(A){F.end=f;var l=B;B=H(x([F]),g.row,z),B.find("*").css("cursor",o+"-resize"),l&&l.remove(),k(b)}else B&&(j(b),B.remove(),B=null);D(),C(b.start,s(w(f),1))}},d)}})}var c=this;c.renderDaySegs=G,c.resizableDayEvent=U;var d=c.opt,e=c.trigger,f=c.isEventDraggable,g=c.isEventResizable,h=c.eventEnd,i=c.reportEventElement,j=c.showEvents,k=c.hideEvents,l=c.eventResize,m=c.getRowCnt,n=c.getColCnt,p=(c.getColWidth,c.allDayRow),q=c.allDayBounds,r=c.colContentLeft,t=c.colContentRight,u=c.dayOfWeekCol,v=c.dateCell,x=c.compileDaySegs,y=c.getDaySegmentContainer,A=c.bindDaySeg,B=c.calendar.formatDates,C=c.renderDayOverlay,D=c.clearOverlays,E=c.clearSelection,F=c.eventFilterId}function qa(){function i(a,b,c){j(),b||(b=e(a,c)),f(a,b,c),k(a,b,c)}function j(a){h&&(h=!1,g(),d("unselect",null,a))}function k(a,b,c,e){h=!0,d("select",null,a,b,c,e)}function l(d){var e=b.cellDate,h=b.cellIsAllDay,i=b.getHoverListener(),l=b.reportDayClick;if(1==d.which&&c("selectable")){j(d);var n;i.start(function(a,b){g(),a&&h(a)?(n=[e(b),e(a)].sort($),f(n[0],n[1],!0)):n=null},d),a(document).one("mouseup",function(a){i.stop(),n&&(+n[0]==+n[1]&&l(n[0],!0,a),k(n[0],n[1],!0,a))})}}var b=this;b.select=i,b.unselect=j,b.reportSelection=k,b.daySelectionMousedown=l;var c=b.opt,d=b.trigger,e=b.defaultSelectionEnd,f=b.renderSelection,g=b.clearSelection,h=!1;c("selectable")&&c("unselectAuto")&&a(document).mousedown(function(b){var d=c("unselectCancel");d&&a(b.target).parents(d).length||j(b)})}function ra(){function e(b,e){var f=d.shift();return f||(f=a("<div class='fc-cell-overlay' style='position:absolute;z-index:0'/>")),f[0].parentNode!=e[0]&&f.appendTo(e),c.push(f.css(b).show()),f}function f(){for(var a;a=c.shift();)d.push(a.hide().unbind())}var b=this;b.renderOverlay=e,b.clearOverlays=f;var c=[],d=[]}function sa(a){var c,d,b=this;b.build=function(){c=[],d=[],a(c,d)},b.cell=function(a,b){var g,e=c.length,f=d.length,h=-1,i=-1;for(g=0;g<e;g++)if(b>=c[g][0]&&b<c[g][1]){h=g;break}for(g=0;g<f;g++)if(a>=d[g][0]&&a<d[g][1]){i=g;break}return h>=0&&i>=0?{row:h,col:i}:null},b.rect=function(a,b,e,f,g){var h=g.offset();return{top:c[a][0]-h.top,left:d[b][0]-h.left,width:d[f][1]-d[b][0],height:c[e][1]-c[a][0]}}}function ta(b){function h(a){var c=b.cell(a.pageX,a.pageY);(!c!=!g||c&&(c.row!=g.row||c.col!=g.col))&&(c?(f||(f=c),e(c,f,c.row-f.row,c.col-f.col)):e(c,f),g=c)}var d,e,f,g,c=this;c.start=function(c,i,j){e=c,f=g=null,b.build(),h(i),d=j||"mousemove",a(document).bind(d,h)},c.stop=function(){return a(document).unbind(d,h),g}}function ua(a){function g(b){return d[b]=d[b]||a(b)}var c=this,d={},e={},f={};c.left=function(a){return e[a]=e[a]===b?g(a).position().left:e[a]},c.right=function(a){return f[a]=f[a]===b?c.left(a)+g(a).width():f[a]},c.clear=function(){d={},e={},f={}}}var c={defaultView:"twoweeks",viewDays:28,aspectRatio:4.6,header:{left:"title",center:"",right:"today prev,next"},weekends:!0,allDayDefault:!0,ignoreTimezone:!0,lazyFetching:!0,startParam:"start",endParam:"end",titleFormat:{month:"MMMM yyyy",week:"MMM d[ yyyy]{ '&#8212;'[ MMM] d yyyy}",day:"dddd, MMM d, yyyy",twoweeks:"MMM d[ yyyy]{ '&#8212;'[ MMM] d yyyy}"},columnFormat:{month:"ddd",week:"ddd M/d",day:"dddd M/d",twoweeks:"ddd"},timeFormat:{"":"h(:mm)t"},isRTL:!1,firstDay:0,monthNames:["January","February","March","April","May","June","July","August","September","October","November","December"],monthNamesShort:["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],dayNames:["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],dayNamesShort:["Sun","Mon","Tue","Wed","Thu","Fri","Sat"],buttonText:{prev:"&nbsp;&#9668;&nbsp;",next:"&nbsp;&#9658;&nbsp;",prevYear:"&nbsp;&lt;&lt;&nbsp;",nextYear:"&nbsp;&gt;&gt;&nbsp;",today:"today",month:"month",week:"week",day:"day",refresh:"refresh",goto:"goto"},theme:!1,buttonIcons:{prev:"circle-triangle-w",next:"circle-triangle-e"},unselectAuto:!0,dropAccept:"*"},d={header:{left:"next,prev today",center:"",right:"title"},buttonText:{prev:"&nbsp;&#9658;&nbsp;",next:"&nbsp;&#9668;&nbsp;",prevYear:"&nbsp;&gt;&gt;&nbsp;",nextYear:"&nbsp;&lt;&lt;&nbsp;"},buttonIcons:{prev:"circle-triangle-e",next:"circle-triangle-w"}},e=a.hhkCalendar={version:"1.1.1"},f=e.views={};a.fn.hhkCalendar=function(e){if("string"==typeof e){var g,f=Array.prototype.slice.call(arguments,1);return this.each(function(){var c=a.data(this,"hhkCalendar");if(c&&a.isFunction(c[e])){var d=c[e].apply(c,f);g===b&&(g=d),"destroy"==e&&a.removeData(this,"hhkCalendar")}}),g!==b?g:this}var i=e.eventSources||[];return delete e.eventSources,e.events&&(i.push(e.events),delete e.events),e=a.extend(!0,{},c,e.isRTL||e.isRTL===b&&c.isRTL?d:{},e),this.each(function(b,c){var d=a(c),f=new h(d,e,i);d.data("hhkCalendar",f),f.render()}),this},e.sourceNormalizers=[],e.sourceFetchers=[];var j={dataType:"json",cache:!1},k=1;e.addDays=s,e.cloneDate=w,e.parseDate=B,e.parseISO8601=C,e.parseTime=D,e.formatDate=E,e.formatDates=F;var m=["sun","mon","tue","wed","thu","fri","sat"],n=864e5,o=36e5,G={s:function(a){return a.getSeconds()},ss:function(a){return aa(a.getSeconds())},m:function(a){return a.getMinutes()},mm:function(a){return aa(a.getMinutes())},h:function(a){return a.getHours()%12||12},hh:function(a){return aa(a.getHours()%12||12)},H:function(a){return a.getHours()},HH:function(a){return aa(a.getHours())},d:function(a){return a.getDate()},dd:function(a){return aa(a.getDate())},ddd:function(a,b){return b.dayNamesShort[a.getDay()]},dddd:function(a,b){return b.dayNames[a.getDay()]},M:function(a){return a.getMonth()+1},MM:function(a){return aa(a.getMonth()+1)},MMM:function(a,b){return b.monthNamesShort[a.getMonth()]},MMMM:function(a,b){return b.monthNames[a.getMonth()]},yy:function(a){return(a.getFullYear()+"").substring(2)},yyyy:function(a){return a.getFullYear()},t:function(a){return a.getHours()<12?"a":"p"},tt:function(a){return a.getHours()<12?"am":"pm"},T:function(a){return a.getHours()<12?"A":"P"},TT:function(a){return a.getHours()<12?"AM":"PM"},u:function(a){return E(a,"yyyy-MM-dd'T'HH:mm:ss'Z'")},S:function(a){var b=a.getDate();return b>10&&b<20?"th":["st","nd","rd"][b%10-1]||"th"}};e.applyAll=ia,f.twoweeks=ka,g({weekMode:"fixed"})}(jQuery);
