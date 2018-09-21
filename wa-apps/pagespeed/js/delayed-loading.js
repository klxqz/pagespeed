var e = document.getElementById("pagespeed-preloader");
//e.parentNode.removeChild(e);
/*var loadedCount = 0;
 function callbackCss(load) {
 loadedCount++;
 if (loadedCount == window.pagespeedDelayCss.length) {
 //var e = document.getElementById("pagespeed-preloader");
 //e.parentNode.removeChild(e);
 }
 }
 
 
 if (window.pagespeedDelayCss.length) {
 var df = document.createDocumentFragment();
 for (var i in window.pagespeedDelayCss) {
 var link = document.createElement("link");
 if (link.readyState && !link.onload) {
 js.onreadystatechange = function () {
 if (link.readyState == "loaded" || link.readyState == "complete") {
 link.onreadystatechange = null;
 callbackCss();
 }
 }
 link.onerror = function () {
 callbackCss();
 }
 }
 else {
 link.onload = callbackCss;
 link.onerror = callbackCss;
 }
 link.type = "text/css";
 link.rel = "stylesheet";
 link.href = window.pagespeedDelayCss[i];
 df.appendChild(link);
 }
 var head = document.getElementsByTagName("head")[0].firstChild;
 document.getElementsByTagName("head")[0].insertBefore(df, head);
 //document.body.appendChild(df);
 }*/
/*
 if (window.pagespeedDelayCss.length) {
 var loadDeferredStyles = function () {
 var addStylesNode = document.getElementById("deferred-styles");
 var replacement = document.createElement("div");
 replacement.innerHTML = addStylesNode.textContent;
 document.body.appendChild(replacement)
 addStylesNode.parentElement.removeChild(addStylesNode);
 };
 var raf = window.requestAnimationFrame ||
 window.mozRequestAnimationFrame ||
 window.webkitRequestAnimationFrame ||
 window.msRequestAnimationFrame;
 if (raf)
 raf(function () {
 window.setTimeout(loadDeferredStyles, 0);
 });
 else {
 window.addEventListener('load', loadDeferredStyles);
 }*/
