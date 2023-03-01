Noty.overrideDefaults({
    layout   : 'top',
    theme    : 'semanticui',
    timeout  : '5000',
    progressBar : true,
    closeWith: ['button'],
    animation: {
        open : 'animated bounceInDown',
        close: 'animated bounceOutUp'
    },
});

//custom click handler
$(document).on('click', '.noty_bar', function(){
	console.log("Banner clicked");
	let barDom = $(this);
	setTimeout(()=>{console.log("Banner hiding...");barDom.hide();}, 500);
	barDom.addClass('animated bounceOutUp');
});