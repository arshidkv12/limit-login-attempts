jQuery(document).ready(function($) {
if(popup_flag!="true_0152"){
var overlay = $('<div id="overlay"></div>');
overlay.show();
overlay.appendTo(document.body);
$('.popup').show();
$('.close').click(function(){
$('.popup').hide();
overlay.appendTo(document.body).remove();
return false;
});

$('.x').click(function(){
$('.popup').hide();
overlay.appendTo(document.body).remove();
return false;
});
$('.submit').click(function(){
$('.popup').hide();
overlay.appendTo(document.body).remove();
return true;
});
}
});
 