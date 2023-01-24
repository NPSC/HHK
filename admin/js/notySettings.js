Noty.overrideDefaults({
    layout   : 'top',
    theme    : 'semanticui',
    timeout  : '4000',
    progressBar : true,
    closeWith: ['button'],
    animation: {
        open : 'animated bounceInDown',
        close: 'animated bounceOutUp'
    },
    callbacks: {
    	afterShow: function(){
    		//set up custom close handler
    		$(this.barDom).on('click', function(){
    			let barDom = $(this);
				setTimeout(()=>{barDom.hide();}, 500);
				$(this).addClass('animated bounceOutUp');
    		});
    	}
    }
});