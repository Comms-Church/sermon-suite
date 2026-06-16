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
        if (expanded) {
            $body.attr('hidden', '');
        } else {
            $body.removeAttr('hidden');
        }
    });

    // ── Filter tag active state ───────────────────────────────────────────────
    var params     = new URLSearchParams(window.location.search);
    var activeTopic = params.get('topic');
    if (activeTopic) {
        $('.gcc-tag').each(function() {
            var href = $(this).attr('href') || '';
            $(this).toggleClass('active', href.indexOf('topic=' + activeTopic) !== -1);
        });
    }

})(jQuery);
