jQuery(document).ready(function($){
    $('.woocommerce-save-button').on('click', function() {
        let selected_cats = [];
        $('#' + field_key + ' #product_cat-all #product_catchecklist input[type="checkbox"]:checked').each(function(i, v){
            selected_cats.push($(v).val());
        });
        $('#' + field_key).val(selected_cats);
    })
});