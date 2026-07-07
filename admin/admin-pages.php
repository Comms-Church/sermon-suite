<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'sermon_suite_admin_menu' );

function sermon_suite_admin_menu() {
    add_menu_page(
        'Sermon Suite',
        'Sermons',
        'edit_posts',
        'sermon-suite',
        'sermon_suite_admin_dashboard',
        'dashicons-video-alt3',
        25
    );
    // Content management — workflow order
    add_submenu_page( 'sermon-suite', 'All Sermons', 'All Sermons', 'edit_posts',        'edit.php?post_type=ss_sermon', '' );
    add_submenu_page( 'sermon-suite', 'Add Sermon',  'Add Sermon',  'edit_posts',        'ss-add-sermon',  'ss_render_sermon_editor' );
    add_submenu_page( 'sermon-suite', 'All Series',  'All Series',  'edit_posts',        'edit.php?post_type=ss_series', '' );
    add_submenu_page( 'sermon-suite', 'Add Series',  'Add Series',  'edit_posts',        'ss-add-series',  'ss_render_series_editor' );
    add_submenu_page( 'sermon-suite', 'Categories',  'Categories',  'manage_categories', 'edit-tags.php?taxonomy=ss_series_category&post_type=ss_series', '' );
    add_submenu_page( 'sermon-suite', 'Campuses',    'Campuses',    'manage_categories', 'edit-tags.php?taxonomy=ss_campus&post_type=ss_series', '' );
    add_submenu_page( 'sermon-suite', 'Speakers',    'Speakers',    'manage_categories', 'edit-tags.php?taxonomy=ss_speaker&post_type=ss_sermon', '' );
    add_submenu_page( 'sermon-suite', 'Topics',      'Topics',      'manage_categories', 'edit-tags.php?taxonomy=ss_topic&post_type=ss_sermon', '' );
    // Tools
    add_submenu_page( 'sermon-suite', 'Shortcode Generator', '⚡ Shortcodes', 'edit_posts', 'sermon-suite-shortcodes', 'sermon_suite_shortcode_generator_page' );
    add_submenu_page( 'sermon-suite', 'Import from Series Engine', 'Import CSV', 'manage_options', 'sermon-suite-import',   'sermon_suite_import_page' );
    add_submenu_page( 'sermon-suite', 'Settings',      'Settings', 'manage_options', 'sermon-suite-settings', 'sermon_suite_settings_page' );
    add_submenu_page( 'sermon-suite', 'REST API Docs', 'API Docs', 'manage_options', 'sermon-suite-api-docs', 'sermon_suite_api_docs_page' );
    // Edit pages — hidden admin pages (empty parent = never shown in a menu,
    // but fully routable via admin.php?page=...). NOTE: do not use the
    // register-then-remove_submenu_page pattern here; removing the submenu
    // entry breaks WordPress's parent resolution at access time and locks
    // users out with "Sorry, you are not allowed to access this page."
    add_submenu_page( '', 'Edit Sermon', 'Edit Sermon', 'edit_posts', 'ss-edit-sermon', 'ss_render_sermon_editor' );
    add_submenu_page( '', 'Edit Series', 'Edit Series', 'edit_posts', 'ss-edit-series', 'ss_render_series_editor' );
}

// ── Keep the Sermons menu highlighted on CPT and taxonomy screens ─────────────
add_filter( 'parent_file', 'sermon_suite_menu_highlight' );
function sermon_suite_menu_highlight( $parent_file ) {
    global $submenu_file, $current_screen, $plugin_page;
    if ( ! $current_screen ) return $parent_file;

    // Hidden edit pages: keep the Sermons menu open and highlight the right list.
    if ( in_array( $plugin_page, [ 'ss-edit-sermon', 'ss-edit-series' ], true ) ) {
        $submenu_file = ( $plugin_page === 'ss-edit-sermon' )
            ? 'edit.php?post_type=ss_sermon'
            : 'edit.php?post_type=ss_series';
        return 'sermon-suite';
    }

    if ( in_array( $current_screen->post_type, [ 'ss_sermon', 'ss_series' ], true ) ) {
        $parent_file = 'sermon-suite';
        if ( $current_screen->base === 'edit' ) {
            $submenu_file = 'edit.php?post_type=' . $current_screen->post_type;
        } elseif ( $current_screen->base === 'edit-tags' || $current_screen->base === 'term' ) {
            // Speaker/topic terms live on sermons; category/campus on series.
            $tax_pt = in_array( $current_screen->taxonomy, [ 'ss_speaker', 'ss_topic' ], true ) ? 'ss_sermon' : 'ss_series';
            $submenu_file = 'edit-tags.php?taxonomy=' . $current_screen->taxonomy . '&post_type=' . $tax_pt;
        }
    }
    return $parent_file;
}

function sermon_suite_admin_dashboard() {
    $series_count  = wp_count_posts('ss_series')->publish;
    $sermon_count  = wp_count_posts('ss_sermon')->publish;
    $topic_count   = wp_count_terms(['taxonomy' => 'ss_topic', 'hide_empty' => false]);
    $speaker_count = wp_count_terms(['taxonomy' => 'ss_speaker', 'hide_empty' => false]);
    ?>
    <div class="wrap gcc-admin-wrap">
        <h1>Sermon Suite</h1>
        <div class="gcc-admin-stats">
            <div class="gcc-stat-card">
                <span class="gcc-stat-number"><?php echo (int)$series_count; ?></span>
                <span class="gcc-stat-label">Series</span>
            </div>
            <div class="gcc-stat-card">
                <span class="gcc-stat-number"><?php echo (int)$sermon_count; ?></span>
                <span class="gcc-stat-label">Sermons</span>
            </div>
            <div class="gcc-stat-card">
                <span class="gcc-stat-number"><?php echo (int)$topic_count; ?></span>
                <span class="gcc-stat-label">Topics</span>
            </div>
            <div class="gcc-stat-card">
                <span class="gcc-stat-number"><?php echo (int)$speaker_count; ?></span>
                <span class="gcc-stat-label">Speakers</span>
            </div>
        </div>

        <div class="gcc-admin-quick-links">
            <h2>Quick Links</h2>
            <a href="<?php echo admin_url('admin.php?page=ss-add-series'); ?>" class="button button-primary">+ New Series</a>
            <a href="<?php echo admin_url('admin.php?page=ss-add-sermon'); ?>" class="button button-primary">+ New Sermon</a>
            <a href="<?php echo admin_url('admin.php?page=sermon-suite-import'); ?>" class="button">Import from Series Engine</a>
            <a href="<?php echo rest_url('sermon-suite/v1/series'); ?>" class="button" target="_blank">View REST API →</a>
        </div>

        <div class="gcc-admin-shortcodes">
            <h2>Shortcodes</h2>
            <table class="widefat">
                <tr>
                    <td><code>[ss_sermon_archive]</code></td>
                    <td>Full sermon archive with series blocks and topic filter bar. Use on your /sermons page.</td>
                </tr>
                <tr>
                    <td><code>[ss_series_grid columns="3"]</code></td>
                    <td>Grid of series cards. Add <code>featured="true"</code> to show only featured series.</td>
                </tr>
                <tr>
                    <td><code>[ss_sermon_player id="123"]</code></td>
                    <td>Embed a single sermon's video + resources by post ID.</td>
                </tr>
            </table>
        </div>

        <div class="gcc-admin-bricks">
            <h2>your page builder Integration</h2>
            <p>Use these shortcodes inside a <strong>Shortcode</strong> element in your page builder. Alternatively, use the REST API endpoints below to build custom your page builder dynamic data queries.</p>
            <p><strong>REST API base:</strong> <code><?php echo esc_html(rest_url('sermon-suite/v1/')); ?></code></p>
        </div>
    </div>
    <?php
}

function sermon_suite_settings_page() {
    if ( isset($_POST['sermon_suite_settings_save']) && check_admin_referer('sermon_suite_settings') ) {
        update_option('sermon_suite_bible_version',   sanitize_text_field($_POST['bible_version']    ?? 'NIV'));
        update_option('sermon_suite_archive_slug',    sanitize_title($_POST['archive_slug']           ?? 'sermons'));
        update_option('sermon_suite_yt_api_key',      sanitize_text_field($_POST['yt_api_key']        ?? ''));
        update_option('sermon_suite_page_id',         absint($_POST['sermons_page_id'] ?? 0));
        $allowed_sizes = [ 'small', 'medium', 'large', 'xlarge' ];
        $size_in = sanitize_key($_POST['text_size'] ?? 'medium');
        update_option('sermon_suite_text_size', in_array($size_in, $allowed_sizes, true) ? $size_in : 'medium');
        update_option('sermon_suite_shots_api_key', sanitize_text_field($_POST['shots_api_key'] ?? ''));
        // Brand colors — sanitize as hex
        $color_fields = [
            'sermon_suite_color_accent',
            'sermon_suite_color_accent_light',
            'sermon_suite_color_text',
            'sermon_suite_color_text_muted',
            'sermon_suite_color_bg',
            'sermon_suite_color_bg_alt',
            'sermon_suite_color_button_text',
        ];
        foreach ( $color_fields as $field ) {
            $input = sanitize_text_field($_POST[$field] ?? '');
            // Accept only valid 3- or 6-digit hex colors
            if ( preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $input) ) {
                update_option($field, $input);
            }
        }
        echo '<div class="notice notice-success"><p>Settings saved. Brand colors are live on the front end.</p></div>';
        flush_rewrite_rules();
    }

    $bible_version   = get_option('sermon_suite_bible_version', 'NIV');
    $archive_slug    = get_option('sermon_suite_archive_slug',  'sermons');
    $yt_api_key      = get_option('sermon_suite_yt_api_key',    '');
    $sermons_page_id = (int) get_option('sermon_suite_page_id', 0);
    $text_size       = get_option('sermon_suite_text_size', 'medium');
    $shots_api_key   = get_option('sermon_suite_shots_api_key', '');
    $all_pages       = get_posts(['post_type'=>'page','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','post_status'=>'publish']);
    $versions = ['NIV','ESV','KJV','NLT','NASB','MSG','CSB'];

    // Color defaults (match CSS vars in sermons.css)
    $colors = sermon_suite_get_brand_colors();
    ?>
    <div class="wrap gcc-settings-wrap">
        <h1>Sermon Suite — Settings</h1>

        <form method="post">
            <?php wp_nonce_field('sermon_suite_settings'); ?>

            <!-- ── Brand Colors ───────────────────────────────────────────── -->
            <h2 class="gcc-settings-section-title">🎨 Brand Colors</h2>
            <p class="description" style="margin-bottom:16px;">
                These control every color in the sermon library — cards, buttons, tags, and text.
                Changes are applied instantly to the front end via inline CSS variables.
            </p>

            <div class="gcc-color-grid">

                <div class="gcc-color-field">
                    <label for="ss_color_accent">Accent / Primary</label>
                    <div class="gcc-color-input-wrap">
                        <input type="color" id="ss_color_accent_picker"
                               value="<?php echo esc_attr($colors['accent']); ?>"
                               data-target="sermon_suite_color_accent" />
                        <input type="text" id="sermon_suite_color_accent" name="sermon_suite_color_accent"
                               value="<?php echo esc_attr($colors['accent']); ?>"
                               class="gcc-hex-input" maxlength="7" placeholder="#2563eb" />
                    </div>
                    <span class="gcc-color-hint">Buttons, active tags, links, series heading border</span>
                </div>

                <div class="gcc-color-field">
                    <label for="ss_color_accent_light">Accent Light</label>
                    <div class="gcc-color-input-wrap">
                        <input type="color" id="ss_color_accent_light_picker"
                               value="<?php echo esc_attr($colors['accent_light']); ?>"
                               data-target="sermon_suite_color_accent_light" />
                        <input type="text" id="sermon_suite_color_accent_light" name="sermon_suite_color_accent_light"
                               value="<?php echo esc_attr($colors['accent_light']); ?>"
                               class="gcc-hex-input" maxlength="7" placeholder="#eff6ff" />
                    </div>
                    <span class="gcc-color-hint">Topic badge background, scripture highlight background</span>
                </div>

                <div class="gcc-color-field">
                    <label for="ss_color_button_text">Button Text Color</label>
                    <div class="gcc-color-input-wrap">
                        <input type="color" id="ss_color_button_text_picker"
                               value="<?php echo esc_attr($colors['button_text']); ?>"
                               data-target="sermon_suite_color_button_text" />
                        <input type="text" id="sermon_suite_color_button_text" name="sermon_suite_color_button_text"
                               value="<?php echo esc_attr($colors['button_text']); ?>"
                               class="gcc-hex-input" maxlength="7" placeholder="#ffffff" />
                    </div>
                    <span class="gcc-color-hint">Text on Watch buttons and filled tags (usually white)</span>
                </div>

                <div class="gcc-color-field">
                    <label for="ss_color_text">Body Text</label>
                    <div class="gcc-color-input-wrap">
                        <input type="color" id="ss_color_text_picker"
                               value="<?php echo esc_attr($colors['text']); ?>"
                               data-target="sermon_suite_color_text" />
                        <input type="text" id="sermon_suite_color_text" name="sermon_suite_color_text"
                               value="<?php echo esc_attr($colors['text']); ?>"
                               class="gcc-hex-input" maxlength="7" placeholder="#1a1a1a" />
                    </div>
                    <span class="gcc-color-hint">Primary text color for titles and descriptions</span>
                </div>

                <div class="gcc-color-field">
                    <label for="ss_color_text_muted">Muted Text</label>
                    <div class="gcc-color-input-wrap">
                        <input type="color" id="ss_color_text_muted_picker"
                               value="<?php echo esc_attr($colors['text_muted']); ?>"
                               data-target="sermon_suite_color_text_muted" />
                        <input type="text" id="sermon_suite_color_text_muted" name="sermon_suite_color_text_muted"
                               value="<?php echo esc_attr($colors['text_muted']); ?>"
                               class="gcc-hex-input" maxlength="7" placeholder="#666666" />
                    </div>
                    <span class="gcc-color-hint">Speaker names, dates, subtitles</span>
                </div>

                <div class="gcc-color-field">
                    <label for="ss_color_bg">Page Background</label>
                    <div class="gcc-color-input-wrap">
                        <input type="color" id="ss_color_bg_picker"
                               value="<?php echo esc_attr($colors['bg']); ?>"
                               data-target="sermon_suite_color_bg" />
                        <input type="text" id="sermon_suite_color_bg" name="sermon_suite_color_bg"
                               value="<?php echo esc_attr($colors['bg']); ?>"
                               class="gcc-hex-input" maxlength="7" placeholder="#ffffff" />
                    </div>
                    <span class="gcc-color-hint">Main background (usually leave as white)</span>
                </div>

                <div class="gcc-color-field">
                    <label for="ss_color_bg_alt">Card / Alt Background</label>
                    <div class="gcc-color-input-wrap">
                        <input type="color" id="ss_color_bg_alt_picker"
                               value="<?php echo esc_attr($colors['bg_alt']); ?>"
                               data-target="sermon_suite_color_bg_alt" />
                        <input type="text" id="sermon_suite_color_bg_alt" name="sermon_suite_color_bg_alt"
                               value="<?php echo esc_attr($colors['bg_alt']); ?>"
                               class="gcc-hex-input" maxlength="7" placeholder="#f5f5f5" />
                    </div>
                    <span class="gcc-color-hint">Resources section and alternate card backgrounds</span>
                </div>

            </div>

            <!-- Live Preview -->
            <div class="gcc-color-preview" id="gcc-color-preview">
                <h3 style="margin-top:0;">Live Preview</h3>
                <div class="gcc-preview-card">
                    <div class="gcc-preview-thumb">▶</div>
                    <div class="gcc-preview-body">
                        <div class="gcc-preview-title">Sample Sermon Title</div>
                        <div class="gcc-preview-byline">Mike Sigman · July 11, 2021</div>
                        <div class="gcc-preview-scripture">📖 Philippians 1:1-2</div>
                        <span class="gcc-preview-tag">Prayer</span>
                        <span class="gcc-preview-tag">Faith</span>
                        <a href="#" class="gcc-preview-btn" onclick="return false;">Watch</a>
                    </div>
                </div>
            </div>

            <hr style="margin: 32px 0;" />

            <!-- ── General Settings ───────────────────────────────────────── -->
            <h2 class="gcc-settings-section-title">⚙️ General Settings</h2>
            <table class="form-table">
                <tr>
                    <th>Sermons Page</th>
                    <td>
                        <select name="sermons_page_id">
                            <option value="0">— Select a page —</option>
                            <?php foreach ($all_pages as $p) : ?>
                            <option value="<?php echo $p->ID; ?>" <?php selected($sermons_page_id, $p->ID); ?>>
                                <?php echo esc_html($p->post_title); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            The page containing <code>[ss_sermon_archive]</code>.
                            Used for "← All Series" back links on series and sermon pages.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>YouTube Data API Key</th>
                    <td>
                        <input type="text" name="yt_api_key" value="<?php echo esc_attr($yt_api_key); ?>"
                               class="regular-text" placeholder="AIza…" autocomplete="off" />
                        <p class="description">
                            Required for YouTube Playlist Sync on series.
                            <a href="https://console.cloud.google.com/apis/library/youtube.googleapis.com" target="_blank" rel="noopener">
                                Get a free key from Google Cloud Console →
                            </a>
                            Enable the <strong>YouTube Data API v3</strong>, create credentials → API Key.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>Sermon Shots API Key</th>
                    <td>
                        <input type="password" name="shots_api_key" id="ss-shots-key"
                               value="<?php echo esc_attr($shots_api_key); ?>"
                               class="regular-text" autocomplete="off" />
                        <button type="button" class="button" id="ss-shots-test">Test Connection</button>
                        <span id="ss-shots-test-result" style="margin-left:8px;font-weight:600;"></span>
                        <p class="description">
                            Connects Sermon Suite to your <a href="https://sermonshots.com" target="_blank" rel="noopener">Sermon Shots</a> account
                            so you can import AI-generated summaries, discussion guides, and transcripts into sermons.
                            The key is stored server-side and never exposed to visitors.
                            <strong>Save the key first, then test.</strong>
                        </p>
                        <script>
                        (function(){
                            var btn = document.getElementById('ss-shots-test');
                            if (!btn) return;
                            btn.addEventListener('click', function(){
                                var out = document.getElementById('ss-shots-test-result');
                                out.style.color = '#666';
                                out.textContent = 'Testing…';
                                fetch('<?php echo esc_url_raw( rest_url('sermon-suite/v1/shots/test') ); ?>', {
                                    method: 'POST',
                                    headers: { 'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce('wp_rest') ); ?>' },
                                    credentials: 'same-origin'
                                }).then(function(r){ return r.json().then(function(j){ return {ok: r.ok, j: j}; }); })
                                .then(function(res){
                                    if (res.ok && res.j && res.j.ok) {
                                        out.style.color = '#1a7f37';
                                        out.textContent = '✅ Connected — ' + res.j.video_count + ' video(s) found';
                                    } else {
                                        out.style.color = '#b32d2e';
                                        out.textContent = '❌ ' + ((res.j && res.j.message) ? res.j.message : 'Connection failed');
                                    }
                                }).catch(function(){
                                    out.style.color = '#b32d2e';
                                    out.textContent = '❌ Request failed';
                                });
                            });
                        })();
                        </script>
                    </td>
                </tr>
                <tr>
                    <th>Default Bible Version</th>
                    <td>
                        <select name="bible_version">
                            <?php foreach ($versions as $v) : ?>
                                <option value="<?php echo $v; ?>" <?php selected($bible_version, $v); ?>><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Used when auto-generating Bible Gateway links from scripture references.</p>
                    </td>
                </tr>
                <tr>
                    <th>Body Text Size</th>
                    <td>
                        <select name="text_size">
                            <option value="small"  <?php selected($text_size, 'small');  ?>>Small</option>
                            <option value="medium" <?php selected($text_size, 'medium'); ?>>Medium (default)</option>
                            <option value="large"  <?php selected($text_size, 'large');  ?>>Large</option>
                            <option value="xlarge" <?php selected($text_size, 'xlarge'); ?>>Extra Large</option>
                        </select>
                        <p class="description">Controls the size of all sermon text on your public pages — titles, cards, descriptions, filters, and buttons. Font family is inherited from your theme.</p>
                    </td>
                </tr>
                <tr>
                    <th>Sermon Archive Slug</th>
                    <td>
                        <input type="text" name="archive_slug" value="<?php echo esc_attr($archive_slug); ?>" />
                        <p class="description">Default: <code>sermons</code>. Changing this requires re-saving Permalinks.</p>
                    </td>
                </tr>
            </table>

            <input type="hidden" name="sermon_suite_settings_save" value="1" />
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>

    <style>
    .gcc-settings-section-title { font-size: 1.2rem; font-weight: 700; margin: 24px 0 8px; border-bottom: 2px solid #eee; padding-bottom: 8px; }
    .gcc-color-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px; }
    .gcc-color-field { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 14px 16px; }
    .gcc-color-field label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; color: #1e1e1e; }
    .gcc-color-input-wrap { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
    .gcc-color-input-wrap input[type="color"] { width: 48px; height: 36px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; padding: 2px; }
    .gcc-hex-input { font-family: monospace; width: 100px; }
    .gcc-color-hint { font-size: 0.75rem; color: #666; display: block; }

    /* Live preview card */
    .gcc-color-preview { background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 20px 24px; margin-bottom: 24px; }
    .gcc-preview-card { display: flex; gap: 0; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 480px; }
    .gcc-preview-thumb { width: 130px; min-height: 80px; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #fff; flex-shrink: 0; }
    .gcc-preview-body { padding: 12px 16px; flex: 1; }
    .gcc-preview-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 4px; }
    .gcc-preview-byline { font-size: 0.78rem; margin-bottom: 6px; }
    .gcc-preview-scripture { font-size: 0.8rem; font-weight: 600; margin-bottom: 8px; }
    .gcc-preview-tag { display: inline-block; font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 10px; margin-right: 4px; }
    .gcc-preview-btn { display: inline-block; font-size: 0.78rem; font-weight: 700; padding: 5px 14px; border-radius: 16px; text-decoration: none; margin-top: 8px; }
    </style>

    <script>
    (function($){
        // Sync color picker → hex input and vice versa, then update preview
        $('[data-target]').on('input', function(){
            var target = $(this).data('target');
            $('#'+target).val(this.value);
            updatePreview();
        });
        $('.gcc-hex-input').on('input', function(){
            var id = this.id;
            var val = this.value;
            if (/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/.test(val)) {
                $('[data-target="'+id+'"]').val(val);
                updatePreview();
            }
        });

        function updatePreview(){
            var accent      = $('#sermon_suite_color_accent').val()       || '#2563eb';
            var accentLight = $('#sermon_suite_color_accent_light').val()  || '#eff6ff';
            var btnText     = $('#sermon_suite_color_button_text').val()   || '#ffffff';
            var text        = $('#sermon_suite_color_text').val()          || '#1a1a1a';
            var textMuted   = $('#sermon_suite_color_text_muted').val()    || '#666666';
            var bg          = $('#sermon_suite_color_bg').val()            || '#ffffff';
            var bgAlt       = $('#sermon_suite_color_bg_alt').val()        || '#f5f5f5';

            $('.gcc-preview-thumb').css({ background: accent, color: btnText });
            $('.gcc-preview-card').css({ background: bg });
            $('.gcc-preview-title').css({ color: text });
            $('.gcc-preview-byline').css({ color: textMuted });
            $('.gcc-preview-scripture').css({ color: accent });
            $('.gcc-preview-tag').css({ background: accentLight, color: accent });
            $('.gcc-preview-btn').css({ background: accent, color: btnText });
        }

        updatePreview();
    })(jQuery);
    </script>
    <?php
}

function sermon_suite_api_docs_page() {
    $base = rest_url('sermon-suite/v1');
    ?>
    <div class="wrap gcc-api-docs">
        <h1>Sermon Suite — REST API Documentation</h1>
        <p>Base URL: <code><?php echo esc_html($base); ?></code></p>

        <h2>Endpoints</h2>

        <table class="widefat gcc-api-table">
            <thead>
                <tr><th>Method</th><th>Endpoint</th><th>Description</th><th>Parameters</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="gcc-method get">GET</span></td>
                    <td><code>/series</code></td>
                    <td>All sermon series</td>
                    <td><code>per_page</code>, <code>page</code>, <code>topic</code>, <code>featured</code></td>
                </tr>
                <tr>
                    <td><span class="gcc-method get">GET</span></td>
                    <td><code>/series/{id}</code></td>
                    <td>Single series with all sermons</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td><span class="gcc-method get">GET</span></td>
                    <td><code>/sermons</code></td>
                    <td>All sermons</td>
                    <td><code>per_page</code>, <code>page</code>, <code>series_id</code>, <code>topic</code>, <code>speaker</code>, <code>search</code>, <code>year</code>, <code>order</code></td>
                </tr>
                <tr>
                    <td><span class="gcc-method get">GET</span></td>
                    <td><code>/sermons/{id}</code></td>
                    <td>Single sermon with resources, scripture, topics</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td><span class="gcc-method get">GET</span></td>
                    <td><code>/topics</code></td>
                    <td>All topics with sermon counts</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td><span class="gcc-method get">GET</span></td>
                    <td><code>/speakers</code></td>
                    <td>All speakers</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td><span class="gcc-method get">GET</span></td>
                    <td><code>/categories</code></td>
                    <td>All series categories with counts</td>
                    <td>—</td>
                </tr>
            </tbody>
        </table>

        <h2>Example Response — /sermons/{id}</h2>
        <pre class="gcc-code-block">{
  "id": 42,
  "title": "How's Your Attitude?",
  "slug": "hows-your-attitude",
  "description": "This weekend, we begin a summer long sermon series...",
  "date": "2021-07-11",
  "date_formatted": "July 11, 2021",
  "series_id": 2,
  "series_title": "Philippians",
  "series_order": 1,
  "youtube_id": "OnGsTGC2NhE",
  "youtube_embed": "https://www.youtube.com/embed/OnGsTGC2NhE",
  "youtube_url": "https://youtu.be/OnGsTGC2NhE",
  "thumbnail": "https://img.youtube.com/vi/OnGsTGC2NhE/hqdefault.jpg",
  "scripture_ref": "Philippians 1:1-2",
  "scripture_url": "https://www.biblegateway.com/passage/?search=Philippians+1%3A1-2&version=NIV",
  "topics": ["Attitude", "Joy"],
  "speakers": ["Mike Sigman"],
  "resources": [
    {
      "label": "Discussion Guide",
      "url": "https://comms.church/wp-content/.../guide.pdf",
      "type": "pdf"
    }
  ],
  "permalink": "https://comms.church/sermons/hows-your-attitude/"
}</pre>

        <h2>Quick Test Links</h2>
        <ul>
            <li><a href="<?php echo esc_url($base . '/series'); ?>" target="_blank"><?php echo esc_html($base . '/series'); ?></a></li>
            <li><a href="<?php echo esc_url($base . '/sermons'); ?>" target="_blank"><?php echo esc_html($base . '/sermons'); ?></a></li>
            <li><a href="<?php echo esc_url($base . '/topics'); ?>" target="_blank"><?php echo esc_html($base . '/topics'); ?></a></li>
            <li><a href="<?php echo esc_url($base . '/speakers'); ?>" target="_blank"><?php echo esc_html($base . '/speakers'); ?></a></li>
        </ul>
    </div>
    <?php
}
