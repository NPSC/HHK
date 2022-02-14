(function ($) {

    $.fn.rssWidget = function (options) {
    
        var defaults = {
            url: '',
            postCount: 3
        };

        var settings = $.extend(true, {}, defaults, options);
        
        
        var $wrapper = $(this);

		loadRSS($wrapper, settings);

        return this;
        
    }
    
    function loadRSS($wrapper, settings){
    
    	if(settings.url){
    		$.ajax({
                url: settings.url,
                dataType: 'xml',
                type: 'GET',
                success: function( xml ){
                	var content = '';
                    $.each($("item", xml), function(index, item){

                    	if(index >= settings.postCount){
                    		return false;
                    	}
                    	var itemURL = $(item).find("link").text();
                    	var itemTitle = $(item).find("title").text();
                    	var itemDescription = $(item).find("description").text();
                    	content += `<div class="item p-3">
                    		<h4><a href="`+ itemURL + `" target="_blank">` + itemTitle + `</a></h4>
                    		<div class="item-content">` + itemDescription + `</div>
                    	</div>`;

                    });
                    $wrapper.html(content);
                },
                error: function( data ){
                	var content = '<div class="p-3 center">Failed to load data</div>';
                    
                    $wrapper.html(content);
                }
            });
    	}
    }
    
}(jQuery));