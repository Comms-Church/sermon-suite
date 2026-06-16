<?php
if ( ! defined( 'ABSPATH' ) ) exit;


// ── Critical editor CSS, inlined so caching/minify plugins can never break the layout ──
function ss_editor_critical_css() {
    ?>
    <style id="ss-editor-critical">
    .gcc-custom-editor{max-width:1160px;clear:both;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
    .gcc-editor-body{overflow:hidden;width:100%}
    .gcc-editor-main{float:left;width:calc(100% - 324px);box-sizing:border-box}
    .gcc-editor-sidebar{float:right;width:300px;box-sizing:border-box;display:flex;flex-direction:column;gap:16px}
    @media screen and (max-width:1100px){
        .gcc-editor-main,.gcc-editor-sidebar{float:none;width:100%}
        .gcc-editor-sidebar{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}
    }
    @media screen and (max-width:700px){.gcc-editor-sidebar{grid-template-columns:1fr}}
    .gcc-editor-header{display:flex;align-items:center;justify-content:space-between;padding:14px 0 16px;border-bottom:2px solid #e8e8e8;margin-bottom:24px;flex-wrap:wrap;gap:12px}
    .gcc-editor-header-left{display:flex;align-items:center;gap:12px}
    .gcc-editor-header-left h1{margin:0;font-size:1.35rem;font-weight:700;line-height:1}
    .gcc-editor-header-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .gcc-card{background:#fff;border:1px solid #e2e2e2;border-radius:8px;margin-bottom:16px;overflow:hidden}
    .gcc-card-header{display:flex;align-items:center;gap:8px;padding:10px 16px;background:#f8f9fa;border-bottom:1px solid #e8e8e8;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#555}
    .gcc-card-body{padding:16px 18px}
    .gcc-sidebar-card{background:#fff;border:1px solid #e2e2e2;border-radius:8px;overflow:hidden}
    .gcc-sidebar-card-header{display:flex;align-items:center;gap:7px;padding:9px 14px;background:#f8f9fa;border-bottom:1px solid #e8e8e8;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#555}
    .gcc-sidebar-card-body{padding:13px 15px}
    .gcc-input,.gcc-select,.gcc-textarea{display:block;width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:5px;padding:7px 10px;font-size:.875rem;background:#fff;font-family:inherit;line-height:1.4}
    .gcc-label{display:block;font-weight:600;font-size:.75rem;color:#444;margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em}
    .gcc-field{margin-bottom:13px}
    .gcc-field:last-child{margin-bottom:0}
    .gcc-picker-row{display:flex;gap:7px}
    .gcc-picker-col{flex:1;min-width:0}
    .gcc-resource-row{display:flex;gap:6px;align-items:center;margin-bottom:7px}
    .gcc-resource-row .gcc-input{flex:1;min-width:0}
    .gcc-resource-row .gcc-select{width:110px;flex-shrink:0}
    </style>
    <?php
}

// Editor pages are registered in admin/admin-pages.php (sermon_suite_admin_menu).

// ── Redirect native WP editor to custom pages ─────────────────────────────────
add_action( 'admin_init', 'ss_redirect_native_editors' );
function ss_redirect_native_editors() {
    $pagenow = $GLOBALS['pagenow'] ?? '';
    $screen  = $_GET['post_type'] ?? '';
    $action  = $_GET['action']    ?? '';
    $post_id = absint($_GET['post'] ?? 0);

    if ( $pagenow === 'post-new.php' && $screen === 'ss_sermon' ) {
        wp_redirect( admin_url('admin.php?page=ss-add-sermon') ); exit;
    }
    if ( $pagenow === 'post-new.php' && $screen === 'ss_series' ) {
        wp_redirect( admin_url('admin.php?page=ss-add-series') ); exit;
    }
    if ( $pagenow === 'post.php' && $action === 'edit' && $post_id ) {
        $pt = get_post_type($post_id);
        if ( $pt === 'ss_sermon' ) { wp_redirect( admin_url('admin.php?page=ss-edit-sermon&post_id='.$post_id) ); exit; }
        if ( $pt === 'ss_series' ) { wp_redirect( admin_url('admin.php?page=ss-edit-series&post_id='.$post_id) ); exit; }
    }
}

// ── Remap Edit links in list tables ───────────────────────────────────────────
add_filter( 'get_edit_post_link', 'ss_filter_edit_post_link', 10, 2 );
function ss_filter_edit_post_link( $link, $post_id ) {
    $pt = get_post_type($post_id);
    if ( $pt === 'ss_sermon' ) return admin_url('admin.php?page=ss-edit-sermon&post_id='.$post_id);
    if ( $pt === 'ss_series' ) return admin_url('admin.php?page=ss-edit-series&post_id='.$post_id);
    return $link;
}

// ── AJAX: save sermon ─────────────────────────────────────────────────────────
add_action( 'wp_ajax_ss_save_sermon', 'ss_ajax_save_sermon' );
function ss_ajax_save_sermon() {
    check_ajax_referer('ss_custom_editor','nonce');
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('Unauthorized');

    $post_id = absint($_POST['post_id'] ?? 0);
    $title   = sanitize_text_field($_POST['title']         ?? '');
    if (!$title) wp_send_json_error('Title is required.');

    $post_data = [
        'post_type'    => 'ss_sermon',
        'post_title'   => $title,
        'post_content' => wp_kses_post($_POST['content']   ?? ''),
        'post_status'  => sanitize_key($_POST['status']    ?? 'publish'),
        'post_date'    => ($_POST['sermon_date'] ?? '') ? sanitize_text_field($_POST['sermon_date']).' 00:00:00' : current_time('mysql'),
    ];
    $result = $post_id ? wp_update_post(array_merge($post_data,['ID'=>$post_id]),true) : wp_insert_post($post_data,true);
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    $post_id = $result;

    $metas = [
        '_ss_youtube_id'    => sanitize_text_field($_POST['youtube_id']    ?? ''),
        '_ss_sermon_date'   => sanitize_text_field($_POST['sermon_date']   ?? ''),
        '_ss_series_id'     => absint($_POST['series_id']                  ?? 0),
        '_ss_series_order'  => absint($_POST['series_order']               ?? 0),
        '_ss_scripture_ref' => sanitize_text_field($_POST['scripture_ref'] ?? ''),
        '_ss_scripture_url' => esc_url_raw($_POST['scripture_url']         ?? ''),
        '_ss_sermon_notes'  => wp_kses_post($_POST['sermon_notes']         ?? ''),
    ];
    foreach ($metas as $k => $v) update_post_meta($post_id, $k, $v);

    // Resources
    $resources = [];
    foreach (($_POST['res_label']??[]) as $i => $label) {
        $url = esc_url_raw($_POST['res_url'][$i] ?? '');
        if ($url) $resources[] = ['label'=>sanitize_text_field($label),'url'=>$url,'type'=>sanitize_key($_POST['res_type'][$i]??'link')];
    }
    update_post_meta($post_id,'_ss_resources',$resources);

    // Taxonomies — topics and speakers
    foreach (['ss_topics'=>'ss_topic','ss_speakers'=>'ss_speaker'] as $field => $tax) {
        $names = array_filter(array_map('trim', explode(',', sanitize_text_field($_POST[$field]??''))));
        $ids   = [];
        foreach ($names as $n) {
            $t = get_term_by('name',$n,$tax);
            $ids[] = $t ? $t->term_id : (($ins=wp_insert_term($n,$tax)) && !is_wp_error($ins) ? $ins['term_id'] : null);
        }
        wp_set_post_terms($post_id, array_filter($ids), $tax);
    }

    // Campus
    $campus_id = absint($_POST['sermon_campus'] ?? 0);
    if ($campus_id) {
        wp_set_post_terms($post_id, [$campus_id], 'ss_campus');
    } else {
        wp_set_post_terms($post_id, [], 'ss_campus');
    }

    wp_send_json_success(['post_id'=>$post_id,'edit_url'=>admin_url('admin.php?page=ss-edit-sermon&post_id='.$post_id),'message'=>'Sermon saved.']);
}

// ── AJAX: save series ─────────────────────────────────────────────────────────
add_action( 'wp_ajax_ss_save_series', 'ss_ajax_save_series' );
function ss_ajax_save_series() {
    check_ajax_referer('ss_custom_editor','nonce');
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('Unauthorized');

    $post_id = absint($_POST['post_id'] ?? 0);
    $title   = sanitize_text_field($_POST['title'] ?? '');
    if (!$title) wp_send_json_error('Title is required.');

    $post_data = ['post_type'=>'ss_series','post_title'=>$title,'post_content'=>wp_kses_post($_POST['content']??''),'post_status'=>sanitize_key($_POST['status']??'publish')];
    $result = $post_id ? wp_update_post(array_merge($post_data,['ID'=>$post_id]),true) : wp_insert_post($post_data,true);
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    $post_id = $result;

    update_post_meta($post_id,'_ss_series_start_date',  sanitize_text_field($_POST['start_date'] ??''));
    update_post_meta($post_id,'_ss_series_end_date',    sanitize_text_field($_POST['end_date']   ??''));
    update_post_meta($post_id,'_ss_series_image_sm',    esc_url_raw($_POST['image_sm']           ??''));
    update_post_meta($post_id,'_ss_series_image_lg',    esc_url_raw($_POST['image_lg']           ??''));
    update_post_meta($post_id,'_ss_series_featured',    !empty($_POST['featured']) ? '1' : '0');
    update_post_meta($post_id,'_ss_series_yt_playlist', sanitize_text_field($_POST['yt_playlist']??''));

    // Category
    $cat_id = absint($_POST['series_category'] ?? 0);
    if ($cat_id) {
        wp_set_post_terms($post_id, [$cat_id], 'ss_series_category');
    } else {
        wp_set_post_terms($post_id, [], 'ss_series_category');
    }

    // Campus
    $campus_id = absint($_POST['series_campus'] ?? 0);
    if ($campus_id) {
        wp_set_post_terms($post_id, [$campus_id], 'ss_campus');
    } else {
        wp_set_post_terms($post_id, [], 'ss_campus');
    }

    wp_send_json_success(['post_id'=>$post_id,'edit_url'=>admin_url('admin.php?page=ss-edit-series&post_id='.$post_id),'message'=>'Series saved.']);
}

// ── Sermon editor page ────────────────────────────────────────────────────────
function ss_render_sermon_editor() {
    wp_enqueue_media();
    ss_editor_critical_css();

    $post_id   = absint($_GET['post_id'] ?? 0);
    $post      = $post_id ? get_post($post_id) : null;
    $is_edit   = $post && $post->post_type === 'ss_sermon';

    $title      = $is_edit ? $post->post_title   : '';
    $content    = $is_edit ? $post->post_content : '';
    $status     = $is_edit ? $post->post_status  : 'publish';
    $youtube    = $is_edit ? get_post_meta($post_id,'_ss_youtube_id',   true) : '';
    $date       = $is_edit ? get_post_meta($post_id,'_ss_sermon_date',  true) : '';
    $series_id  = $is_edit ? (int)get_post_meta($post_id,'_ss_series_id',    true) : 0;
    $order      = $is_edit ? (int)get_post_meta($post_id,'_ss_series_order', true) : '';
    $scr_ref    = $is_edit ? get_post_meta($post_id,'_ss_scripture_ref', true) : '';
    $scr_url    = $is_edit ? get_post_meta($post_id,'_ss_scripture_url', true) : '';
    $notes      = $is_edit ? get_post_meta($post_id,'_ss_sermon_notes',  true) : '';
    $resources  = $is_edit ? (get_post_meta($post_id,'_ss_resources',true) ?: []) : [];
    $yt_synced  = $is_edit ? get_post_meta($post_id,'_ss_yt_synced', true) : '';
    $topics     = $is_edit ? implode(', ', wp_get_post_terms($post_id,'ss_topic',  ['fields'=>'names'])) : '';
    $speakers   = $is_edit ? implode(', ', wp_get_post_terms($post_id,'ss_speaker',['fields'=>'names'])) : '';
    $topic_ids   = $is_edit ? wp_get_post_terms($post_id,'ss_topic',  ['fields'=>'ids']) : [];
    $speaker_ids = $is_edit ? wp_get_post_terms($post_id,'ss_speaker',['fields'=>'ids']) : [];

    $all_series   = get_posts(['post_type'=>'ss_series','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','post_status'=>'any']);
    $all_topics   = get_terms(['taxonomy'=>'ss_topic',  'hide_empty'=>false,'orderby'=>'name']);
    $all_speakers = get_terms(['taxonomy'=>'ss_speaker','hide_empty'=>false,'orderby'=>'name']);
    $bible_books  = ss_bible_books();
    $bible_ver    = get_option('sermon_suite_bible_version','NIV');
    $vid_id       = ss_get_youtube_id($youtube);
    ?>
    <div class="wrap gcc-custom-editor">

        <!-- Header -->
        <div class="gcc-editor-header">
            <div class="gcc-editor-header-left">
                <a href="<?php echo admin_url('edit.php?post_type=ss_sermon'); ?>" class="gcc-back-btn">← All Sermons</a>
                <h1><?php echo $is_edit ? 'Edit Sermon' : 'Add New Sermon'; ?></h1>
            </div>
            <div class="gcc-editor-header-right">
                <select id="gcc-status" class="gcc-status-select">
                    <option value="publish" <?php selected($status,'publish'); ?>>Published</option>
                    <option value="draft"   <?php selected($status,'draft');   ?>>Draft</option>
                    <option value="private" <?php selected($status,'private'); ?>>Private</option>
                </select>
                <?php if ($is_edit): ?><a href="<?php echo get_permalink($post_id); ?>" class="gcc-view-btn" target="_blank">View ↗</a><?php endif; ?>
                <button id="gcc-save-btn" class="gcc-primary-btn">Save Sermon</button>
            </div>
        </div>

        <?php if ($yt_synced): ?>
        <div class="gcc-editor-notice gcc-notice-info">🔗 Synced from YouTube <code><?php echo esc_html($yt_synced); ?></code>. Your edits here won't be overwritten by future syncs.</div>
        <?php endif; ?>

        <div id="gcc-toast" class="gcc-save-feedback" style="display:none;"></div>

        <div class="gcc-editor-body">

            <!-- ── MAIN COLUMN ──────────────────────────────── -->
            <div class="gcc-editor-main">

                <!-- Title -->
                <div class="gcc-card">
                    <div class="gcc-card-body">
                        <div class="gcc-field">
                            <label class="gcc-label">Sermon Title <span class="gcc-required">*</span></label>
                            <input type="text" id="gcc-title" class="gcc-input gcc-title-input"
                                   value="<?php echo esc_attr($title); ?>"
                                   placeholder="e.g. How's Your Attitude?" />
                        </div>
                        <div class="gcc-field">
                            <label class="gcc-label">Description <span class="gcc-label-hint">shown on archive cards</span></label>
                            <textarea id="gcc-content" class="gcc-textarea" rows="3"
                                      placeholder="A brief description of this sermon…"><?php echo esc_textarea($content); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Sermon Notes -->
                <div class="gcc-card">
                    <div class="gcc-card-header">📝 Sermon Notes / Outline <span class="gcc-label-hint" style="text-transform:none;font-weight:400;">Optional — shown as a collapsible section below the video</span></div>
                    <div class="gcc-card-body">
                        <textarea id="gcc-notes" class="gcc-textarea" rows="6"
                                  placeholder="Outline, fill-in-the-blank notes, or key points…"><?php echo esc_textarea($notes); ?></textarea>
                    </div>
                </div>

                <!-- Resources -->
                <div class="gcc-card">
                    <div class="gcc-card-header">📎 Resources & Downloads</div>
                    <div class="gcc-card-body">
                        <div id="gcc-resources-list">
                        <?php foreach ($resources as $r): ?>
                            <div class="gcc-resource-row">
                                <input type="text" class="gcc-input" name="res_label[]" value="<?php echo esc_attr($r['label']??''); ?>" placeholder="Label (e.g. Discussion Guide)" />
                                <input type="url"  class="gcc-input" name="res_url[]"   value="<?php echo esc_attr($r['url']??'');   ?>" placeholder="https://…" />
                                <select name="res_type[]" class="gcc-select">
                                    <option value="pdf"        <?php selected($r['type']??'','pdf');        ?>>📄 PDF</option>
                                    <option value="devotional" <?php selected($r['type']??'','devotional'); ?>>📖 Devotional</option>
                                    <option value="notes"      <?php selected($r['type']??'','notes');      ?>>📝 Notes</option>
                                    <option value="link"       <?php selected($r['type']??'','link');       ?>>🔗 Link</option>
                                </select>
                                <button type="button" class="gcc-remove-btn gcc-remove-resource">✕</button>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <button type="button" id="gcc-add-resource" class="gcc-add-row-btn">+ Add Resource</button>
                    </div>
                </div>

            </div><!-- main -->

            <!-- ── SIDEBAR ──────────────────────────────────── -->
            <div class="gcc-editor-sidebar">

                <!-- Video -->
                <div class="gcc-sidebar-card">
                    <div class="gcc-sidebar-card-header">🎬 Video</div>
                    <div class="gcc-sidebar-card-body">
                        <div class="gcc-field">
                            <label class="gcc-label">YouTube URL or Video ID</label>
                            <input type="text" id="gcc-youtube" class="gcc-input"
                                   value="<?php echo esc_attr($youtube); ?>"
                                   placeholder="https://youtu.be/…" />
                        </div>
                        <div class="gcc-yt-preview" id="gcc-yt-preview" style="display:<?php echo $vid_id?'block':'none';?>;">
                            <?php if ($vid_id): ?><img src="https://img.youtube.com/vi/<?php echo esc_attr($vid_id); ?>/mqdefault.jpg" /><?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Date & Series -->
                <div class="gcc-sidebar-card">
                    <div class="gcc-sidebar-card-header">📅 Date & Series</div>
                    <div class="gcc-sidebar-card-body">
                        <div class="gcc-field">
                            <label class="gcc-label">Sermon Date</label>
                            <input type="date" id="gcc-date" class="gcc-input" value="<?php echo esc_attr($date); ?>" />
                        </div>
                        <div class="gcc-field">
                            <label class="gcc-label">Series</label>
                            <select id="gcc-series" class="gcc-select">
                                <option value="0">— No Series —</option>
                                <?php foreach ($all_series as $s): ?>
                                <option value="<?php echo $s->ID; ?>" <?php selected($series_id,$s->ID); ?>><?php echo esc_html($s->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="gcc-field">
                            <label class="gcc-label">Message # in Series</label>
                            <input type="number" id="gcc-order" class="gcc-input gcc-input-inline" style="width:80px;"
                                   value="<?php echo esc_attr($order); ?>" min="1" placeholder="1" />
                        </div>
                    </div>
                </div>

                <!-- Scripture -->
                <div class="gcc-sidebar-card">
                    <div class="gcc-sidebar-card-header">📖 Scripture</div>
                    <div class="gcc-sidebar-card-body">

                        <div class="gcc-bible-picker">
                            <div class="gcc-picker-row">
                                <div class="gcc-picker-col">
                                    <label>Book</label>
                                    <select id="gcc-book" class="gcc-select">
                                        <option value="">Select…</option>
                                        <?php foreach ($bible_books as $b): ?>
                                        <option value="<?php echo esc_attr($b); ?>"><?php echo esc_html($b); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="gcc-picker-col">
                                    <label>Chapter</label>
                                    <select id="gcc-chapter" class="gcc-select" disabled><option value="">—</option></select>
                                </div>
                            </div>
                            <div class="gcc-picker-row">
                                <div class="gcc-picker-col">
                                    <label>From verse</label>
                                    <select id="gcc-verse-start" class="gcc-select" disabled><option value="">—</option></select>
                                </div>
                                <div class="gcc-picker-col">
                                    <label>To verse</label>
                                    <select id="gcc-verse-end" class="gcc-select" disabled><option value="">—</option></select>
                                </div>
                            </div>
                            <button type="button" id="gcc-apply-ref" class="gcc-apply-btn" disabled>Apply Reference →</button>
                        </div>

                        <div class="gcc-field" style="margin-top:14px;border-top:1px solid #f0f0f0;padding-top:12px;">
                            <label class="gcc-label">Reference</label>
                            <input type="text" id="gcc-scr-ref" class="gcc-input"
                                   value="<?php echo esc_attr($scr_ref); ?>"
                                   placeholder="e.g. John 3:16-17" />
                        </div>
                        <div class="gcc-field">
                            <label class="gcc-label">Bible Gateway Link</label>
                            <div class="gcc-input-row">
                                <input type="url" id="gcc-scr-url" class="gcc-input"
                                       value="<?php echo esc_attr($scr_url); ?>"
                                       placeholder="Auto-generated or paste…" />
                                <button type="button" id="gcc-gen-url" class="gcc-link-btn" title="Generate from reference">🔗</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Speakers & Topics -->
                <div class="gcc-sidebar-card">
                    <div class="gcc-sidebar-card-header">🏷 People & Topics</div>
                    <div class="gcc-sidebar-card-body">

                        <!-- Speakers -->
                        <div class="gcc-field">
                            <label class="gcc-label">Speaker(s)</label>
                            <div class="gcc-pick-list" id="gcc-speaker-list">
                                <?php foreach ($all_speakers as $sp): ?>
                                <label class="gcc-pick-item">
                                    <input type="checkbox" class="gcc-pick-speaker"
                                           value="<?php echo esc_attr($sp->name); ?>"
                                           <?php checked(in_array($sp->term_id, $speaker_ids), true); ?> />
                                    <span><?php echo esc_html($sp->name); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="gcc-add-new-row">
                                <input type="text" id="gcc-new-speaker" class="gcc-input" placeholder="Add a new speaker…" />
                                <button type="button" class="gcc-add-new-btn" id="gcc-add-speaker">+ Add</button>
                            </div>
                            <input type="hidden" id="gcc-speakers" value="<?php echo esc_attr($speakers); ?>" />
                        </div>

                        <!-- Topics -->
                        <div class="gcc-field">
                            <label class="gcc-label">Topics</label>
                            <div class="gcc-pick-list" id="gcc-topic-list">
                                <?php foreach ($all_topics as $t): ?>
                                <label class="gcc-pick-item">
                                    <input type="checkbox" class="gcc-pick-topic"
                                           value="<?php echo esc_attr($t->name); ?>"
                                           <?php checked(in_array($t->term_id, $topic_ids), true); ?> />
                                    <span><?php echo esc_html($t->name); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="gcc-add-new-row">
                                <input type="text" id="gcc-new-topic" class="gcc-input" placeholder="Add a new topic…" />
                                <button type="button" class="gcc-add-new-btn" id="gcc-add-topic">+ Add</button>
                            </div>
                            <input type="hidden" id="gcc-topics" value="<?php echo esc_attr($topics); ?>" />
                        </div>

                    </div>
                </div>

                <!-- Campus -->
                <?php
                $all_campuses    = get_terms(['taxonomy'=>'ss_campus','hide_empty'=>false,'orderby'=>'name']);
                $current_campuses = $is_edit ? wp_get_post_terms($post_id, 'ss_campus', ['fields'=>'ids']) : [];
                $current_campus_id = !empty($current_campuses) ? $current_campuses[0] : 0;
                ?>
                <div class="gcc-sidebar-card">
                    <div class="gcc-sidebar-card-header">🏛 Campus</div>
                    <div class="gcc-sidebar-card-body">
                        <div class="gcc-field">
                            <?php if (!empty($all_campuses)): ?>
                            <select id="gcc-sermon-campus" class="gcc-select">
                                <option value="0">— All Campuses —</option>
                                <?php foreach ($all_campuses as $c): ?>
                                <option value="<?php echo $c->term_id; ?>"
                                    <?php selected($current_campus_id, $c->term_id); ?>>
                                    <?php echo esc_html($c->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <p style="margin:0;font-size:0.8rem;color:#888;">
                                No campuses yet. <a href="<?php echo admin_url('edit-tags.php?taxonomy=ss_campus&post_type=ss_sermon'); ?>">Add campuses →</a>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div><!-- sidebar -->
        </div><!-- body -->
    </div><!-- wrap -->

    <input type="hidden" id="gcc-post-id" value="<?php echo $post_id; ?>" />

    <script>
    if (typeof sermonSuiteAdmin === 'undefined') {
        sermonSuiteAdmin = {
            ajaxUrl:   '<?php echo esc_js(admin_url("admin-ajax.php")); ?>',
            restUrl:   '<?php echo esc_js(rest_url("sermon-suite/v1/")); ?>',
            nonce:     '<?php echo esc_js(wp_create_nonce("sermon_suite_admin")); ?>',
            restNonce: '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>',
        };
    }
    </script>
    <script>
    jQuery(function($){
        var bibleData = <?php echo json_encode(ss_bible_data()); ?>;
        var bibleVer  = '<?php echo esc_js($bible_ver); ?>';

        // YouTube preview
        $('#gcc-youtube').on('blur', function(){
            var id = extractYtId($(this).val().trim());
            if (id) $('#gcc-yt-preview').html('<img src="https://img.youtube.com/vi/'+id+'/mqdefault.jpg" />').show();
        });
        function extractYtId(v) {
            var m;
            if (/^[a-zA-Z0-9_\-]{11}$/.test(v)) return v;
            if ((m=v.match(/youtu\.be\/([a-zA-Z0-9_\-]{11})/)))            return m[1];
            if ((m=v.match(/[?&\/](?:v=|embed\/)([a-zA-Z0-9_\-]{11})/)))  return m[1];
            return '';
        }

        // Bible picker cascades
        $('#gcc-book').on('change', function(){
            var book = $(this).val();
            $('#gcc-chapter, #gcc-verse-start, #gcc-verse-end').empty().append('<option value="">—</option>').prop('disabled',true);
            $('#gcc-apply-ref').prop('disabled',true);
            if (!book || !bibleData[book]) return;
            var $ch = $('#gcc-chapter').prop('disabled',false);
            for (var i=1; i<=bibleData[book].length; i++) $ch.append('<option value="'+i+'">'+i+'</option>');
        });
        $('#gcc-chapter').on('change', function(){
            var book=($('#gcc-book').val()), ch=parseInt($(this).val());
            $('#gcc-verse-start,#gcc-verse-end').empty().append('<option value="">—</option>').prop('disabled',true);
            $('#gcc-apply-ref').prop('disabled',true);
            if (!book||!ch) return;
            var vc = bibleData[book][ch-1];
            var $vs=$('#gcc-verse-start').prop('disabled',false);
            var $ve=$('#gcc-verse-end').prop('disabled',false);
            for (var v=1;v<=vc;v++) { $vs.append('<option value="'+v+'">'+v+'</option>'); $ve.append('<option value="'+v+'">'+v+'</option>'); }
            $ve.val(vc); // default to last verse
            $('#gcc-apply-ref').prop('disabled',false);
        });
        $('#gcc-apply-ref').on('click', function(){
            var book=$('#gcc-book').val(), ch=$('#gcc-chapter').val();
            var vs=$('#gcc-verse-start').val(), ve=$('#gcc-verse-end').val();
            if (!book||!ch) return;
            var ref = book+' '+ch;
            if (vs) { ref += ':'+vs; if (ve && ve!==vs) ref += '-'+ve; }
            $('#gcc-scr-ref').val(ref);
            $('#gcc-scr-url').val('https://www.biblegateway.com/passage/?search='+encodeURIComponent(ref)+'&version='+bibleVer);
        });

        // Auto-generate BG link on ref blur
        $('#gcc-scr-ref').on('blur', function(){
            var ref=$(this).val().trim();
            if (ref && !$('#gcc-scr-url').val()) {
                $('#gcc-scr-url').val('https://www.biblegateway.com/passage/?search='+encodeURIComponent(ref)+'&version='+bibleVer);
            }
        });
        $('#gcc-gen-url').on('click', function(){
            var ref=$('#gcc-scr-ref').val().trim();
            if (ref) $('#gcc-scr-url').val('https://www.biblegateway.com/passage/?search='+encodeURIComponent(ref)+'&version='+bibleVer);
        });

        // ── Speakers & Topics: keep hidden inputs in sync with checkboxes ──
        function syncPicks(listClass, hiddenId) {
            var vals = [];
            $(listClass + ':checked').each(function(){ vals.push($(this).val()); });
            $(hiddenId).val(vals.join(', '));
        }
        $(document).on('change', '.gcc-pick-speaker', function(){ syncPicks('.gcc-pick-speaker', '#gcc-speakers'); });
        $(document).on('change', '.gcc-pick-topic',   function(){ syncPicks('.gcc-pick-topic',   '#gcc-topics'); });

        // Add-new speaker
        function addPick(inputId, listId, cls, hiddenId) {
            var name = $(inputId).val().trim();
            if (!name) return;
            // avoid duplicates (case-insensitive)
            var exists = false;
            $(cls).each(function(){ if ($(this).val().toLowerCase() === name.toLowerCase()) { exists = true; $(this).prop('checked', true); } });
            if (!exists) {
                var id = 'pick_' + Math.random().toString(36).substr(2,8);
                var item = $('<label class="gcc-pick-item"><input type="checkbox" class="' + cls.substr(1) + '" value="' + name.replace(/"/g,'&quot;') + '" checked /><span>' + $('<div>').text(name).html() + '</span></label>');
                $(listId).append(item);
            }
            $(inputId).val('');
            syncPicks(cls, hiddenId);
        }
        $('#gcc-add-speaker').on('click', function(){ addPick('#gcc-new-speaker', '#gcc-speaker-list', '.gcc-pick-speaker', '#gcc-speakers'); });
        $('#gcc-add-topic').on('click',   function(){ addPick('#gcc-new-topic',   '#gcc-topic-list',   '.gcc-pick-topic',   '#gcc-topics'); });
        // Enter key adds instead of submitting
        $('#gcc-new-speaker').on('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); $('#gcc-add-speaker').click(); } });
        $('#gcc-new-topic').on('keydown',   function(e){ if (e.key === 'Enter') { e.preventDefault(); $('#gcc-add-topic').click(); } });

        // Resources
        $('#gcc-add-resource').on('click', function(){
            $('#gcc-resources-list').append(
                '<div class="gcc-resource-row">' +
                '<input type="text" class="gcc-input" name="res_label[]" placeholder="Label" />' +
                '<input type="url"  class="gcc-input" name="res_url[]"   placeholder="https://…" />' +
                '<select name="res_type[]" class="gcc-select"><option value="pdf">📄 PDF</option><option value="devotional">📖 Devotional</option><option value="notes">📝 Notes</option><option value="link">🔗 Link</option></select>' +
                '<button type="button" class="gcc-remove-btn gcc-remove-resource">✕</button>' +
                '</div>'
            );
        });
        $(document).on('click','.gcc-remove-resource', function(){ $(this).closest('.gcc-resource-row').remove(); });

        // Save
        $('#gcc-save-btn').on('click', function(){
            var btn=$(this).prop('disabled',true).text('Saving…');
            var res_labels=[], res_urls=[], res_types=[];
            $('.gcc-resource-row').each(function(){
                res_labels.push($(this).find('[name="res_label[]"]').val()||'');
                res_urls.push(  $(this).find('[name="res_url[]"]').val()  ||'');
                res_types.push( $(this).find('[name="res_type[]"]').val() ||'link');
            });
            $.post(ajaxurl, {
                action:'ss_save_sermon', nonce:'<?php echo wp_create_nonce("ss_custom_editor"); ?>',
                post_id:$('#gcc-post-id').val(), title:$('#gcc-title').val(), content:$('#gcc-content').val(),
                status:$('#gcc-status').val(), youtube_id:$('#gcc-youtube').val(), sermon_date:$('#gcc-date').val(),
                series_id:$('#gcc-series').val(), series_order:$('#gcc-order').val(),
                scripture_ref:$('#gcc-scr-ref').val(), scripture_url:$('#gcc-scr-url').val(),
                sermon_notes:$('#gcc-notes').val(), ss_topics:$('#gcc-topics').val(), ss_speakers:$('#gcc-speakers').val(), sermon_campus:$('#gcc-sermon-campus').val()||0,
                res_label:res_labels, res_url:res_urls, res_type:res_types
            }, function(res){
                btn.prop('disabled',false).text('Save Sermon');
                if (res.success) {
                    if (!$('#gcc-post-id').val()) { window.location.href=res.data.edit_url+'&saved=1'; return; }
                    toast(res.data.message,'success');
                } else { toast('Error: '+res.data,'error'); }
            });
        });

        if (new URLSearchParams(window.location.search).get('saved')) toast('Sermon saved!','success');

        function toast(msg,type){
            $('#gcc-toast').text(msg).removeClass('gcc-feedback-success gcc-feedback-error')
                .addClass('gcc-feedback-'+type).show();
            setTimeout(function(){ $('#gcc-toast').fadeOut(); },3000);
        }
    });
    </script>
    <?php
}

// ── Series editor page ────────────────────────────────────────────────────────
function ss_render_series_editor() {
    wp_enqueue_media();
    ss_editor_critical_css();

    $post_id  = absint($_GET['post_id'] ?? 0);
    $post     = $post_id ? get_post($post_id) : null;
    $is_edit  = $post && $post->post_type === 'ss_series';

    $title    = $is_edit ? $post->post_title   : '';
    $content  = $is_edit ? $post->post_content : '';
    $status   = $is_edit ? $post->post_status  : 'publish';
    $start    = $is_edit ? get_post_meta($post_id,'_ss_series_start_date',  true) : '';
    $end      = $is_edit ? get_post_meta($post_id,'_ss_series_end_date',    true) : '';
    $img_sm   = $is_edit ? get_post_meta($post_id,'_ss_series_image_sm',    true) : '';
    $img_lg   = $is_edit ? get_post_meta($post_id,'_ss_series_image_lg',    true) : '';
    $featured = $is_edit ? get_post_meta($post_id,'_ss_series_featured',    true) : '';
    $playlist = $is_edit ? get_post_meta($post_id,'_ss_series_yt_playlist', true) : '';
    $last_sync= $is_edit ? get_post_meta($post_id,'_ss_series_yt_last_sync',true) : '';
    $api_key  = get_option('sermon_suite_yt_api_key','');
    $sermons  = $is_edit ? ss_get_series_sermons($post_id) : [];
    ?>
    <div class="wrap gcc-custom-editor">

        <!-- Header -->
        <div class="gcc-editor-header">
            <div class="gcc-editor-header-left">
                <a href="<?php echo admin_url('edit.php?post_type=ss_series'); ?>" class="gcc-back-btn">← All Series</a>
                <h1><?php echo $is_edit ? 'Edit Series' : 'Add New Series'; ?></h1>
            </div>
            <div class="gcc-editor-header-right">
                <select id="gcc-status" class="gcc-status-select">
                    <option value="publish" <?php selected($status,'publish'); ?>>Published</option>
                    <option value="draft"   <?php selected($status,'draft');   ?>>Draft</option>
                </select>
                <?php if ($is_edit): ?><a href="<?php echo get_permalink($post_id); ?>" class="gcc-view-btn" target="_blank">View ↗</a><?php endif; ?>
                <button id="gcc-save-btn" class="gcc-primary-btn">Save Series</button>
            </div>
        </div>

        <div id="gcc-toast" class="gcc-save-feedback" style="display:none;"></div>

        <div class="gcc-editor-body">

            <!-- ── MAIN COLUMN ──────────────────────────────── -->
            <div class="gcc-editor-main">

                <!-- Title + description -->
                <div class="gcc-card">
                    <div class="gcc-card-body">
                        <div class="gcc-field">
                            <label class="gcc-label">Series Title <span class="gcc-required">*</span></label>
                            <input type="text" id="gcc-title" class="gcc-input gcc-title-input"
                                   value="<?php echo esc_attr($title); ?>"
                                   placeholder="e.g. Philippians — Reset Your Attitude" />
                        </div>
                        <div class="gcc-field">
                            <label class="gcc-label">Description</label>
                            <textarea id="gcc-content" class="gcc-textarea" rows="4"
                                      placeholder="A brief description of this series…"><?php echo esc_textarea($content); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- YouTube Sync (only on edit) -->
                <?php if ($is_edit): ?>
                <div class="gcc-card">
                    <div class="gcc-card-header">🔄 YouTube Playlist Sync</div>
                    <div class="gcc-card-body">
                        <?php if (!$api_key): ?>
                        <div class="gcc-editor-notice gcc-notice-warning">
                            ⚠️ No YouTube API key set. <a href="<?php echo admin_url('admin.php?page=sermon-suite-settings'); ?>">Add one in Settings →</a>
                        </div>
                        <?php endif; ?>
                        <p style="margin:0 0 10px;font-size:0.85rem;color:#555;line-height:1.5;">
                            Paste a playlist URL — new videos become sermon drafts automatically.
                            Existing sermons and any edits you've made are never overwritten.
                        </p>
                        <div class="gcc-sync-row">
                            <input type="text" id="gcc-playlist" class="gcc-input"
                                   value="<?php echo esc_attr($playlist); ?>"
                                   placeholder="https://www.youtube.com/playlist?list=PL…" />
                            <button type="button" id="gcc-sync-btn" class="gcc-sync-btn" data-series="<?php echo $post_id; ?>" <?php echo !$api_key?'disabled':'';?>>
                                🔄 Sync Now
                            </button>
                            <span class="spinner" id="gcc-sync-spin" style="float:none;display:none;margin-top:4px;"></span>
                        </div>
                        <?php if ($last_sync): ?>
                        <p style="margin:8px 0 0;font-size:0.75rem;color:#999;">
                            Last synced: <?php echo date_i18n(get_option('date_format').' '.get_option('time_format'),strtotime($last_sync)); ?>
                        </p>
                        <?php endif; ?>
                        <div id="gcc-sync-log" class="gcc-sync-log"></div>
                    </div>
                </div>

                <!-- Sermons in this series -->
                <?php if (!empty($sermons)): ?>
                <div class="gcc-card">
                    <div class="gcc-card-header">📋 Messages in This Series <span style="font-weight:400;text-transform:none;letter-spacing:0;">(<?php echo count($sermons); ?>)</span></div>
                    <div class="gcc-sermon-list">
                        <?php foreach ($sermons as $s):
                            $s_date = ss_format_sermon_date(get_post_meta($s->ID,'_ss_sermon_date',true));
                            $s_spk  = implode(', ', wp_get_post_terms($s->ID,'ss_speaker',['fields'=>'names']));
                            $s_num  = (int)get_post_meta($s->ID,'_ss_series_order',true);
                            $s_stat = get_post_status($s->ID);
                        ?>
                        <div class="gcc-sermon-row">
                            <span class="gcc-sermon-num"><?php echo $s_num ?: '—'; ?></span>
                            <div class="gcc-sermon-info">
                                <a href="<?php echo admin_url('admin.php?page=ss-edit-sermon&post_id='.$s->ID); ?>" class="gcc-sermon-name">
                                    <?php echo esc_html($s->post_title); ?>
                                    <?php if ($s_stat==='draft'): ?><span class="gcc-draft-badge">draft</span><?php endif; ?>
                                </a>
                                <span class="gcc-sermon-sub">
                                    <?php echo esc_html($s_spk ?: '—'); ?>
                                    <?php if ($s_date) echo ' · '.esc_html($s_date); ?>
                                </span>
                            </div>
                            <a href="<?php echo admin_url('admin.php?page=ss-edit-sermon&post_id='.$s->ID); ?>" class="gcc-edit-link">Edit</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="padding:12px 16px;border-top:1px solid #f0f0f0;">
                        <a href="<?php echo admin_url('admin.php?page=ss-add-sermon'); ?>" class="button">+ Add Sermon to This Series</a>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; // is_edit ?>

            </div><!-- main -->

            <!-- ── SIDEBAR ──────────────────────────────────── -->
            <div class="gcc-editor-sidebar">

                <!-- Dates -->
                <div class="gcc-sidebar-card">
                    <div class="gcc-sidebar-card-header">📅 Dates</div>
                    <div class="gcc-sidebar-card-body">
                        <div class="gcc-field">
                            <label class="gcc-label">Start Date</label>
                            <input type="date" id="gcc-start" class="gcc-input" value="<?php echo esc_attr($start); ?>" />
                        </div>
                        <div class="gcc-field">
                            <label class="gcc-label">End Date <span class="gcc-label-hint">leave blank if ongoing</span></label>
                            <input type="date" id="gcc-end" class="gcc-input" value="<?php echo esc_attr($end); ?>" />
                        </div>
                    </div>
                </div>

                <!-- Images -->
                <div class="gcc-sidebar-card">
                    <div class="gcc-sidebar-card-header">🖼 Images</div>
                    <div class="gcc-sidebar-card-body">
                        <div class="gcc-field">
                            <label class="gcc-label">Thumbnail <span class="gcc-label-hint">600×338, used in cards</span></label>
                            <div class="gcc-img-well" id="gcc-well-sm">
                                <?php if ($img_sm): ?>
                                <img src="<?php echo esc_url($img_sm); ?>" id="gcc-preview-sm" />
                                <?php else: ?>
                                <div class="gcc-img-well-empty" id="gcc-well-sm-empty"><span>🖼</span>Click to upload</div>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="gcc-img-sm" value="<?php echo esc_attr($img_sm); ?>" />
                            <div class="gcc-img-actions">
                                <button type="button" class="button" id="gcc-pick-sm">Select Image</button>
                                <?php if ($img_sm): ?><button type="button" class="button gcc-clear-img" data-field="gcc-img-sm" data-well="gcc-well-sm" data-empty="gcc-well-sm-empty">Remove</button><?php endif; ?>
                            </div>
                        </div>
                        <div class="gcc-field">
                            <label class="gcc-label">Hero / Large <span class="gcc-label-hint">1000×563, series header</span></label>
                            <div class="gcc-img-well" id="gcc-well-lg">
                                <?php if ($img_lg): ?>
                                <img src="<?php echo esc_url($img_lg); ?>" id="gcc-preview-lg" />
                                <?php else: ?>
                                <div class="gcc-img-well-empty" id="gcc-well-lg-empty"><span>🖼</span>Click to upload</div>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="gcc-img-lg" value="<?php echo esc_attr($img_lg); ?>" />
                            <div class="gcc-img-actions">
                                <button type="button" class="button" id="gcc-pick-lg">Select Image</button>
                                <?php if ($img_lg): ?><button type="button" class="button gcc-clear-img" data-field="gcc-img-lg" data-well="gcc-well-lg" data-empty="gcc-well-lg-empty">Remove</button><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category -->
                <div class="gcc-sidebar-card">
                    <div class="gcc-sidebar-card-header">📂 Category</div>
                    <div class="gcc-sidebar-card-body">
                        <div class="gcc-field">
                            <label class="gcc-label">Series Category</label>
                            <?php
                            $current_cats = $is_edit ? wp_get_post_terms($post_id, 'ss_series_category', ['fields'=>'ids']) : [];
                            $all_cats = get_terms(['taxonomy'=>'ss_series_category','hide_empty'=>false,'orderby'=>'name']);
                            ?>
                            <select id="gcc-series-category" class="gcc-select">
                                <option value="0">— Uncategorized —</option>
                                <?php foreach ($all_cats as $cat) : ?>
                                <option value="<?php echo $cat->term_id; ?>"
                                    <?php selected(in_array($cat->term_id, $current_cats), true); ?>>
                                    <?php echo esc_html(str_repeat('&nbsp;&nbsp;', $cat->parent ? 1 : 0) . $cat->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p style="margin:8px 0 0;font-size:0.75rem;color:#888;">
                                Manage categories under
                                <a href="<?php echo admin_url('edit-tags.php?taxonomy=ss_series_category&post_type=ss_series'); ?>">Series → Categories</a>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Campus -->
                <div class="gcc-sidebar-card">
                    <div class="gcc-sidebar-card-header">🏛 Campus</div>
                    <div class="gcc-sidebar-card-body">
                        <div class="gcc-field">
                            <label class="gcc-label">Campus</label>
                            <?php
                            $current_campuses = $is_edit ? wp_get_post_terms($post_id, 'ss_campus', ['fields'=>'ids']) : [];
                            $all_campuses = get_terms(['taxonomy'=>'ss_campus','hide_empty'=>false,'orderby'=>'name']);
                            ?>
                            <?php if (!empty($all_campuses)) : ?>
                            <select id="gcc-series-campus" class="gcc-select">
                                <option value="0">— All Campuses —</option>
                                <?php foreach ($all_campuses as $c) : ?>
                                <option value="<?php echo $c->term_id; ?>"
                                    <?php selected(in_array($c->term_id, $current_campuses), true); ?>>
                                    <?php echo esc_html($c->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else : ?>
                            <p style="margin:0;font-size:0.8rem;color:#888;">
                                No campuses yet.
                                <a href="<?php echo admin_url('edit-tags.php?taxonomy=ss_campus&post_type=ss_series'); ?>">Add campuses →</a>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Options -->
                <div class="gcc-sidebar-card">
                    <div class="gcc-sidebar-card-header">⚙️ Options</div>
                    <div class="gcc-sidebar-card-body">
                        <label class="gcc-toggle-label">
                            <input type="checkbox" id="gcc-featured" <?php checked($featured,'1'); ?> />
                            <div class="gcc-toggle-text">
                                <strong>Featured Series</strong>
                                <span>Highlighted on the sermon archive page</span>
                            </div>
                        </label>
                    </div>
                </div>

            </div><!-- sidebar -->
        </div><!-- body -->
    </div><!-- wrap -->

    <input type="hidden" id="gcc-post-id" value="<?php echo $post_id; ?>" />

    <script>
    if (typeof sermonSuiteAdmin === 'undefined') {
        sermonSuiteAdmin = {
            ajaxUrl:   '<?php echo esc_js(admin_url("admin-ajax.php")); ?>',
            restUrl:   '<?php echo esc_js(rest_url("sermon-suite/v1/")); ?>',
            nonce:     '<?php echo esc_js(wp_create_nonce("sermon_suite_admin")); ?>',
            restNonce: '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>',
        };
    }
    </script>
    <script>
    jQuery(function($){
        // Media picker helper
        function pickImage(hiddenId, wellId, emptyId) {
            var frame = wp.media({ title:'Select Image', button:{text:'Use this image'}, multiple:false, library:{type:'image'} });
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                var url = att.sizes && att.sizes.large ? att.sizes.large.url : att.url;
                $('#'+hiddenId).val(url);
                $('#'+wellId).html('<img src="'+url+'" style="width:100%;border-radius:4px;" />');
            });
            frame.open();
        }
        $('#gcc-pick-sm, #gcc-well-sm').on('click', function(){ pickImage('gcc-img-sm','gcc-well-sm','gcc-well-sm-empty'); });
        $('#gcc-pick-lg, #gcc-well-lg').on('click', function(){ pickImage('gcc-img-lg','gcc-well-lg','gcc-well-lg-empty'); });
        $(document).on('click', '.gcc-clear-img', function(e){
            e.stopPropagation();
            var f=$(this).data('field'), w=$(this).data('well'), em=$(this).data('empty');
            $('#'+f).val('');
            $('#'+w).html('<div class="gcc-img-well-empty" id="'+em+'"><span>🖼</span>Click to upload</div>');
            $(this).remove();
        });

        // Sync
        $('#gcc-sync-btn').on('click', function(){
            var playlist=$('#gcc-playlist').val().trim();
            if (!playlist) { alert('Enter a playlist URL.'); return; }
            $(this).prop('disabled',true);
            $('#gcc-sync-spin').show();
            $('#gcc-sync-log').empty().hide();
            $.post(ajaxurl,{ action:'ss_yt_sync_playlist', nonce:'<?php echo wp_create_nonce("ss_yt_sync"); ?>', series_id:'<?php echo $post_id; ?>', playlist:playlist }, function(res){
                $('#gcc-sync-btn').prop('disabled',false);
                $('#gcc-sync-spin').hide();
                if (res.success){
                    var html=''; res.data.log.forEach(function(l){ html+='<div>'+l+'</div>'; });
                    $('#gcc-sync-log').html(html).show();
                    toast(res.data.summary,'success');
                    setTimeout(function(){ location.reload(); },2000);
                } else { toast('Sync error: '+res.data,'error'); }
            });
        });

        // Save
        $('#gcc-save-btn').on('click', function(){
            $(this).prop('disabled',true).text('Saving…');
            $.post(ajaxurl,{
                action:'ss_save_series', nonce:'<?php echo wp_create_nonce("ss_custom_editor"); ?>',
                post_id:$('#gcc-post-id').val(), title:$('#gcc-title').val(), content:$('#gcc-content').val(),
                status:$('#gcc-status').val(), start_date:$('#gcc-start').val(), end_date:$('#gcc-end').val(),
                image_sm:$('#gcc-img-sm').val(), image_lg:$('#gcc-img-lg').val(),
                featured:$('#gcc-featured').is(':checked')?'1':'0',
                yt_playlist:$('#gcc-playlist').val(),
                series_category:$('#gcc-series-category').val(),
                series_campus:$('#gcc-series-campus').val()||0
            }, function(res){
                $('#gcc-save-btn').prop('disabled',false).text('Save Series');
                if (res.success){
                    if (!$('#gcc-post-id').val()) { window.location.href=res.data.edit_url+'&saved=1'; return; }
                    toast(res.data.message,'success');
                } else { toast('Error: '+res.data,'error'); }
            });
        });

        if (new URLSearchParams(window.location.search).get('saved')) toast('Series saved!','success');

        function toast(msg,type){
            $('#gcc-toast').text(msg).removeClass('gcc-feedback-success gcc-feedback-error')
                .addClass('gcc-feedback-'+type).show();
            setTimeout(function(){ $('#gcc-toast').fadeOut(); },3000);
        }
    });
    </script>
    <?php
}
