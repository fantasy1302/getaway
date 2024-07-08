const successCallback = function( data ) {

    const checkoutForm = $( 'form.woocommerce-checkout' )

    // add a token to our hidden input field
    // console.log(data) to find the token
    checkoutForm.find( '#misha_token' ).val( data.token )

    // deactivate the tokenRequest function event
    checkoutForm.off( 'checkout_place_order', tokenRequest )

    // submit the form now
    checkoutForm.submit()

}

const errorCallback = function( data ) {
    console.log( data )
}

const tokenRequest = function() {

    // here will be a payment gateway function that process all the card data from your form,
    // maybe it will need your Publishable API key which is misha_params.publishableKey
    // and fires successCallback() on success and errorCallback on failure
    return false

}

jQuery( function( $ ){

    const checkoutForm = $( 'form.woocommerce-checkout' )
    checkoutForm.on( 'checkout_place_order', tokenRequest )

})