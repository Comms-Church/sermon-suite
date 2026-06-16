<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function sermon_suite_shortcode_generator_page() {
    $series_list  = get_posts(['post_type'=>'ss_series','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','post_status'=>'publish']);
    $sermon_list  = get_posts(['post_type'=>'ss_sermon','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','post_status'=>'publish','meta_key'=>'_ss_sermon_date','orderby'=>'meta_value','order'=>'DESC']);
    $all_pages    = get_posts(['post_type'=>'page','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','post_status'=>'publish']);
    ?>
    <div class="wrap gcc-admin-wrap gcc-sc-generator">
        <h1>⚡ Shortcode Generator</h1>
        <p>Pick what you want to display, configure the options, and copy the shortcode into any page or your page builder element.</p>

        <div class="gcc-sc-layout">

            <!-- ── LEFT: shortcode picker ── -->
            <div class="gcc-sc-picker">

                <div class="gcc-sc-tabs">
                    <button class="gcc-sc-tab active" data-tab="archive">Sermon Archive</button>
                    <button class="gcc-sc-tab" data-tab="hero">Latest Message Hero</button>
                    <button class="gcc-sc-tab" data-tab="series-grid">Series Grid</button>
                    <button class="gcc-sc-tab" data-tab="player">Sermon Player</button>
                    <button class="gcc-sc-tab" data-tab="related">Related Sermons</button>
                    <button class="gcc-sc-tab" data-tab="message-list">Message List</button>
                    <button class="gcc-sc-tab" data-tab="topics">Browse by Topic</button>
                </div>

                <!-- ── Archive ── -->
                <div class="gcc-sc-panel active" id="gcc-tab-archive">
                    <h3>Sermon Archive</h3>
                    <p class="gcc-sc-desc">The main sermons page — shows a grid of series cards with an optional topic filter bar. Clicking a card opens that series.</p>
                    <div class="gcc-sc-fields">
                        <div class="gcc-sc-field">
                            <label>Layout</label>
                            <select data-param="layout" data-sc="ss_sermon_archive" id="gcc-archive-layout">
                                <option value="grid" selected>Grid — series cards (default)</option>
                                <option value="list">List — series blocks with sermon rows</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field gcc-grid-only">
                            <label>Columns <span style="font-weight:400;color:#888;">(grid only)</span></label>
                            <select data-param="columns" data-sc="ss_sermon_archive">
                                <option value="2">2</option>
                                <option value="3" selected>3 (default)</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field gcc-list-only" style="display:none;">
                            <label>Sermons shown per series <span style="font-weight:400;color:#888;">(list only)</span></label>
                            <select data-param="sermons_per_series" data-sc="ss_sermon_archive">
                                <option value="3">3</option>
                                <option value="5" selected>5 (default)</option>
                                <option value="8">8</option>
                                <option value="999">All</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Show Topic Filter Bar</label>
                            <select data-param="show_filter" data-sc="ss_sermon_archive">
                                <option value="true" selected>Yes (default)</option>
                                <option value="false">No</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Filter by Category</label>
                            <select data-param="category" data-sc="ss_sermon_archive">
                                <option value="">All categories</option>
                                <?php
                                $cats = get_terms(['taxonomy'=>'ss_series_category','hide_empty'=>false,'orderby'=>'name']);
                                foreach ($cats as $cat) {
                                    echo '<option value="'.esc_attr($cat->slug).'">'.esc_html($cat->name).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Filter by Campus</label>
                            <select data-param="campus" data-sc="ss_sermon_archive">
                                <option value="">All campuses</option>
                                <?php
                                $cpus = get_terms(['taxonomy'=>'ss_campus','hide_empty'=>false,'orderby'=>'name']);
                                foreach ($cpus as $c) {
                                    echo '<option value="'.esc_attr($c->slug).'">'.esc_html($c->name).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Featured Series First</label>
                            <select data-param="featured_first" data-sc="ss_sermon_archive">
                                <option value="true" selected>Yes (default)</option>
                                <option value="false">No</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Max Series to Show</label>
                            <select data-param="count" data-sc="ss_sermon_archive">
                                <option value="-1" selected>All (default)</option>
                                <option value="3">3</option>
                                <option value="6">6</option>
                                <option value="9">9</option>
                                <option value="12">12</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── Hero ── -->
                <div class="gcc-sc-panel" id="gcc-tab-hero">
                    <h3>Latest Message Hero</h3>
                    <p class="gcc-sc-desc">A full-width cinematic banner showing your most recent sermon — auto-updates every time you publish a new one. Great at the top of your sermons page.</p>
                    <div class="gcc-sc-fields">
                        <div class="gcc-sc-field">
                            <label>Label Text</label>
                            <input type="text" data-param="label" data-sc="ss_latest_hero"
                                   placeholder="Latest Message" value="Latest Message" class="gcc-sc-input" />
                        </div>
                    </div>
                </div>

                <!-- ── Series Grid ── -->
                <div class="gcc-sc-panel" id="gcc-tab-series-grid">
                    <h3>Series Grid</h3>
                    <p class="gcc-sc-desc">A clean grid of series cards. Use anywhere — homepage, sidebar, landing page.</p>
                    <div class="gcc-sc-fields">
                        <div class="gcc-sc-field">
                            <label>Columns</label>
                            <select data-param="columns" data-sc="ss_series_grid">
                                <option value="2">2</option>
                                <option value="3" selected>3 (default)</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>How Many Series</label>
                            <select data-param="count" data-sc="ss_series_grid">
                                <option value="-1" selected>All</option>
                                <option value="3">3</option>
                                <option value="6">6</option>
                                <option value="9">9</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Filter by Category</label>
                            <select data-param="category" data-sc="ss_series_grid">
                                <option value="">All categories</option>
                                <?php
                                $cats = get_terms(['taxonomy'=>'ss_series_category','hide_empty'=>false,'orderby'=>'name']);
                                foreach ($cats as $cat) {
                                    echo '<option value="'.esc_attr($cat->slug).'">'.esc_html($cat->name).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Featured Only</label>
                            <select data-param="featured" data-sc="ss_series_grid">
                                <option value="false" selected>No — show all</option>
                                <option value="true">Yes — featured only</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── Player ── -->
                <div class="gcc-sc-panel" id="gcc-tab-player">
                    <h3>Sermon Player</h3>
                    <p class="gcc-sc-desc">Embed a single sermon — video, scripture, and download links — anywhere on your site.</p>
                    <div class="gcc-sc-fields">
                        <div class="gcc-sc-field">
                            <label>Sermon</label>
                            <select data-param="id" data-sc="ss_sermon_player" class="gcc-sc-wide">
                                <option value="">— Pick a sermon —</option>
                                <?php foreach ($sermon_list as $s) :
                                    $date = get_post_meta($s->ID, '_ss_sermon_date', true);
                                    $label = get_the_title($s) . ($date ? ' (' . ss_format_sermon_date($date) . ')' : '');
                                ?>
                                <option value="<?php echo $s->ID; ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── Related ── -->
                <div class="gcc-sc-panel" id="gcc-tab-related">
                    <h3>Related Sermons</h3>
                    <p class="gcc-sc-desc">Shows "More from this series" and "More on this topic" grids. Normally auto-appears on sermon pages — use this shortcode to place it manually on a custom page.</p>
                    <div class="gcc-sc-fields">
                        <div class="gcc-sc-field">
                            <label>Sermon</label>
                            <select data-param="id" data-sc="ss_related_sermons" class="gcc-sc-wide">
                                <option value="">— Pick a sermon —</option>
                                <?php foreach ($sermon_list as $s) : ?>
                                <option value="<?php echo $s->ID; ?>"><?php echo esc_html(get_the_title($s)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Cards per Section</label>
                            <select data-param="count" data-sc="ss_related_sermons">
                                <option value="3">3</option>
                                <option value="4" selected>4 (default)</option>
                                <option value="6">6</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── Message List ── -->
                <div class="gcc-sc-panel" id="gcc-tab-message-list">
                    <h3>Message List</h3>
                    <p class="gcc-sc-desc">
                        A flat list of individual messages — no series grouping.
                        Perfect for churches that don't use series, or for showing
                        a filtered set like "all messages from this campus" or "all messages by this speaker."
                    </p>
                    <div class="gcc-sc-fields">
                        <div class="gcc-sc-field">
                            <label>Layout</label>
                            <select data-param="layout" data-sc="ss_message_list">
                                <option value="list" selected>List (default)</option>
                                <option value="grid">Grid</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field gcc-ml-grid-only" style="display:none;">
                            <label>Columns <span style="font-weight:400;color:#888;">(grid only)</span></label>
                            <select data-param="columns" data-sc="ss_message_list">
                                <option value="2">2</option>
                                <option value="3" selected>3</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Messages per page</label>
                            <select data-param="per_page" data-sc="ss_message_list">
                                <option value="10">10</option>
                                <option value="12" selected>12 (default)</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Filter by Campus</label>
                            <select data-param="campus" data-sc="ss_message_list">
                                <option value="">All campuses</option>
                                <?php
                                $cpus = get_terms(['taxonomy'=>'ss_campus','hide_empty'=>false,'orderby'=>'name']);
                                foreach ($cpus as $c) {
                                    echo '<option value="'.esc_attr($c->slug).'">'.esc_html($c->name).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Filter by Topic</label>
                            <select data-param="topic" data-sc="ss_message_list">
                                <option value="">All topics</option>
                                <?php
                                $tpcs = get_terms(['taxonomy'=>'ss_topic','hide_empty'=>false,'orderby'=>'name']);
                                foreach ($tpcs as $t) {
                                    echo '<option value="'.esc_attr($t->slug).'">'.esc_html($t->name).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Sort Order</label>
                            <select data-param="order" data-sc="ss_message_list">
                                <option value="DESC" selected>Newest first (default)</option>
                                <option value="ASC">Oldest first</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Show Filter Bar</label>
                            <select data-param="show_filter" data-sc="ss_message_list">
                                <option value="true" selected>Yes (default)</option>
                                <option value="false">No</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── Browse by Topic ── -->
                <div class="gcc-sc-panel" id="gcc-tab-topics">
                    <h3>Browse by Topic</h3>
                    <p class="gcc-sc-desc">
                        A directory grid of all your topics. Visitors click a topic to see
                        every sermon on it, across all series. Great for a "Topics" page or
                        a section on your sermons page.
                    </p>
                    <div class="gcc-sc-fields">
                        <div class="gcc-sc-field">
                            <label>Columns</label>
                            <select data-param="columns" data-sc="ss_topics">
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4" selected>4 (default)</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Order by</label>
                            <select data-param="orderby" data-sc="ss_topics">
                                <option value="count" selected>Most sermons first (default)</option>
                                <option value="name">Alphabetical</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Show sermon count</label>
                            <select data-param="show_count" data-sc="ss_topics">
                                <option value="true" selected>Yes (default)</option>
                                <option value="false">No</option>
                            </select>
                        </div>
                        <div class="gcc-sc-field">
                            <label>Minimum sermons to show a topic</label>
                            <select data-param="min_count" data-sc="ss_topics">
                                <option value="1" selected>1 (show all)</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                            </select>
                        </div>
                    </div>
                </div>

            </div><!-- .gcc-sc-picker -->

            <!-- ── RIGHT: output ── -->
            <div class="gcc-sc-output-wrap">
                <div class="gcc-sc-output-card">
                    <div class="gcc-sc-output-label">Your Shortcode</div>
                    <div class="gcc-sc-output" id="gcc-sc-output"><code>[ss_sermon_archive]</code></div>
                    <button id="gcc-sc-copy" class="button button-primary gcc-sc-copy-btn">
                        📋 Copy Shortcode
                    </button>
                    <div id="gcc-sc-copied" style="display:none;color:#065f46;font-weight:600;font-size:0.85rem;margin-top:8px;">✅ Copied to clipboard!</div>
                </div>

                <div class="gcc-sc-usage-card">
                    <h4>How to use</h4>
                    <ol>
                        <li>Copy the shortcode above</li>
                        <li>Open any WordPress page or post</li>
                        <li>Paste it into a <strong>Shortcode block</strong> (Gutenberg) or a <strong>Shortcode element</strong> (your page builder)</li>
                        <li>Save and view the page</li>
                    </ol>
                    <p style="margin-top:10px;font-size:0.82rem;color:#555;background:#eff6ff;padding:10px 12px;border-radius:5px;border-left:3px solid #2563eb;">
                        💡 <strong>Gutenberg users:</strong> Search "Sermon Suite" in the block inserter — no shortcodes needed.
                    </p>
                </div>

                <div class="gcc-sc-all-card">
                    <h4>All Available Shortcodes</h4>
                    <table class="gcc-sc-ref-table">
                        <tr>
                            <td><code>[ss_sermon_archive]</code></td>
                            <td>Series grid (default) or list layout with sermon rows</td>
                        </tr>
                        <tr>
                            <td><code>[ss_latest_hero]</code></td>
                            <td>Latest message hero banner — auto-updates</td>
                        </tr>
                        <tr>
                            <td><code>[ss_series_grid]</code></td>
                            <td>Grid of series cards — use anywhere</td>
                        </tr>
                        <tr>
                            <td><code>[ss_sermon_player id="X"]</code></td>
                            <td>Single sermon embed with video + resources</td>
                        </tr>
                        <tr>
                            <td><code>[ss_related_sermons id="X"]</code></td>
                            <td>Related sermons by series and topic</td>
                        </tr>
                        <tr>
                            <td><code>[ss_message_list]</code></td>
                            <td>Flat message list — no series grouping, filterable by campus/topic/speaker</td>
                        </tr>
                        <tr>
                            <td><code>[ss_topics]</code></td>
                            <td>Browse-by-topic directory grid — each topic links to all its sermons</td>
                        </tr>
                    </table>
                </div>
            </div>

        </div><!-- .gcc-sc-layout -->
    </div>

    <style>
    .gcc-sc-layout {
        display: flex;
        gap: 24px;
        align-items: flex-start;
        margin-top: 20px;
    }
    .gcc-sc-picker {
        flex: 1;
        min-width: 0;
        background: #fff;
        border: 1px solid #e2e2e2;
        border-radius: 8px;
        overflow: hidden;
    }
    .gcc-sc-tabs {
        display: flex;
        flex-wrap: wrap;
        border-bottom: 2px solid #e8e8e8;
        background: #f8f9fa;
    }
    .gcc-sc-tab {
        padding: 10px 16px;
        background: none;
        border: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        font-size: 0.82rem;
        font-weight: 600;
        color: #666;
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
    }
    .gcc-sc-tab:hover { color: #2563eb; }
    .gcc-sc-tab.active { color: #2563eb; border-bottom-color: #2563eb; background: #fff; }
    .gcc-sc-panel { display: none; padding: 20px 22px; }
    .gcc-sc-panel.active { display: block; }
    .gcc-sc-panel h3 { margin: 0 0 6px; font-size: 1rem; }
    .gcc-sc-desc { color: #666; font-size: 0.85rem; margin: 0 0 18px; line-height: 1.5; }
    .gcc-sc-fields { display: flex; flex-direction: column; gap: 14px; }
    .gcc-sc-field label { display: block; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: #444; margin-bottom: 5px; }
    .gcc-sc-field select, .gcc-sc-input {
        width: 100%; padding: 7px 10px; border: 1px solid #d1d5db; border-radius: 5px;
        font-size: 0.88rem; background: #fff; box-sizing: border-box;
    }
    .gcc-sc-wide { width: 100%; }

    .gcc-sc-output-wrap { width: 300px; flex-shrink: 0; display: flex; flex-direction: column; gap: 16px; }
    .gcc-sc-output-card {
        background: #fff; border: 1px solid #e2e2e2; border-radius: 8px; padding: 18px 20px;
    }
    .gcc-sc-output-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin-bottom: 10px; }
    .gcc-sc-output {
        background: #1a1a2e; color: #93c5fd; font-family: monospace; font-size: 0.9rem;
        padding: 14px 16px; border-radius: 6px; margin-bottom: 12px; word-break: break-all;
        min-height: 48px; display: flex; align-items: center;
    }
    .gcc-sc-output code { background: none; color: inherit; font-size: inherit; padding: 0; }
    .gcc-sc-copy-btn { width: 100%; justify-content: center; }
    .gcc-sc-usage-card, .gcc-sc-all-card {
        background: #fff; border: 1px solid #e2e2e2; border-radius: 8px; padding: 16px 18px;
    }
    .gcc-sc-usage-card h4, .gcc-sc-all-card h4 { margin: 0 0 10px; font-size: 0.88rem; }
    .gcc-sc-usage-card ol { margin: 0; padding-left: 18px; font-size: 0.82rem; color: #444; line-height: 1.8; }
    .gcc-sc-ref-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
    .gcc-sc-ref-table tr { border-bottom: 1px solid #f0f0f0; }
    .gcc-sc-ref-table tr:last-child { border-bottom: none; }
    .gcc-sc-ref-table td { padding: 6px 4px; vertical-align: top; }
    .gcc-sc-ref-table td:first-child { white-space: nowrap; padding-right: 10px; }
    .gcc-sc-ref-table code { font-size: 0.75rem; }
    @media (max-width: 900px) {
        .gcc-sc-layout { flex-direction: column; }
        .gcc-sc-output-wrap { width: 100%; }
    }
    </style>

    <script>
    jQuery(function($){
        // Defaults for each shortcode
        var defaults = {
            ss_sermon_archive: { layout:'grid', columns:'3', show_filter:'true', featured_first:'true', count:'-1', sermons_per_series:'5', category:'', campus:'' },
            ss_message_list:   { layout:'list', columns:'3', per_page:'12', campus:'', topic:'', order:'DESC', show_filter:'true' },
            ss_latest_hero:    { label:'Latest Message' },
            ss_series_grid:    { columns:'3', count:'-1', featured:'false', category:'' },
            ss_sermon_player:  { id:'' },
            ss_related_sermons:{ id:'', count:'4' },
            ss_topics:         { columns:'4', orderby:'count', show_count:'true', min_count:'1' },
        };

        function buildShortcode() {
            var $panel = $('.gcc-sc-panel.active');
            var sc     = $panel.find('[data-sc]').first().data('sc');
            if (!sc) return '[ss_sermon_archive]';

            var defs   = defaults[sc] || {};
            var params = {};
            $panel.find('[data-param]').each(function(){
                var param = $(this).data('param');
                var val   = $(this).val();
                var def   = String(defs[param] ?? '');
                // Only include if different from default, or if it's a required field (id)
                if (param === 'id' && val) params[param] = val;
                else if (param !== 'id' && String(val) !== def) params[param] = val;
            });

            var parts = '[' + sc;
            $.each(params, function(k,v){ parts += ' ' + k + '="' + v + '"'; });
            parts += ']';
            return parts;
        }

        function updateOutput() {
            var sc = buildShortcode();
            $('#gcc-sc-output').html('<code>' + sc + '</code>');
        }

        // Message list layout toggle
        $(document).on('change', '[data-sc="ss_message_list"][data-param="layout"]', function(){
            var isGrid = $(this).val() === 'grid';
            $('.gcc-ml-grid-only').toggle(isGrid);
            updateOutput();
        });

        // Show/hide grid vs list options
        $(document).on('change', '#gcc-archive-layout', function(){
            var isGrid = $(this).val() === 'grid';
            $('.gcc-grid-only').toggle(isGrid);
            $('.gcc-list-only').toggle(!isGrid);
            updateOutput();
        });

        // Tab switching
        $('.gcc-sc-tab').on('click', function(){
            var tab = $(this).data('tab');
            $('.gcc-sc-tab').removeClass('active');
            $('.gcc-sc-panel').removeClass('active');
            $(this).addClass('active');
            $('#gcc-tab-' + tab).addClass('active');
            updateOutput();
        });

        // Live update on any input change
        $(document).on('change input', '[data-param]', updateOutput);

        // Copy button
        $('#gcc-sc-copy').on('click', function(){
            var text = buildShortcode();
            navigator.clipboard.writeText(text).then(function(){
                $('#gcc-sc-copied').show();
                setTimeout(function(){ $('#gcc-sc-copied').hide(); }, 2000);
            }).catch(function(){
                // Fallback for older browsers
                var ta = document.createElement('textarea');
                ta.value = text; document.body.appendChild(ta);
                ta.select(); document.execCommand('copy');
                document.body.removeChild(ta);
                $('#gcc-sc-copied').show();
                setTimeout(function(){ $('#gcc-sc-copied').hide(); }, 2000);
            });
        });

        updateOutput();
    });
    </script>
    <?php
}
