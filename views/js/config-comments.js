function gestionProductCommentsAllowAlreadyOrdered() {
    if($('#PRODUCT_COMMENTS_ALLOW_GUESTS_on').length == 1) {
        if ($('#PRODUCT_COMMENTS_ALLOW_GUESTS_on').is( ":checked" )) {
            $('#PRODUCT_COMMENTS_ALLOW_ALREADY_ORDERED_off').prop("checked", true);
            $('#PRODUCT_COMMENTS_ALLOW_ALREADY_ORDERED_off').closest('.form-group').hide();
        } else {
            $('#PRODUCT_COMMENTS_ALLOW_ALREADY_ORDERED_off').closest('.form-group').show();
        }
    }
}
$( document ).ready(function() {
    gestionProductCommentsAllowAlreadyOrdered();
    $('#PRODUCT_COMMENTS_ALLOW_GUESTS_on, #PRODUCT_COMMENTS_ALLOW_GUESTS_off').change(function() {
        gestionProductCommentsAllowAlreadyOrdered();
    });
});