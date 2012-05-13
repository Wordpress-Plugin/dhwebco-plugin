jQuery(document).ready(function($){
	if ($('.datepicker').length > 0) {
		load_jqui(function(){
			load_jqui_component('datepicker', function(){
				$('.datepicker').datepicker();
			})
		});
	}
});

function load_jqui(callback) {
	if (!jQuery.ui) {
		jQuery.getScript(dhwebco_plugin.base_url + 'js/jquery-ui/jquery.ui.core.min.js', callback);
	} else {
		callback();
	}
}

function load_jqui_component(component, callback) {
	var url = dhwebco_plugin.base_url + 'js/jquery-ui/jquery.ui.' + component + '.min.js';
	jQuery.getScript(url, callback);
}