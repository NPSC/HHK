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
});

$(document).on('click', '.noty_bar', function(){
	console.log("Banner clicked");
	let barDom = $(this);
	setTimeout(()=>{barDom.hide();}, 500);
	barDom.addClass('animated bounceOutUp');
});