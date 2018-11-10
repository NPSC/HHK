
(function () {
    var initialized = false;
var scripts = document.getElementsByTagName('script');
var myScript = scripts[ scripts.length - 1 ];

var queryString = myScript.src.replace(/^[^\?]+\??/,'');
var vals = new Object(); 

for(var i = 0; i < myScript.attributes.length; i++) {
	vals[myScript.attributes[i].name] = myScript.attributes[i].value;
}



cookieScripts = {};
    /*
    name - name of the cookie
    value - value of the cookie
    [expires] - expiration date of the cookie
    (defaults to end of current session)
    [path] - path for which the cookie is valid
    (defaults to path of calling document)
    [domain] - domain for which the cookie is valid
    (defaults to domain of calling document)
    [secure] - Boolean value indicating if the cookie transmission requires
    a secure transmission
    * an argument defaults when it is assigned null as a placeholder
    * a null placeholder is not required for trailing omitted arguments
    */
cookieScripts.isSecure = function () {
    return true;
}
cookieScripts.setCookie = function (name, value, expires, path, domain) {

    var curCookie = name + "=" + escape(value) +
	  ((expires) ? "; expires=" + expires.toGMTString() : "") +
	  ((path) ? "; path=" + path : "") +
	  ((domain) ? "; domain=" + domain : "") +
	  ((cookieScripts.isSecure()) ? "; secure" : "");
    document.cookie = curCookie;
}


    /*
    name - name of the desired cookie
    return string containing value of specified cookie or null
    if cookie does not exist
    */

cookieScripts.getCookie = function (name) {
    var dc = document.cookie;
    var prefix = name + "=";
    var begin = dc.indexOf("; " + prefix);
    if (begin == -1) {
        begin = dc.indexOf(prefix);
        if (begin != 0) return null;
    } else
        begin += 2;
    var end = document.cookie.indexOf(";", begin);
    if (end == -1)
        end = dc.length;
    return unescape(dc.substring(begin + prefix.length, end));
}


    /*
    name - name of the cookie
    [path] - path of the cookie (must be same as path used to create cookie)
    [domain] - domain of the cookie (must be same as domain used to
    create cookie)
    path and domain default if assigned null or omitted if no explicit
    argument proceeds
    */

cookieScripts.deleteCookie = function (name, path, domain) {
    if (cookieScripts.getCookie(name)) {
        document.cookie = name + "=" +
	((path) ? "; path=" + path : "") +
	((domain) ? "; domain=" + domain : "") +
	"; expires=Thu, 01-Jan-70 00:00:01 GMT";
    }
}

var InstaMed = {
    onReady: function () {
        if (InstaMed.isInContext()) {
            InstaMed.init();
        }

        InstaMed.handleSafari(InstaMed.getHostname());
    },
    handleSafari: function (url) {
		if(url == undefined || url == null || url == '') {
			return false;
		}
        if (InstaMed.isSafari() && InstaMed.isEmbedded()) {
            var cookie_name = 'safari_cookie_fix-' + url;
            var val = cookieScripts.getCookie(cookie_name);
            if (val == undefined || val == null || val == '') {
                var vr = new Date();
                vr.setDate(vr.getDate() + 1);
                cookieScripts.setCookie(cookie_name, 'fixed', vr, '/', null);
                window.location.href = url + 'Form/Payments/Init?lightWeight=true';
                return true;
            }
        }
        return false;
    },
	isInContext: function () {
        if (InstaMed.isMobile()) {
            return vals['data-patientportaldisplaymode'] == 'incontext';
        }
        return vals['data-displaymode'] == 'incontext';
    },
    isEmbedded: function () {
        if (InstaMed.isMobile()) {
            return vals['data-mobiledisplaymode'] == 'embedded';
        }
        return vals['data-displaymode'] == 'embedded';
    },
    isIE: function () {
        return !InstaMed.isOpera() && (InstaMed.check(/msie/) || InstaMed.check(/trident/));
    },
    isIE7: function () {
        return false;// InstaMed.isIE() && ((InstaMed.check(/msie 7/) && InstaMed.docMode() != 8 && InstaMed.docMode() != 9 && InstaMed.docMode() != 10) || InstaMed.docMode() == 7);
    },
    isSafari: function () {

        var userAgentString = navigator.userAgent || navigator.vendor || window.opera;
        return userAgentString && userAgentString.indexOf('Safari') != -1 && userAgentString.indexOf('Chrome') == -1;

        return isSafari;
    },
    doPopup: function () {
        return /*InstaMed.isSafari() ||*/ (InstaMed.isMobile() && !InstaMed.isEmbedded() && !InstaMed.isInContext());
    },
    isMobile: function () {

        var userAgentString = navigator.userAgent || navigator.vendor || window.opera;
        if (userAgentString != null && ((userAgentString.indexOf("iPhone") != -1 || userAgentString.indexOf("iPad") != -1 ||
            //(context.Request.Browser.MobileDeviceModel == "iPhone" && context.Request.Browser.MobileDeviceModel == "Apple")) ||
                (userAgentString.indexOf("Android") != -1 /*&& userAgentString.Contains("Mobile")*/) ||
                (userAgentString.indexOf("Windows Phone OS") != -1) ||
                (userAgentString.indexOf("BB10") != -1) ||
                 (userAgentString.indexOf("iPod") != -1 /*|| (context.Request.Browser.MobileDeviceModel == "iPod" && context.Request.Browser.MobileDeviceModel == "Apple")*/)))) {
            return true;
        }
        return false;

    },
    launch: function (page) {
        if (InstaMed.doPopup()) {
            InstaMed.childWindow = window.open(page);
        }
        else {
			AddIFrame();
            document.getElementById('instamed').setAttribute('src', page);
        }
    },
    init: function () {
        if (!initialized) {
            initialized = true;
        }
        if (!InstaMed.doPopup() && InstaMed.isInContext() && !InstaMed.isMobile()) {
            AddIFrame();
        }
    },
    check: function (regex) {
        return regex.test(navigator.userAgent.toLowerCase());
    },
    isOpera: function () {
        return InstaMed.check(/opera/);
    },
    docMode: function () {
        return document.documentMode;
    },
	getHostname: function () {
        return vals['data-hostname'];
    }
};

/*!
* 
* Portions derived from
* jQuery postMessage - v0.5 - 9/11/2009
* http://benalman.com/projects/jquery-postmessage-plugin/
* 
* Copyright (c) 2009 "Cowboy" Ben Alman
* Dual licensed under the MIT and GPL licenses.
* http://benalman.com/about/license/
*/

//'$:nomunge'; // Used by YUI compressor.
var PostMessageHelper = {
    // A few vars used in non-awesome browsers.
    interval_id: undefined,
    last_hash: undefined,
    cache_bust: 1,

    // A var used in awesome browsers.
    rm_callback: undefined,
    p_receiveMessage: undefined,
    // A few convenient shortcuts.
    window: this,
    FALSE: !1,

    // Reused internal strings.
    postMessage: 'postMessage',
    addEventListener: 'addEventListener',

    // I couldn't get window.postMessage to actually work in Opera 9.64!
    has_postMessage: function () {
        return !InstaMed.isIE7() && (!InstaMed.isIE() || vals['data-displaymode'] != 'window')
	&& PostMessageHelper.isFunction(window['postMessage']);
    },
    isFunction: function (functionToCheck) {
        var getType = {};
        return functionToCheck && getType.toString.call(functionToCheck) === '[object Function]';
    },
    // Method: jQuery.postMessage
    // 
    // This method will call window.postMessage if available, setting the
    // targetOrigin parameter to the base of the target_url parameter for maximum
    // security in browsers that support it. If window.postMessage is not available,
    // the target window's location.hash will be used to pass the message. If an
    // object is passed as the message param, it will be serialized into a string
    // using the jQuery.param method.
    // 
    // Usage:
    // 
    // > jQuery.postMessage( message, target_url [, target ] );
    // 
    // Arguments:
    // 
    //  message - (String) A message to be passed to the other frame.
    //  message - (Object) An object to be serialized into a params string, using
    //    the jQuery.param method.
    //  target_url - (String) The URL of the other frame this window is
    //    attempting to communicate with. This must be the exact URL (including
    //    any query string) of the other window for this script to work in
    //    browsers that don't support window.postMessage.
    //  target - (Object) A reference to the other frame this window is
    //    attempting to communicate with. If omitted, defaults to `parent`.
    // 
    // Returns:
    // 
    //  Nothing.

    doPostMessage: function (message, target_url, target) {
        if (!target_url) { return; }

        // Serialize the message if not a string. Note that this is the only real
        // jQuery dependency for this script. If removed, this script could be
        // written as very basic JavaScript.
        message = typeof message === 'string' ? message : message/*$.param( message )*/;

        // Default to parent if unspecified.
        target = target || parent;

        if (PostMessageHelper.has_postMessage) {
            // The browser supports window.postMessage, so call it with a targetOrigin
            // set appropriately, based on the target_url parameter.
            target['postMessage'](message, target_url.replace(/([^:]+:\/\/[^\/]+).*/, '$1'));

        } else if (target_url) {
            // The browser does not support window.postMessage, so set the location
            // of the target to target_url#message. A bit ugly, but it works! A cache
            // bust parameter is added to ensure that repeat messages trigger the
            // callback.
            target.location = target_url.replace(/#.*$/, '') + '#' + (+new Date) + (PostMessageHelper.cache_bust++) + '&' + message;
        }
    },

    // Method: jQuery.receiveMessage
    // 
    // Register a single callback for either a window.postMessage call, if
    // supported, or if unsupported, for any change in the current window
    // location.hash. If window.postMessage is supported and source_origin is
    // specified, the source window will be checked against this for maximum
    // security. If window.postMessage is unsupported, a polling loop will be
    // started to watch for changes to the location.hash.
    // 
    // Note that for simplicity's sake, only a single callback can be registered
    // at one time. Passing no params will unbind this event (or stop the polling
    // loop), and calling this method a second time with another callback will
    // unbind the event (or stop the polling loop) first, before binding the new
    // callback.
    // 
    // Also note that if window.postMessage is available, the optional
    // source_origin param will be used to test the event.origin property. From
    // the MDC window.postMessage docs: This string is the concatenation of the
    // protocol and "://", the host name if one exists, and ":" followed by a port
    // number if a port is present and differs from the default port for the given
    // protocol. Examples of typical origins are https://example.org (implying
    // port 443), http://example.net (implying port 80), and http://example.com:8080.
    // 
    // Usage:
    // 
    // > jQuery.receiveMessage( callback [, source_origin ] [, delay ] );
    // 
    // Arguments:
    // 
    //  callback - (Function) This callback will execute whenever a <jQuery.postMessage>
    //    message is received, provided the source_origin matches. If callback is
    //    omitted, any existing receiveMessage event bind or polling loop will be
    //    canceled.
    //  source_origin - (String) If window.postMessage is available and this value
    //    is not equal to the event.origin property, the callback will not be
    //    called.
    //  source_origin - (Function) If window.postMessage is available and this
    //    function returns false when passed the event.origin property, the
    //    callback will not be called.
    //  delay - (Number) An optional zero-or-greater delay in milliseconds at
    //    which the polling loop will execute (for browser that don't support
    //    window.postMessage). If omitted, defaults to 100.
    // 
    // Returns:
    // 
    //  Nothing!

    receiveMessage: function (callback, source_origin, delay) {
        if (PostMessageHelper.has_postMessage) {
            // Since the browser supports window.postMessage, the callback will be
            // bound to the actual event associated with window.postMessage.

            if (callback) {
                // Unbind an existing callback if it exists.
                PostMessageHelper.rm_callback && PostMessageHelper.p_receiveMessage();

                // Bind the callback. A reference to the callback is stored for ease of
                // unbinding.
                PostMessageHelper.rm_callback = function (e) {
                    if ((typeof source_origin === 'string' && e.origin !== source_origin)
            || (PostMessageHelper.isFunction(source_origin) && source_origin(e.origin) === FALSE)) {
                        return FALSE;
                    }
                    callback(e);
                };
            }

            if (window[PostMessageHelper.addEventListener]) {
                window[callback ? PostMessageHelper.addEventListener : 'removeEventListener']('message', PostMessageHelper.rm_callback, PostMessageHelper.FALSE);
            } else {
                window[callback ? 'attachEvent' : 'detachEvent']('onmessage', PostMessageHelper.rm_callback);
            }

        } else {
            // Since the browser sucks, a polling loop will be started, and the
            // callback will be called whenever the location.hash changes.

            PostMessageHelper.interval_id && clearInterval(PostMessageHelper.interval_id);
            PostMessageHelper.interval_id = null;

            if (callback) {
                delay = typeof source_origin === 'number'
          ? source_origin
          : typeof delay === 'number'
            ? delay
            : 100;

                PostMessageHelper.interval_id = setInterval(function () {
                    var hash = document.location.hash,
            re = /^#?\d+&/;
                    if (hash !== PostMessageHelper.last_hash && re.test(hash)) {
                        PostMessageHelper.last_hash = hash;
                        callback({ data: hash.replace(re, '') });
                    }
                }, delay);
            }
        }
    }

};

//PostMessageHelper.has_postMessage = false;
//PostMessageHelper.p_receiveMessage = PostMessageHelper.receiveMessage;
function doHandleMessage(message) {
    
	var obj = {};
	if(typeof JSON == "undefined") {
		eval('obj = ' + message.data + ';');
	}
	else {
		obj = JSON.parse(message.data);
	}
	if(obj.closeInContext) {
		var iframe = document.getElementById('instamed');
		if(iframe && iframe.getAttribute('data-incontext') == 'true') {
			iframe.style.visibility = 'hidden';
            iframe.style.opacity = 0;
			document.body.style.overflow = 'visible';	
		}
	}
	if(obj.returnURL) {
		window.location.href = obj.returnURL;
	}
	if (obj.redirectIframeUrl) {
	    //InstaMed.launch(message.source.location.href);
		InstaMed.launch(obj.redirectIframeUrl);
	}
	if(obj.launchInContext) {
		document.body.style.overflow = 'hidden'; 
        var iframe = document.getElementById('instamed');
		iframe.style.visibility = 'visible';
        iframe.style.opacity = 1; 
	}
	if (window.location.hash != undefined && window.location.hash != null && window.location.hash != '' && !PostMessageHelper.has_postMessage()) {
	    window.location.hash = '';
	}
}
PostMessageHelper.receiveMessage(doHandleMessage);

function parseQuery ( query ) {
   var Params = new Object ();
   if ( ! query ) return Params; // return empty object
   var Pairs = query.split(/[;&]/);
   for ( var i = 0; i < Pairs.length; i++ ) {
      var KeyVal = Pairs[i].split('=');
      if ( ! KeyVal || KeyVal.length != 2 ) continue;
      var key = unescape( KeyVal[0] );
      var val = unescape( KeyVal[1] );
      val = val.replace(/\+/g, ' ');
      Params[key] = val;
   }
   return Params;
}

function AddIFrame(page) {

    if (document.getElementsByName('instamed').length != 0) {
        return;
    }
    var url = page + "?incontext=true";  
    var iframe = document.createElement('IFRAME');
	iframe.name = 'instamed';
    iframe.setAttribute('frameborder', '0');
    iframe.setAttribute('allowtransparency', 'true');
	iframe.setAttribute('name', 'instamed');
    if(!InstaMed.isIE7()) {
	iframe.setAttribute('style', "z-index: 9999; display: block; background-color: transparent; border: 0px none transparent; overflow-x: hidden; overflow-y: auto; visibility: hidden; margin: 0px; padding: 0px; -webkit-tap-highlight-color: transparent; position: fixed; left: 0px; top: 0px; width: 100%; height: 100%;");
	}
	else {
		iframe.style.setAttribute("z-index","9999");
		iframe.style.setAttribute("display","block");
		iframe.style.setAttribute("background-color","transparent");
		iframe.style.setAttribute("border","0px none transparent");
		iframe.style.setAttribute("overflow-x","hidden");
		iframe.style.setAttribute("overflow-y","auto");
		iframe.style.setAttribute("visibility","hidden");
		iframe.style.setAttribute("margin","0px");
		iframe.style.setAttribute("padding","0px");
		iframe.style.setAttribute("-webkit-tap-highlight-color","transparent");
		iframe.style.setAttribute("position","fixed");
		iframe.style.setAttribute("left","0px");
		iframe.style.setAttribute("top","0px");
		iframe.style.setAttribute("width","100%");
		iframe.style.setAttribute("height","100%");
	}
    iframe.setAttribute('id', 'instamed');
	iframe.setAttribute('data-incontext','true');
    //iframe.setAttribute("src", url);
    document.body.appendChild(iframe);
	
	if(InstaMed.isIE7()) {
		window.frames['instamed'].name = 'instamed';
	}
}

function addOnloadEvent(fnc){
  if ( typeof window.addEventListener != "undefined" )
    window.addEventListener( "load", fnc, false );
  else if ( typeof window.attachEvent != "undefined" ) {
    window.attachEvent( "onload", fnc );
  }
  else {
    if ( window.onload != null ) {
      var oldOnload = window.onload;
      window.onload = function ( e ) {
        oldOnload( e );
        window[fnc]();
      };
    }
    else 
      window.onload = fnc;
  }
}

window.InstaMed = InstaMed;
if (typeof define === "function") {
    define("instamed", function () {
        return InstaMed;
    });
}

addOnloadEvent(InstaMed.onReady);

})();