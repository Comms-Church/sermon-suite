<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'add_meta_boxes', 'sermon_suite_add_meta_boxes' );
add_action( 'save_post',      'sermon_suite_save_meta', 10, 2 );

// ── Register meta for REST API ─────────────────────────────────────────────────
add_action( 'init', 'sermon_suite_register_meta' );
function sermon_suite_register_meta() {

    $sermon_fields = [
        '_ss_youtube_id'       => [ 'type' => 'string',  'description' => 'YouTube video ID or full URL' ],
        '_ss_series_id'        => [ 'type' => 'integer', 'description' => 'Related ss_series post ID' ],
        '_ss_sermon_date'      => [ 'type' => 'string',  'description' => 'Sermon date (YYYY-MM-DD)' ],
        '_ss_scripture_ref'    => [ 'type' => 'string',  'description' => 'Primary scripture reference text' ],
        '_ss_scripture_url'    => [ 'type' => 'string',  'description' => 'Bible Gateway / YouVersion URL' ],
        '_ss_series_order'     => [ 'type' => 'integer', 'description' => 'Message order within series' ],
        '_ss_sermon_notes'     => [ 'type' => 'string',  'description' => 'Sermon outline / notes (HTML)' ],
        '_ss_yt_synced'        => [ 'type' => 'string',  'description' => 'YouTube video ID this post was synced from' ],
    ];

    foreach ( $sermon_fields as $key => $args ) {
        register_post_meta( 'ss_sermon', $key, [
            'single'        => true,
            'type'          => $args['type'],
            'description'   => $args['description'],
            'show_in_rest'  => true,
            'auth_callback' => '__return_true',
        ]);
    }

    // Series meta
    $series_fields = [
        '_ss_series_start_date'   => [ 'type' => 'string',  'description' => 'Series start date' ],
        '_ss_series_end_date'     => [ 'type' => 'string',  'description' => 'Series end date' ],
        '_ss_series_image_sm'     => [ 'type' => 'string',  'description' => '600x338 image URL' ],
        '_ss_series_image_lg'     => [ 'type' => 'string',  'description' => '1000x563 image URL' ],
        '_ss_series_featured'     => [ 'type' => 'boolean', 'description' => 'Feature on homepage' ],
        '_ss_series_yt_playlist'  => [ 'type' => 'string',  'description' => 'YouTube playlist URL or ID' ],
        '_ss_series_yt_last_sync' => [ 'type' => 'string',  'description' => 'Timestamp of last YouTube sync' ],
    ];

    foreach ( $series_fields as $key => $args ) {
        register_post_meta( 'ss_series', $key, [
            'single'        => true,
            'type'          => $args['type'],
            'description'   => $args['description'],
            'show_in_rest'  => true,
            'auth_callback' => '__return_true',
        ]);
    }
}

// ── Meta Boxes ─────────────────────────────────────────────────────────────────
function sermon_suite_add_meta_boxes() {
    add_meta_box(
        'ss_sermon_details',
        'Sermon Details',
        'sermon_suite_render_sermon_meta_box',
        'ss_sermon',
        'normal',
        'high'
    );

    add_meta_box(
        'ss_sermon_notes',
        'Sermon Notes / Outline',
        'sermon_suite_render_notes_meta_box',
        'ss_sermon',
        'normal',
        'default'
    );

    add_meta_box(
        'ss_sermon_resources',
        'Downloadable Resources & Links',
        'sermon_suite_render_resources_meta_box',
        'ss_sermon',
        'normal',
        'default'
    );

    add_meta_box(
        'ss_series_details',
        'Series Details',
        'sermon_suite_render_series_meta_box',
        'ss_series',
        'normal',
        'high'
    );

    add_meta_box(
        'ss_series_image_upload',
        'Series Images',
        'sermon_suite_render_series_image_box',
        'ss_series',
        'side',
        'low'
    );

    add_meta_box(
        'ss_series_yt_sync',
        '🔄 YouTube Playlist Sync',
        'sermon_suite_render_yt_sync_box',
        'ss_series',
        'normal',
        'default'
    );
}

// ── Sermon Details Box ─────────────────────────────────────────────────────────
function sermon_suite_render_sermon_meta_box( $post ) {
    wp_nonce_field( 'ss_sermon_save', 'ss_sermon_nonce' );

    $youtube_id    = get_post_meta( $post->ID, '_ss_youtube_id',    true );
    $series_id     = get_post_meta( $post->ID, '_ss_series_id',     true );
    $sermon_date   = get_post_meta( $post->ID, '_ss_sermon_date',   true );
    $scripture_ref = get_post_meta( $post->ID, '_ss_scripture_ref', true );
    $scripture_url = get_post_meta( $post->ID, '_ss_scripture_url', true );
    $series_order  = get_post_meta( $post->ID, '_ss_series_order',  true );
    $yt_synced     = get_post_meta( $post->ID, '_ss_yt_synced',     true );

    $series_list = get_posts([
        'post_type'      => 'ss_series',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'any',
    ]);
    ?>
    <?php if ( $yt_synced ) : ?>
    <div style="background:#e8f4fd;border-left:3px solid #2196f3;padding:8px 12px;margin-bottom:12px;border-radius:0 4px 4px 0;font-size:0.85rem;">
        🔗 Synced from YouTube video <code><?php echo esc_html($yt_synced); ?></code>.
        Edit freely — re-syncing will not overwrite fields you've filled in.
    </div>
    <?php endif; ?>
    <table class="form-table gcc-meta-table">
        <tr>
            <th><label for="ss_youtube_id">YouTube ID or URL</label></th>
            <td>
                <input type="text" id="ss_youtube_id" name="ss_youtube_id"
                       value="<?php echo esc_attr( $youtube_id ); ?>"
                       placeholder="e.g. dQw4w9WgXcQ or https://youtu.be/dQw4w9WgXcQ"
                       class="large-text" />
                <p class="description">Paste the full YouTube URL or just the video ID.</p>
            </td>
        </tr>
        <tr>
            <th><label for="ss_sermon_date">Sermon Date</label></th>
            <td>
                <input type="date" id="ss_sermon_date" name="ss_sermon_date"
                       value="<?php echo esc_attr( $sermon_date ); ?>" />
            </td>
        </tr>
        <tr>
            <th><label for="ss_series_id">Series</label></th>
            <td>
                <select id="ss_series_id" name="ss_series_id">
                    <option value="">— No Series —</option>
                    <?php foreach ( $series_list as $s ) : ?>
                        <option value="<?php echo $s->ID; ?>" <?php selected( $series_id, $s->ID ); ?>>
                            <?php echo esc_html( $s->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="ss_series_order">Message # in Series</label></th>
            <td>
                <input type="number" id="ss_series_order" name="ss_series_order"
                       value="<?php echo esc_attr( $series_order ); ?>"
                       min="1" style="width:80px;" />
                <p class="description">Order within the series (1 = first message).</p>
            </td>
        </tr>
        <tr>
            <th><label for="ss_scripture_ref">Scripture Reference</label></th>
            <td>
                <input type="text" id="ss_scripture_ref" name="ss_scripture_ref"
                       value="<?php echo esc_attr( $scripture_ref ); ?>"
                       placeholder="e.g. John 3:16-17"
                       class="regular-text" />
            </td>
        </tr>
        <tr>
            <th><label for="ss_scripture_url">Scripture Link</label></th>
            <td>
                <input type="url" id="ss_scripture_url" name="ss_scripture_url"
                       value="<?php echo esc_attr( $scripture_url ); ?>"
                       placeholder="https://www.biblegateway.com/passage/..."
                       class="large-text" />
                <p class="description">
                    Bible Gateway or YouVersion link. &nbsp;
                    <a href="#" class="gcc-generate-bg-url button button-small">Auto-generate from reference ↑</a>
                </p>
            </td>
        </tr>
    </table>
    <script>
    jQuery(function($){
        $('#ss_scripture_ref').on('blur', function(){
            var ref = $(this).val().trim();
            var urlField = $('#ss_scripture_url');
            if (ref && !urlField.val()) {
                urlField.val('https://www.biblegateway.com/passage/?search=' + encodeURIComponent(ref) + '&version=NIV');
            }
        });
        $('.gcc-generate-bg-url').on('click', function(e){
            e.preventDefault();
            var ref = $('#ss_scripture_ref').val().trim();
            if (ref) {
                $('#ss_scripture_url').val('https://www.biblegateway.com/passage/?search=' + encodeURIComponent(ref) + '&version=NIV');
            }
        });
    });
    </script>
    <?php
}

// ── Sermon Notes Box ───────────────────────────────────────────────────────────
function sermon_suite_render_notes_meta_box( $post ) {
    $notes = get_post_meta( $post->ID, '_ss_sermon_notes', true );
    ?>
    <p class="description" style="margin-bottom:10px;">
        Optional sermon outline or fill-in-the-blank notes. Shown on the sermon page below the video.
        Leave blank if unused — the section won't appear on the front end.
    </p>
    <?php
    wp_editor( $notes, 'ss_sermon_notes_editor', [
        'textarea_name' => 'ss_sermon_notes',
        'media_buttons' => false,
        'teeny'         => true,
        'textarea_rows' => 8,
        'tinymce'       => [
            'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink,undo,redo',
        ],
    ]);
}

// ── Resources Box ──────────────────────────────────────────────────────────────
function sermon_suite_render_resources_meta_box( $post ) {
    $resources = get_post_meta( $post->ID, '_ss_resources', true );
    if ( ! is_array( $resources ) ) $resources = [];
    ?>
    <div id="gcc-resources-wrap">
        <p class="description" style="margin-bottom:12px;">Add downloadable PDFs, discussion guides, devotionals, and external links.</p>
        <div id="gcc-resources-list">
            <?php foreach ( $resources as $i => $r ) : ?>
            <div class="gcc-resource-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
                <input type="text" name="ss_resources[<?php echo $i; ?>][label]"
                       value="<?php echo esc_attr( $r['label'] ?? '' ); ?>"
                       placeholder="Label (e.g. Discussion Guide)"
                       style="flex:1;" />
                <input type="url" name="ss_resources[<?php echo $i; ?>][url]"
                       value="<?php echo esc_attr( $r['url'] ?? '' ); ?>"
                       placeholder="URL"
                       style="flex:2;" />
                <select name="ss_resources[<?php echo $i; ?>][type]" style="width:130px;">
                    <option value="pdf"        <?php selected( $r['type'] ?? '', 'pdf' ); ?>>PDF</option>
                    <option value="link"       <?php selected( $r['type'] ?? '', 'link' ); ?>>Link</option>
                    <option value="devotional" <?php selected( $r['type'] ?? '', 'devotional' ); ?>>Devotional</option>
                    <option value="notes"      <?php selected( $r['type'] ?? '', 'notes' ); ?>>Notes</option>
                </select>
                <button type="button" class="button gcc-remove-resource">✕</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button button-secondary" id="gcc-add-resource">+ Add Resource</button>
    </div>
    <script>
    jQuery(function($){
        var idx = <?php echo max(count($resources), 1); ?>;
        $('#gcc-add-resource').on('click', function(){
            var row = '<div class="gcc-resource-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">'
                + '<input type="text" name="ss_resources['+idx+'][label]" placeholder="Label (e.g. Discussion Guide)" style="flex:1;" />'
                + '<input type="url"  name="ss_resources['+idx+'][url]"   placeholder="URL" style="flex:2;" />'
                + '<select name="ss_resources['+idx+'][type]" style="width:130px;">'
                + '<option value="pdf">PDF</option><option value="link">Link</option>'
                + '<option value="devotional">Devotional</option><option value="notes">Notes</option>'
                + '</select>'
                + '<button type="button" class="button gcc-remove-resource">✕</button>'
                + '</div>';
            $('#gcc-resources-list').append(row);
            idx++;
        });
        $(document).on('click', '.gcc-remove-resource', function(){
            $(this).closest('.gcc-resource-row').remove();
        });
    });
    </script>
    <?php
}

// ── Series Details Box ─────────────────────────────────────────────────────────
function sermon_suite_render_series_meta_box( $post ) {
    wp_nonce_field( 'ss_series_save', 'ss_series_nonce' );

    $start    = get_post_meta( $post->ID, '_ss_series_start_date', true );
    $end      = get_post_meta( $post->ID, '_ss_series_end_date',   true );
    $featured = get_post_meta( $post->ID, '_ss_series_featured',   true );
    ?>
    <table class="form-table gcc-meta-table">
        <tr>
            <th><label for="ss_series_start">Start Date</label></th>
            <td><input type="date" id="ss_series_start" name="ss_series_start_date"
                       value="<?php echo esc_attr($start); ?>" /></td>
        </tr>
        <tr>
            <th><label for="ss_series_end">End Date</label></th>
            <td><input type="date" id="ss_series_end" name="ss_series_end_date"
                       value="<?php echo esc_attr($end); ?>" />
                <p class="description">Leave blank if series is ongoing.</p>
            </td>
        </tr>
        <tr>
            <th>Featured</th>
            <td>
                <label>
                    <input type="checkbox" name="ss_series_featured" value="1"
                           <?php checked( $featured, '1' ); ?> />
                    Show as featured series on sermon archives
                </label>
            </td>
        </tr>
    </table>
    <?php
}

// ── Series Image Upload Box (sidebar) ─────────────────────────────────────────
function sermon_suite_render_series_image_box( $post ) {
    $img_sm = get_post_meta( $post->ID, '_ss_series_image_sm', true );
    $img_lg = get_post_meta( $post->ID, '_ss_series_image_lg', true );
    ?>
    <style>
    .gcc-img-upload-wrap { margin-bottom: 14px; }
    .gcc-img-upload-wrap label { display:block; font-weight:600; font-size:0.82rem; margin-bottom:4px; }
    .gcc-img-preview { width:100%; border-radius:4px; margin-bottom:6px; display:<?php echo $img_lg ? 'block' : 'none'; ?>; }
    .gcc-img-preview-sm { width:100%; border-radius:4px; margin-bottom:6px; display:<?php echo $img_sm ? 'block' : 'none'; ?>; }
    </style>

    <div class="gcc-img-upload-wrap">
        <label>Thumbnail (600×338) — used in cards</label>
        <img src="<?php echo esc_url($img_sm); ?>" class="gcc-img-preview-sm" id="gcc-img-preview-sm" />
        <input type="hidden" name="ss_series_image_sm" id="ss_series_image_sm"
               value="<?php echo esc_attr($img_sm); ?>" />
        <button type="button" class="button" id="gcc-upload-sm">
            <?php echo $img_sm ? 'Change Image' : 'Upload / Select Image'; ?>
        </button>
        <?php if ($img_sm) : ?>
            <button type="button" class="button gcc-remove-img" data-target="ss_series_image_sm" data-preview="gcc-img-preview-sm">Remove</button>
        <?php endif; ?>
    </div>

    <div class="gcc-img-upload-wrap">
        <label>Hero / Large (1000×563) — used in series header</label>
        <img src="<?php echo esc_url($img_lg); ?>" class="gcc-img-preview" id="gcc-img-preview-lg" />
        <input type="hidden" name="ss_series_image_lg" id="ss_series_image_lg"
               value="<?php echo esc_attr($img_lg); ?>" />
        <button type="button" class="button" id="gcc-upload-lg">
            <?php echo $img_lg ? 'Change Image' : 'Upload / Select Image'; ?>
        </button>
        <?php if ($img_lg) : ?>
            <button type="button" class="button gcc-remove-img" data-target="ss_series_image_lg" data-preview="gcc-img-preview-lg">Remove</button>
        <?php endif; ?>
    </div>

    <p class="description">These override the Featured Image in series cards and headers.</p>

    <script>
    jQuery(function($){
        function openMediaPicker(hiddenFieldId, previewId, buttonEl) {
            var frame = wp.media({
                title: 'Select Series Image',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function(){
                var attachment = frame.state().get('selection').first().toJSON();
                var url = attachment.sizes && attachment.sizes.large
                    ? attachment.sizes.large.url
                    : attachment.url;
                $('#' + hiddenFieldId).val(url);
                $('#' + previewId).attr('src', url).show();
                $(buttonEl).text('Change Image');
            });
            frame.open();
        }

        $('#gcc-upload-sm').on('click', function(){
            openMediaPicker('ss_series_image_sm', 'gcc-img-preview-sm', this);
        });
        $('#gcc-upload-lg').on('click', function(){
            openMediaPicker('ss_series_image_lg', 'gcc-img-preview-lg', this);
        });
        $(document).on('click', '.gcc-remove-img', function(){
            var target  = $(this).data('target');
            var preview = $(this).data('preview');
            $('#' + target).val('');
            $('#' + preview).hide().attr('src', '');
            $(this).remove();
        });
    });
    </script>
    <?php
}

// ── YouTube Playlist Sync Box ──────────────────────────────────────────────────
function sermon_suite_render_yt_sync_box( $post ) {
    $playlist   = get_post_meta( $post->ID, '_ss_series_yt_playlist',  true );
    $last_sync  = get_post_meta( $post->ID, '_ss_series_yt_last_sync', true );
    $api_key    = get_option('sermon_suite_yt_api_key', '');
    ?>
    <p class="description" style="margin-bottom:12px;">
        Paste a YouTube playlist URL and click <strong>Sync Now</strong>.
        The plugin will create a draft sermon for each video it finds.
        Videos already synced will not be duplicated — only new videos get added.
        Anything you've already edited (scripture, resources, notes) is preserved.
    </p>
    <?php if ( ! $api_key ) : ?>
    <div class="notice notice-warning inline" style="margin:0 0 12px;">
        <p>⚠️ No YouTube API key set.
           <a href="<?php echo admin_url('admin.php?page=sermon-suite-settings'); ?>">Add one in Settings</a>
           to enable playlist sync.</p>
    </div>
    <?php endif; ?>

    <table class="form-table gcc-meta-table" style="margin-bottom:0;">
        <tr>
            <th><label for="ss_series_yt_playlist">Playlist URL or ID</label></th>
            <td>
                <input type="text" id="ss_series_yt_playlist" name="ss_series_yt_playlist"
                       value="<?php echo esc_attr($playlist); ?>"
                       class="large-text"
                       placeholder="https://www.youtube.com/playlist?list=PLxxxxxxxx" />
                <p class="description">Full playlist URL, or just the playlist ID (starts with PL…)</p>
            </td>
        </tr>
    </table>

    <?php if ( $last_sync ) : ?>
    <p style="color:#666;font-size:0.85rem;margin:8px 0 0 210px;">
        Last synced: <?php echo esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync) ) ); ?>
    </p>
    <?php endif; ?>

    <div style="margin-top:16px; margin-left:210px;">
        <button type="button" id="gcc-yt-sync-btn" class="button button-primary"
                data-series-id="<?php echo $post->ID; ?>"
                <?php echo !$api_key ? 'disabled' : ''; ?>>
            🔄 Sync Playlist Now
        </button>
        <span id="gcc-sync-spinner" class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
        <span id="gcc-sync-status" style="margin-left:10px;font-style:italic;"></span>
    </div>

    <div id="gcc-sync-results" style="display:none;margin-top:16px;background:#f6f6f6;border:1px solid #ddd;border-radius:4px;padding:12px 16px;max-height:260px;overflow-y:auto;">
        <strong>Sync Log:</strong>
        <ul id="gcc-sync-log" style="margin:8px 0 0;padding-left:18px;font-size:0.85rem;font-family:monospace;"></ul>
    </div>

    <script>
    jQuery(function($){
        $('#gcc-yt-sync-btn').on('click', function(){
            var seriesId  = $(this).data('series-id');
            var playlist  = $('#ss_series_yt_playlist').val().trim();
            if (!playlist) { alert('Please enter a playlist URL or ID first.'); return; }

            var btn = $(this);
            btn.prop('disabled', true);
            $('#gcc-sync-spinner').show();
            $('#gcc-sync-status').text('Fetching playlist…');
            $('#gcc-sync-results').hide();
            $('#gcc-sync-log').empty();

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action:    'ss_yt_sync_playlist',
                    nonce:     '<?php echo wp_create_nonce("ss_yt_sync"); ?>',
                    series_id: seriesId,
                    playlist:  playlist,
                },
                success: function(res){
                    btn.prop('disabled', false);
                    $('#gcc-sync-spinner').hide();
                    if (res.success) {
                        $('#gcc-sync-status').text('Done! ' + res.data.summary);
                        var log = res.data.log;
                        log.forEach(function(line){
                            $('#gcc-sync-log').append('<li>' + line + '</li>');
                        });
                        $('#gcc-sync-results').show();
                        // Update last-synced text inline
                        $('p.gcc-last-synced').remove();
                    } else {
                        $('#gcc-sync-status').text('Error: ' + res.data);
                    }
                },
                error: function(){
                    btn.prop('disabled', false);
                    $('#gcc-sync-spinner').hide();
                    $('#gcc-sync-status').text('Server error — check console.');
                }
            });
        });
    });
    </script>
    <?php
}

// ── Save Meta ──────────────────────────────────────────────────────────────────
function sermon_suite_save_meta( $post_id, $post ) {
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( $post->post_type === 'ss_sermon' ) {
        if ( ! isset($_POST['ss_sermon_nonce']) || ! wp_verify_nonce($_POST['ss_sermon_nonce'], 'ss_sermon_save') ) return;

        $fields = [
            'ss_youtube_id'    => '_ss_youtube_id',
            'ss_sermon_date'   => '_ss_sermon_date',
            'ss_series_id'     => '_ss_series_id',
            'ss_series_order'  => '_ss_series_order',
            'ss_scripture_ref' => '_ss_scripture_ref',
            'ss_scripture_url' => '_ss_scripture_url',
        ];
        foreach ( $fields as $input => $meta_key ) {
            if ( isset($_POST[$input]) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field($_POST[$input]) );
            }
        }

        // Sermon notes (allow basic HTML)
        if ( isset($_POST['ss_sermon_notes']) ) {
            update_post_meta( $post_id, '_ss_sermon_notes', wp_kses_post($_POST['ss_sermon_notes']) );
        }

        // Resources
        $resources = [];
        if ( isset($_POST['ss_resources']) && is_array($_POST['ss_resources']) ) {
            foreach ( $_POST['ss_resources'] as $r ) {
                if ( ! empty($r['url']) ) {
                    $resources[] = [
                        'label' => sanitize_text_field($r['label'] ?? ''),
                        'url'   => esc_url_raw($r['url']),
                        'type'  => sanitize_key($r['type'] ?? 'link'),
                    ];
                }
            }
        }
        update_post_meta( $post_id, '_ss_resources', $resources );
    }

    if ( $post->post_type === 'ss_series' ) {
        if ( ! isset($_POST['ss_series_nonce']) || ! wp_verify_nonce($_POST['ss_series_nonce'], 'ss_series_save') ) return;

        $sf = [
            'ss_series_start_date'  => '_ss_series_start_date',
            'ss_series_end_date'    => '_ss_series_end_date',
            'ss_series_image_sm'    => '_ss_series_image_sm',
            'ss_series_image_lg'    => '_ss_series_image_lg',
            'ss_series_yt_playlist' => '_ss_series_yt_playlist',
        ];
        foreach ( $sf as $input => $meta_key ) {
            if ( isset($_POST[$input]) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field($_POST[$input]) );
            }
        }
        update_post_meta( $post_id, '_ss_series_featured', isset($_POST['ss_series_featured']) ? '1' : '0' );
    }
}

// ── Enqueue WP media uploader on Series edit screen ───────────────────────────
add_action( 'admin_enqueue_scripts', 'sermon_suite_enqueue_media_uploader' );
function sermon_suite_enqueue_media_uploader( $hook ) {
    $screen = get_current_screen();
    if ( in_array($hook, ['post.php','post-new.php']) && $screen && $screen->post_type === 'ss_series' ) {
        wp_enqueue_media();
    }
}
