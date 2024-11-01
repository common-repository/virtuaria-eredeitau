jQuery(document).ready(function() {
    setTimeout(function(){
        setInterval(fetch_payment_erede_status, 8000);
    }, 20000);
});

function fetch_payment_erede_status() {
    if ( jQuery('.on-hold-payment-erede').length > 0 ) {
        jQuery.ajax({
            type:		'POST',
            url:		payment.ajax_url,
            data:		{
                action: 'erede_fetch_payment_order',
                order_id: payment.order_id,
                payment_nonce: payment.nonce
            },
            success: function( response ) {
                if ( 'success' === response ) {
                    jQuery('.pix-payment').fadeOut(1500, function(){
                        jQuery('.pix-payment').show().html('<div class="paid">' + payment.confirm_message + '</div>');
                    });
                }
            },
            error: function() {
                console.log('Falha ao consultar status do pedido');
            }
        });
    }
}