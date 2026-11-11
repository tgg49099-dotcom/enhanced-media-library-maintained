  ( function( $ ) {

    var orderValue;



    $( document ).ready( function() {

        orderValue = $('#wpuxss_eml_lib_options_media_order').val();
        $('#wpuxss_eml_lib_options_media_orderby').trigger( 'change' );
        $('#wpuxss_eml_lib_options_grid_show_caption').trigger( 'change' );
        $('#wpuxss_eml_lib_options_search_on_enter').trigger( 'change' );
        $('#wpuxss_eml_lib_options_search_auto').trigger( 'change' );
    });



    $( document ).on( 'change', '#wpuxss_eml_lib_options_media_orderby', function( event ) {

        var isMenuOrder = 'menuOrder' === $( event.target ).val(),
            isTitleOrder = 'title' === $( event.target ).val(),
            value;

        orderValue = isMenuOrder ? $('#wpuxss_eml_lib_options_media_order').val() : orderValue;
        value = isMenuOrder ? 'ASC' : orderValue;

        $('#wpuxss_eml_lib_options_media_order').prop( 'disabled', isMenuOrder ).val( value );
        $('#wpuxss_eml_lib_options_natural_sort').prop( 'hidden', ! isTitleOrder );
    });



    $( document ).on( 'change', '#wpuxss_eml_lib_options_grid_show_caption', function( event ) {

        var isChecked = $(this).prop( 'checked' );

        $('#wpuxss_eml_lib_options_grid_caption_type').prop( 'hidden', ! isChecked );
    });


    $( document ).on( 'click', 'input[readonly], .disabled .submit input.button', function( event ) {
        event.preventDefault();
    });


    $( document ).on( 'change', '#wpuxss_eml_lib_options_search_in input[type=checkbox].search_columns', function( event ) {

        if ( ! $( '#wpuxss_eml_lib_options_search_in input.search_columns:checked' ).length ) {
            $( event.target ).prop( 'checked', true );
        }
    });


    $( document ).on( 'change', '#wpuxss_eml_lib_options_search_on_enter', function( event ) {

        var isChecked = $(this).prop( 'checked' );

        if ( ! isChecked ) {
            $('#wpuxss_eml_lib_options_search_auto').prop( 'checked', true ).trigger( 'change' );
        }
        $('#wpuxss_eml_lib_options_search_auto').prop( 'disabled', ! isChecked );
    });

    $( document ).on( 'change', '#wpuxss_eml_lib_options_search_auto', function( event ) {

        var isChecked = $(this).prop( 'checked' );

        $('#wpuxss_eml_lib_options_search_min_letters').prop( 'hidden', ! isChecked );
    });

})( jQuery );
