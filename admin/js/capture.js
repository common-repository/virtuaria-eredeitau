jQuery(document).on( 'click', '.capture_transaction', function() {
    jQuery('#virt_rede_transaction_id').val(jQuery(this).attr('data-id'));
});