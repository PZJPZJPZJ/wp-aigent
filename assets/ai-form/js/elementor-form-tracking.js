/**
 * dataLayer tracking for successful Elementor form submissions.
 */
(function ($) {
    'use strict';

    $(document).on('submit_success', function () {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'elementor_form_submit_success'
        });
    });
})(jQuery);
