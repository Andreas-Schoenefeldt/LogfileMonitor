jQuery(document).ready(function(){
	Widgets.initSome($("body"), ["showAditionals", "traceoverlay"]);
});

/*
 * Widgets are defined always by classes starting with widget_ 
 * The widget Class will be removed after the widget was applyed. 
 * Please do not call directly the init functions but the initSome function
 * 
 * There are also trigger_ classes, which are connected to a certain widget and will not be removed also after the widget was applied
 * 
 * the classes are in the template always with the prefix widget_ eg. widget_hideShowInput
 * the syntax is widget_<widgetname>
 * add the widget name to the RegisteredWidgets function as prototype function (evaluates faster then object notation), to register a new widget and add a function with the widget name
 * 
 */
function widgets () {}
widgets.prototype.widgetClassPrefix = "widget_";
/* The main initialising function of the widgets
 * 
 * param root			jQuery List of the root element
 * @param classList 	Array of widget classes. Will be used as selector 
 */
widgets.prototype.initSome = function (root, classList) {
	
	for (var i = 0; i < classList.length; i++){
		var wClass = classList[i];
		var result = root.find("." + this.widgetClassPrefix + wClass);
		
		if (result.length) {
			for(var k = 0; k < result.length; k++){
				this.initSpecific($(result.get(k)), wClass);
			}
		}
	}		
	
};	
widgets.prototype.initSpecific = function(elem, wClass){
	
	try {
		this.registered[wClass](elem);
		elem.removeClass(this.widgetClassPrefix + wClass);
	} catch (e) {
		debug(e.message);
		debug("No method for widget " + wClass + " provided or error during execution.");
		return false;
	}
	
	return true;		
};
		
		
widgets.prototype.registered = {
	// a overlaywidget
	"traceoverlay" : function (elem) {
		var b = $("body");
		
		elem.bind("click", function(e){
			var	elem = $(this),
				ov = elem.find(".overlay"),
				b = $("body"),
				modal = b.find('#ov_modal_overlay'),
				func = function(){
					ov.fadeOut(200);
					modal.unbind('click').fadeOut(200);
					elem.append(ov);
				}
			;
				
			e.stopPropagation();
			
			if (! ov.find('.close_btn').length) ov.prepend($('<a class="fr close_btn minibutton">close</a>').bind('click', func));
			modal.bind('click', func);
			
			b.append(ov.hide().removeClass("hidden").fadeIn(300));
			modal.fadeIn(300);
		})
		
		if (! b.find('#ov_modal_overlay').length) {
			b.append('<div id="ov_modal_overlay" style="display:none;"><!-- Karmapa Tchenno --></div>');
		}
	},
	
	"showAditionals": function (elem) {
		var aditionals = elem.find('.aditionals').hide();
		elem.data('open', 0);
		elem.bind('click', function(){
			var elem = $(this);
			
			if (elem.data('open') == 1) {
				elem.find('.aditionals').slideUp();
				elem.data('open', 0);
			} else {
				elem.find('.aditionals').slideDown();
				elem.data('open', 1);
			}
		});
	}
}

var Widgets = new widgets();

// --------- Fixed Fade Function ------------------------ 

function fade(element, opacity, duration, cbFunction, retension){
	
	var callback = null;
	
	switch (opacity){
		default: 
			callback = function(){
				// fallback for ie to remove the filter.
				if ($.browser.msie) {
					var opacString = opacity * 100;
				}		
				
				// user defined callback
				if (cbFunction) cbFunction($(this));
			}
			break;
		case 0:
			callback = function(){
				$(this).css("display", "none");
				
				// user defined callback
				if (cbFunction) cbFunction($(this));
			}
			break;
		case 1:
			callback = function(){
				// fallback for ie to remove the filter.
				if ($.browser.msie) {
					$(this).css("filter", "").attr("style", $(this).attr("style").replace("filter:", ""));
					
				}
				
				// user defined callback
				if (cbFunction) cbFunction($(this));
			}
			break;
	
	}
	
	element.stop(true);
	
	var opac = element.css("opacity");
	if (element.css("display") == "none") opac = 0;
	
	var css = {"display": "block", "opacity": opac};
	
	element.css(css);
	if (retension) {
		element.animate({"opacity" : opac}, retension).animate({"opacity" : opacity}, duration, callback);
	} else {
		element.animate({"opacity" : opacity}, duration, callback);
	}
	
}

//--------- Debugging functions to wrap the firebug -------------------
function d(val) {debug(val);}
function debug() {
	if (typeof(console) != "undefined") {
		switch (arguments.length){
			case 1:
				console.log(arguments[0]);
				break;
			case 2:
				console.log(arguments[0], arguments[1]);
				break;
			case 3:
				console.log(arguments[0], arguments[1], arguments[2]);
				break;
			case 4:
				console.log(arguments[0], arguments[1], arguments[2], arguments[3]);
				break;
		}
	}
}

function trace() {
	if (typeof(console) != "undefined") {
		console.trace();
	}
}