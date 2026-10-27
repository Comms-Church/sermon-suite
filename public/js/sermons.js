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

    // ── Share: the system share sheet where there is one, else copy the link ─
    $(document).on('click', '.ss-share-btn', function() {
        var $btn = $(this), $status = $btn.siblings('.ss-share-status');
        var data = { title: $btn.data('title'), url: $btn.data('url') || window.location.href };
        function say(msg) {
            $status.text(msg);
            setTimeout(function() { $status.text(''); }, 2500);
        }
        if (navigator.share) {
            navigator.share(data).catch(function() {});  // dismissing the sheet isn't an error
        } else if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(data.url).then(function() { say('Link copied'); },
                function() { window.prompt('Copy this link:', data.url); });
        } else {
            window.prompt('Copy this link:', data.url);
        }
    });

    // ── Deep links: /sermon/#discussion-guide opens that section ─────────────
    function openFromHash() {
        var id = window.location.hash.slice(1);
        if (!/^[a-z-]+$/.test(id)) return;
        var $block = $('#' + id + '.ss-notes-block');
        if (!$block.length) return;
        var $btn = $block.children('.ss-notes-toggle');
        if ($btn.attr('aria-expanded') !== 'true') $btn.trigger('click');
        $block[0].scrollIntoView({ block: 'start' });
        $btn.trigger('focus');
    }
    $(openFromHash);
    $(window).on('hashchange', openFromHash);

    // ── Start below a theme header that sits over the page ──────────────────
    // Some themes (Bricks' transparent sticky header, for one) fix the header
    // over the top of every page, expecting a full-width hero beneath it. Ours
    // open with links instead, which the header would cover and swallow clicks
    // on, so measure how far it reaches into the page and start below it.
    function clearFixedHeader() {
        var page = document.querySelector('.ss-sermon-page, .gcc-single-series, .ss-tax-page');
        if (!page) return;
        var root = document.documentElement;
        root.style.removeProperty('--ss-header-offset');
        var pageTop = page.getBoundingClientRect().top + window.pageYOffset;
        var reach = 0;
        $('body > *, header, [role="banner"]').not('#wpadminbar').each(function() {
            var cs = getComputedStyle(this);
            if (cs.position !== 'fixed' || this.offsetWidth < window.innerWidth / 2) return;
            var top = parseFloat(cs.top);
            if (!(top >= 0 && top < 100)) return;  // pinned to the top, not a bottom bar
            // offsetHeight ignores the transform a hide-on-scroll header slides away with.
            reach = Math.max(reach, top + this.offsetHeight);
        });
        if (reach > pageTop) root.style.setProperty('--ss-header-offset', Math.ceil(reach - pageTop) + 'px');
    }
    $(clearFixedHeader);
    $(window).on('load', clearFixedHeader);
    var resizeTimer;
    $(window).on('resize', function() { clearTimeout(resizeTimer); resizeTimer = setTimeout(clearFixedHeader, 150); });

    // ── Series list: start scrolled to the current message ──────────────────
    // The list scrolls inside its card, so message 16 of 22 would otherwise sit
    // below the fold. Scroll the list itself, never the page.
    $(function() {
        $('.ss-series-list').each(function() {
            var current = $(this).find('.is-current').closest('li')[0];
            if (!current) return;
            this.scrollTop = current.offsetTop - (this.clientHeight - current.offsetHeight) / 2;
        });
    });

})(jQuery);
