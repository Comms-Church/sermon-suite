/**
 * Sermon Suite — Admin JavaScript
 */
(function($) {
    'use strict';

    // Inline validation message under a field, instead of an alert() on submit.
    // Clears itself as soon as the field changes.
    window.ssFieldError = function($el, msg) {
        var id = ($el.attr('id') || 'ss-field') + '-error';
        var $msg = $('#' + id);
        if (!$msg.length) $msg = $('<p class="gcc-field-error" role="alert"></p>').attr('id', id).insertAfter($el);
        $msg.text(msg);
        $el.attr({ 'aria-invalid': 'true', 'aria-describedby': id }).trigger('focus');
        $el.one('input change', function() {
            $el.removeAttr('aria-invalid aria-describedby');
            $msg.remove();
        });
    };
})(jQuery);
