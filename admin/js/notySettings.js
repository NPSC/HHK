Noty.overrideDefaults({
    layout   : 'top',
    theme    : 'semanticui',
    timeout  : '4000',
    progressBar : true,
    closeWith: ['click','button'],
    animation: {
        open : 'animated bounceInDown',
        close: 'animated bounceOutUp'
    }
});

//Close banner messages when accessing navbar dropdowns
$(document).on('show.bs.dropdown', '.navbar .dropdown', function(){
	Noty.closeAll();
});