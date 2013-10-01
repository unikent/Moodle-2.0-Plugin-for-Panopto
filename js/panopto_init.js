function panoptoInit($) {
	getScript(M.cfg.wwwroot + '/blocks/panopto/js/panopto_tac.js', function() {
		$('#panopto_ts_button').panoptoTac();
	})
}

function getScript(url, success) {
	var script     = document.createElement('script');
	script.src = url;
	var head = Y.one('head');
	var done = false;
	// Attach handlers for all browsers
	script.onload = script.onreadystatechange = function() {
		if (!done && (!this.readyState || this.readyState == 'loaded' || this.readyState == 'complete')) {
		
			done = true;
			// callback function provided as param
			success();
			script.onload = script.onreadystatechange = null;
		};
	};
	head.append(script);
}

// Have we already loaded in jQuery? If not load it and pass it as $ to panopto
if(typeof jQuery == 'undefined') {
	getScript(M.cfg.wwwroot + '/blocks/panopto/js/jquery-1.9.1.min.js', function() {
		panoptoInit(jQuery);
	})
} else {
	panoptoInit(jQuery);
}