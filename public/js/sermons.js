/**
 * Sermon Suite — Public JavaScript
 */
(function($) {
    'use strict';

    // ── Sermon notes collapsible toggle (old .gcc- selector + new .ss- selector) ──
    $(document).on('click', '.gcc-notes-toggle, .ss-notes-toggle', function() {
        var $btn     = $(this);
        var expanded = $btn.attr('aria-expanded') === 'true';
        $btn.attr('aria-expanded', String(!expanded));
        var $body = $btn.next('.gcc-notes-body, .ss-notes-body');
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (expanded) {
            // Play the close animation, then hide. Reduced motion has no
            // animation (so no animationend) — hide straight away.
            if (reduce) { $body.attr('hidden', ''); return; }
            $body.addClass('is-closing').one('animationend', function() {
                $body.removeClass('is-closing').attr('hidden', '');
            });
        } else {
            $body.removeClass('is-closing').off('animationend').removeAttr('hidden');
        }
    });

})(jQuery);
