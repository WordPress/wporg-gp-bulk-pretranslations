(function($) {
    $(document).ready(function() {
        $( '#bulk-actions-toolbar-top .button, #bulk-actions-toolbar-bottom .button' ).click(
            function( e ) {
                let pretranslationValues = ['bulk-pretranslation-tm', 'bulk-pretranslation-openai', 'bulk-pretranslation-deepl'];
                if (pretranslationValues.includes($('select[name="bulk[action]"]').val())) {
                    $( 'form.filters-toolbar.bulk-actions, form#bulk-actions-toolbar-top' ).submit();
                    e.preventDefault();
                    e.stopImmediatePropagation();
                }
            }
        );
    });
})(jQuery);
