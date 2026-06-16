<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── REST API endpoints for import (more reliable than admin-ajax) ──────────────
add_action( 'rest_api_init', 'sermon_suite_register_import_routes' );

function sermon_suite_register_import_routes() {
    $ns = 'sermon-suite/v1';

    // Ping — dead simple connectivity test
    register_rest_route( $ns, '/import/ping', [
        'methods'             => 'GET',
        'callback'            => function() {
            return rest_ensure_response([
                'message'        => 'pong',
                'upload_max'     => ini_get('upload_max_filesize'),
                'post_max'       => ini_get('post_max_size'),
                'max_execution'  => ini_get('max_execution_time'),
                'php_version'    => PHP_VERSION,
                'wp_version'     => get_bloginfo('version'),
            ]);
        },
        'permission_callback' => function() { return current_user_can('manage_options'); },
    ]);

    // Upload + parse CSV — returns job_id
    register_rest_route( $ns, '/import/upload', [
        'methods'             => 'POST',
        'callback'            => 'ss_rest_import_upload',
        'permission_callback' => function() { return current_user_can('manage_options'); },
    ]);

    // Process one batch
    register_rest_route( $ns, '/import/batch', [
        'methods'             => 'POST',
        'callback'            => 'ss_rest_import_batch',
        'permission_callback' => function() { return current_user_can('manage_options'); },
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// UPLOAD: receive CSV file, parse, store job in transient, return job_id
// ─────────────────────────────────────────────────────────────────────────────
function ss_rest_import_upload( WP_REST_Request $request ) {
    $dry_run       = filter_var( $request->get_param('dry_run'),       FILTER_VALIDATE_BOOLEAN );
    $skip_existing = filter_var( $request->get_param('skip_existing'), FILTER_VALIDATE_BOOLEAN );

    // Get CSV from file upload or raw body
    $csv_data = '';
    $files    = $request->get_file_params();

    if ( ! empty($files['csv_file']['tmp_name']) && is_uploaded_file($files['csv_file']['tmp_name']) ) {
        $csv_data = file_get_contents( $files['csv_file']['tmp_name'] );
    }

    if ( ! $csv_data ) {
        // Fallback: try raw POST body (sent as text/plain)
        $csv_data = $request->get_body();
    }

    if ( ! $csv_data ) {
        return new WP_Error('no_data', 'No CSV data received.', ['status' => 400]);
    }

    $parsed = ss_parse_series_engine_csv( $csv_data );
    if ( is_wp_error($parsed) ) return $parsed;

    $job_id = 'ss_import_' . wp_generate_password(12, false);
    set_transient( $job_id, [
        'parsed'        => $parsed,
        'dry_run'       => $dry_run,
        'skip_existing' => $skip_existing,
        'offset'        => 0,
        'topic_map'     => [],
        'speaker_map'   => [],
        'series_map'    => [],
        'sermon_map'    => [],
        'category_map'  => [],
    ], 15 * MINUTE_IN_SECONDS );

    return rest_ensure_response([
        'job_id' => $job_id,
        'totals' => [
            'series'   => count($parsed['series']  ?? []),
            'messages' => count($parsed['message'] ?? []),
            'topics'   => count($parsed['topic']   ?? []),
            'speakers' => count($parsed['speaker'] ?? []),
        ],
        'dry_run' => $dry_run,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// BATCH: process next 25 sermons from the stored job
// ─────────────────────────────────────────────────────────────────────────────
define( 'SS_IMPORT_BATCH_SIZE', 25 );

function ss_rest_import_batch( WP_REST_Request $request ) {
    $job_id = sanitize_text_field( $request->get_param('job_id') );
    if ( ! $job_id ) return new WP_Error('missing_job', 'Missing job_id.', ['status'=>400]);

    $job = get_transient($job_id);
    if ( ! $job ) return new WP_Error('expired', 'Import session expired — please re-upload the CSV.', ['status'=>410]);

    $parsed        = $job['parsed'];
    $dry_run       = $job['dry_run'];
    $skip_existing = $job['skip_existing'];
    $offset        = (int)$job['offset'];
    $topic_map     = $job['topic_map'];
    $speaker_map   = $job['speaker_map'];
    $series_map    = $job['series_map'];

    $log      = [];
    $counters = ['series'=>0,'sermons'=>0,'topics'=>0,'speakers'=>0,'skipped'=>0,'errors'=>0];

    // First batch: build lookup maps + import taxonomies + series
    if ( $offset === 0 ) {
        $file_map      = [];
        $scripture_map = [];
        $msg_files     = [];
        $msg_topics    = [];
        $msg_speakers  = [];
        $msg_scripture = [];

        foreach ( ($parsed['file']      ?? []) as $r ) {
            $url = trim($r[3]??'');
            if (!$url || strpos($url,'seriesengine.com')!==false) continue;
            $type = 'link';
            if (strpos($url,'.pdf')!==false) $type='pdf';
            elseif (strpos($url,'sermonsend')!==false) $type='devotional';
            $file_map[trim($r[1]??'')] = ['label'=>trim($r[2]??''),'url'=>$url,'type'=>$type];
        }
        foreach ( ($parsed['scripture'] ?? []) as $r ) { if (trim($r[2]??'')) $scripture_map[trim($r[1]??'')] = trim($r[2]); }
        foreach ( ($parsed['mfm']       ?? []) as $r ) { $msg_files[trim($r[1]??'')][]     = trim($r[2]??''); }
        foreach ( ($parsed['mtm']       ?? []) as $r ) { $msg_topics[trim($r[1]??'')][]    = trim($r[2]??''); }
        foreach ( ($parsed['msp']       ?? []) as $r ) { $msg_speakers[trim($r[1]??'')][]  = trim($r[2]??''); }
        foreach ( ($parsed['scm']       ?? []) as $r ) { $msg_scripture[trim($r[1]??'')][] = trim($r[2]??''); }

        $job['file_map']=$file_map; $job['scripture_map']=$scripture_map;
        $job['msg_files']=$msg_files; $job['msg_topics']=$msg_topics;
        $job['msg_speakers']=$msg_speakers; $job['msg_scripture']=$msg_scripture;

        // Topics
        foreach ( ($parsed['topic']??[]) as $row ) {
            $se_id=trim($row[1]??''); $name=trim($row[2]??'');
            if (!$name||$name==='Demonstration') continue;
            if (!$dry_run) {
                $ex=get_term_by('name',$name,'ss_topic');
                $tid = $ex ? $ex->term_id : (($t=wp_insert_term($name,'ss_topic'))&&!is_wp_error($t)?$t['term_id']:0);
                if ($tid&&!$ex) $counters['topics']++;
            } else { $tid=0; $counters['topics']++; }
            $topic_map[$se_id]=$tid;
        }
        $log[] = '✅ Topics: '.count($topic_map);

        // ── Series Categories (seriestype records) ──────────────────────────
        $category_map = $job['category_map'] ?? [];
        foreach ( ($parsed['seriestype']??[]) as $row ) {
            $se_id = trim($row[1]??'');
            $name  = trim($row[2]??'');
            $desc  = trim($row[3]??'');
            if (!$name) continue;
            if (!$dry_run) {
                $ex = get_term_by('name', $name, 'ss_series_category');
                if ($ex) {
                    $term_id = $ex->term_id;
                } else {
                    $t = wp_insert_term($name, 'ss_series_category', ['description'=>$desc]);
                    $term_id = is_wp_error($t) ? 0 : $t['term_id'];
                }
                $category_map[$se_id] = $term_id;
            } else {
                $category_map[$se_id] = 0;
                $log[] = "→ [DRY] Category: $name";
            }
        }
        $job['category_map'] = $category_map;
        $log[] = '✅ Categories: '.count($category_map);

        // Speakers
        foreach ( ($parsed['speaker']??[]) as $row ) {
            $se_id=trim($row[1]??''); $name=trim(trim($row[2]??'').' '.trim($row[3]??''));
            if (!$name) continue;
            if (!$dry_run) {
                $ex=get_term_by('name',$name,'ss_speaker');
                $tid = $ex ? $ex->term_id : (($t=wp_insert_term($name,'ss_speaker'))&&!is_wp_error($t)?$t['term_id']:0);
                if ($tid&&!$ex) $counters['speakers']++;
            } else { $tid=0; $counters['speakers']++; }
            $speaker_map[$se_id]=$tid;
        }
        $log[] = '✅ Speakers: '.count($speaker_map);

        // Series
        foreach ( ($parsed['series']??[]) as $row ) {
            $se_id=trim($row[1]??''); $title=trim($row[2]??'');
            $desc=trim($row[3]??''); $img_lg=trim($row[4]??'');
            $start=trim($row[6]??''); $img_sm=trim($row[7]??'');
            if (!$title||$title==='Demo Series') continue;
            if (strpos($img_lg,'seriesengine.com')!==false) $img_lg='';
            if (strpos($img_sm,'seriesengine.com')!==false) $img_sm='';
            if ($dry_run) { $series_map[$se_id]=0; $counters['series']++; $log[]="→ [DRY] Series: $title"; continue; }
            if ($skip_existing) {
                $ex=get_posts(['post_type'=>'ss_series','title'=>$title,'posts_per_page'=>1,'post_status'=>'any']);
                if (!empty($ex)) { $series_map[$se_id]=$ex[0]->ID; $counters['skipped']++; continue; }
            }
            $pid=wp_insert_post(['post_type'=>'ss_series','post_title'=>$title,'post_content'=>wp_kses_post($desc),'post_status'=>'publish']);
            if (is_wp_error($pid)) { $log[]="❌ Series: $title"; $counters['errors']++; continue; }
            update_post_meta($pid,'_ss_series_start_date',$start);
            if ($img_sm) update_post_meta($pid,'_ss_series_image_sm',$img_sm);
            if ($img_lg) update_post_meta($pid,'_ss_series_image_lg',$img_lg);
            // Assign category from stm mapping
            $stm_map      = $job['stm_map']     ?? [];
            $category_map = $job['category_map'] ?? [];
            $type_id      = $stm_map[$se_id] ?? '';
            if ($type_id && isset($category_map[$type_id]) && $category_map[$type_id]) {
                wp_set_post_terms($pid, [(int)$category_map[$type_id]], 'ss_series_category');
            }

            $series_map[$se_id]=$pid; $counters['series']++;
            $log[]="✅ Series: $title";
        }

        // Build series-to-category map from stm rows: [stm, row_id, series_id, type_id]
        $stm_map = [];
        foreach ( ($parsed['stm']??[]) as $r ) {
            $stm_map[trim($r[2]??'')] = trim($r[3]??'');
        }
        $job['stm_map'] = $stm_map;

        $job['topic_map']=$topic_map; $job['speaker_map']=$speaker_map; $job['series_map']=$series_map;
    }

    $file_map      = $job['file_map']      ?? [];
    $scripture_map = $job['scripture_map'] ?? [];
    $msg_files     = $job['msg_files']     ?? [];
    $msg_topics    = $job['msg_topics']    ?? [];
    $msg_speakers  = $job['msg_speakers']  ?? [];
    $msg_scripture = $job['msg_scripture'] ?? [];

    // Sermons batch
    $all_msgs = $parsed['message'] ?? [];
    $batch    = array_slice($all_msgs, $offset, SS_IMPORT_BATCH_SIZE);
    $total    = count($all_msgs);

    foreach ( $batch as $row ) {
        $se_id=$row[1]??''; $title=trim($row[2]??'');
        $spk_str=trim($row[3]??''); $date=trim($row[4]??'');
        $desc=trim($row[6]??''); $yt_raw=trim($row[12]??'');
        $se_series=trim($row[23]??''); $scripture=trim($row[31]??'');
        if ($date==='0000-00-00') $date='';
        if (!$title) continue;

        $yt_id='';
        foreach (array_merge([$yt_raw],$row) as $cell) {
            if (!$yt_id && strpos($cell,'youtu')!==false) {
                if (preg_match('/embed\/([a-zA-Z0-9_\-]{11})/',$cell,$m))              { $yt_id=$m[1]; break; }
                if (preg_match('/youtu\.be\/([a-zA-Z0-9_\-]{11})/',$cell,$m))         { $yt_id=$m[1]; break; }
                if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_\-]{11})/',$cell,$m)){ $yt_id=$m[1]; break; }
            }
        }

        if ($dry_run) { $counters['sermons']++; $log[]="→ [DRY] $title ($date)"; continue; }
        if ($skip_existing) {
            $ex=get_posts(['post_type'=>'ss_sermon','title'=>$title,'posts_per_page'=>1,'post_status'=>'any']);
            if (!empty($ex)) { $counters['skipped']++; continue; }
        }

        $pid=wp_insert_post(['post_type'=>'ss_sermon','post_title'=>$title,'post_content'=>wp_kses_post($desc),'post_status'=>'publish','post_date'=>$date?$date.' 00:00:00':current_time('mysql')]);
        if (is_wp_error($pid)) { $log[]="❌ $title"; $counters['errors']++; continue; }

        if ($yt_id) update_post_meta($pid,'_ss_youtube_id',$yt_id);
        if ($date)  update_post_meta($pid,'_ss_sermon_date',$date);
        $wp_sid=$series_map[$se_series]??0;
        if ($wp_sid) update_post_meta($pid,'_ss_series_id',$wp_sid);

        if (!$scripture && isset($msg_scripture[$se_id])) {
            foreach ($msg_scripture[$se_id] as $sid) { if (isset($scripture_map[$sid])) { $scripture=$scripture_map[$sid]; break; } }
        }
        if ($scripture) {
            update_post_meta($pid,'_ss_scripture_ref',$scripture);
            $ver=get_option('sermon_suite_bible_version','NIV');
            update_post_meta($pid,'_ss_scripture_url','https://www.biblegateway.com/passage/?search='.urlencode($scripture).'&version='.$ver);
            $book=preg_replace('/\s*\d.*/','',$scripture);
            if ($book) wp_set_post_terms($pid,[$book],'ss_scripture_book',true);
        }

        if (isset($msg_topics[$se_id])) {
            $tids=array_filter(array_map(fn($t)=>(int)($topic_map[$t]??0),$msg_topics[$se_id]));
            if ($tids) wp_set_post_terms($pid,array_values($tids),'ss_topic');
        }

        $sp_terms=[];
        if (isset($msg_speakers[$se_id])) {
            foreach ($msg_speakers[$se_id] as $sid) { if (!empty($speaker_map[$sid])) $sp_terms[]=(int)$speaker_map[$sid]; }
        }
        if (!$sp_terms && $spk_str) {
            $t=get_term_by('name',$spk_str,'ss_speaker');
            $sp_terms[]=$t?$t->term_id:(($ins=wp_insert_term($spk_str,'ss_speaker'))&&!is_wp_error($ins)?$ins['term_id']:null);
        }
        if ($sp_terms=array_filter($sp_terms)) wp_set_post_terms($pid,$sp_terms,'ss_speaker');

        $resources=[];
        if (isset($msg_files[$se_id])) { foreach ($msg_files[$se_id] as $fid) { if (isset($file_map[$fid])) $resources[]=$file_map[$fid]; } }
        if ($resources) update_post_meta($pid,'_ss_resources',$resources);

        $counters['sermons']++;
        $log[]='✅ '.$title.($yt_id?" [YT:$yt_id]":'');
    }

    $new_offset = $offset + SS_IMPORT_BATCH_SIZE;
    $done       = $new_offset >= $total;

    $job['offset']      = $new_offset;
    $job['series_map']  = $series_map;
    $job['topic_map']   = $topic_map;
    $job['speaker_map'] = $speaker_map;

    if ($done) {
        // Series ordering pass
        if (!$dry_run) {
            foreach ($series_map as $wp_sid) {
                if (!$wp_sid) continue;
                $ss=get_posts(['post_type'=>'ss_sermon','posts_per_page'=>-1,'meta_query'=>[['key'=>'_ss_series_id','value'=>$wp_sid]],'orderby'=>'date','order'=>'ASC','post_status'=>'any']);
                foreach ($ss as $i=>$s) { if (!get_post_meta($s->ID,'_ss_series_order',true)) update_post_meta($s->ID,'_ss_series_order',$i+1); }
            }
        }
        delete_transient($job_id);
    } else {
        set_transient($job_id, $job, 15*MINUTE_IN_SECONDS);
    }

    return rest_ensure_response([
        'log'      => $log,
        'counters' => $counters,
        'offset'   => $new_offset,
        'total'    => $total,
        'done'     => $done,
        'dry_run'  => $dry_run,
        'progress' => min(100, (int)(($new_offset/max($total,1))*100)),
    ]);
}

// ── CSV parser (pure, no DB writes) ───────────────────────────────────────────
function ss_parse_series_engine_csv( $csv_data ) {
    $rows=[]; $h=fopen('php://memory','r+'); fwrite($h,$csv_data); rewind($h);
    while (($row=fgetcsv($h))!==false) {
        if (!isset($row[0])||!trim($row[0])) continue;
        $rows[strtolower(trim($row[0]))][]=$row;
    }
    fclose($h);
    if (empty($rows)) return new WP_Error('parse_error','Could not parse any rows from the CSV.');
    return $rows;
}

// ── Admin import page ──────────────────────────────────────────────────────────
function sermon_suite_import_page() {
    ?>
    <div class="wrap gcc-admin-wrap">
        <h1>Import from Series Engine</h1>
        <p>Export your data from <strong>Series Engine → Tools → Export</strong>, then upload the CSV here.</p>

        <div class="gcc-import-options">
            <label><input type="checkbox" id="gcc-dry-run" checked /> <strong>Dry Run</strong> — preview without saving</label>
            <label style="margin-left:20px;"><input type="checkbox" id="gcc-skip-existing" checked /> <strong>Skip existing</strong> — don't re-import records already present</label>
        </div>

        <div style="margin:20px 0;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <input type="file" id="gcc-import-file" accept=".csv" />
            <button id="gcc-start-import" class="button button-primary">Start Import</button>
            <button id="gcc-test-connection" class="button">🔌 Test Connection</button>
            <span id="gcc-ping-result" style="font-size:0.85rem;font-style:italic;"></span>
        </div>

        <div id="gcc-import-progress" style="display:none;max-width:600px;">
            <div style="background:#e2e2e2;border-radius:4px;height:10px;margin:12px 0 6px;">
                <div id="gcc-progress-bar" style="background:#2563eb;height:100%;border-radius:4px;width:0;transition:width 0.3s;"></div>
            </div>
            <p id="gcc-progress-text" style="font-style:italic;color:#555;font-size:0.88rem;"></p>
        </div>

        <div id="gcc-import-results" style="display:none;">
            <h2 id="gcc-import-headline"></h2>
            <div id="gcc-import-summary" style="margin-bottom:12px;font-weight:600;"></div>
            <ul id="gcc-import-log" style="max-height:400px;overflow-y:auto;font-family:monospace;font-size:0.82rem;background:#f8f8f8;border:1px solid #ddd;border-radius:4px;padding:12px 16px;list-style:none;margin:0;"></ul>
        </div>
    </div>

    <script>
    // Safety net: define sermonSuiteAdmin inline in case the enqueue missed this page
    if (typeof sermonSuiteAdmin === 'undefined') {
        sermonSuiteAdmin = {
            ajaxUrl:    '<?php echo esc_js(admin_url("admin-ajax.php")); ?>',
            restUrl:    '<?php echo esc_js(rest_url("sermon-suite/v1/")); ?>',
            nonce:      '<?php echo esc_js(wp_create_nonce("sermon_suite_admin")); ?>',
            restNonce:  '<?php echo esc_js(wp_create_nonce("wp_rest")); ?>',
        };
    }
    jQuery(function($){
        var REST    = sermonSuiteAdmin.restUrl;   // e.g. https://site.com/wp-json/sermon-suite/v1/
        var NONCE   = sermonSuiteAdmin.restNonce; // wp_rest nonce
        var totalMessages = 0, jobId = null;
        var allLog = [], grandCounters = {series:0,sermons:0,topics:0,speakers:0,skipped:0,errors:0};

        function restHeaders() {
            return { 'X-WP-Nonce': NONCE };
        }

        // ── Test Connection ─────────────────────────────────────────────
        $('#gcc-test-connection').on('click', function(){
            $(this).prop('disabled',true).text('Testing…');
            $('#gcc-ping-result').text('').css('color','');
            $.ajax({
                url: REST + 'import/ping',
                method: 'GET',
                headers: restHeaders(),
                timeout: 15000,
                success: function(res){
                    $('#gcc-test-connection').prop('disabled',false).text('🔌 Test Connection');
                    $('#gcc-ping-result').html(
                        '✅ Connected &nbsp;·&nbsp; upload_max: <strong>'+res.upload_max+'</strong>' +
                        ' &nbsp;·&nbsp; post_max: <strong>'+res.post_max+'</strong>' +
                        ' &nbsp;·&nbsp; PHP '+res.php_version
                    ).css('color','#155724');
                },
                error: function(xhr, status){
                    $('#gcc-test-connection').prop('disabled',false).text('🔌 Test Connection');
                    var msg = status==='timeout'
                        ? 'Timed out — REST API may be disabled.'
                        : 'HTTP '+xhr.status+' — '+xhr.responseText.substring(0,200);
                    $('#gcc-ping-result').text('❌ '+msg).css('color','#991b1b');
                }
            });
        });

        // ── Start Import ────────────────────────────────────────────────
        $('#gcc-start-import').on('click', function(){
            var file = document.getElementById('gcc-import-file').files[0];
            if (!file) { alert('Please select a CSV file first.'); return; }

            allLog=[]; grandCounters={series:0,sermons:0,topics:0,speakers:0,skipped:0,errors:0};
            $('#gcc-progress-bar').css('width','0');
            $('#gcc-progress-text').text('Uploading file…');
            $('#gcc-import-progress').show();
            $('#gcc-import-results').hide();
            $('#gcc-import-log').empty();
            $('#gcc-start-import').prop('disabled',true);

            var dryRun    = $('#gcc-dry-run').is(':checked')      ? 'true':'false';
            var skipExist = $('#gcc-skip-existing').is(':checked') ? 'true':'false';

            var fd = new FormData();
            fd.append('csv_file',      file);
            fd.append('dry_run',       dryRun);
            fd.append('skip_existing', skipExist);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', REST + 'import/upload', true);
            xhr.setRequestHeader('X-WP-Nonce', NONCE);

            // Real upload progress
            xhr.upload.onprogress = function(e){
                if (e.lengthComputable) {
                    var pct = Math.round((e.loaded/e.total)*40);
                    $('#gcc-progress-bar').css('width', pct+'%');
                    $('#gcc-progress-text').text('Uploading… '+pct+'%');
                }
            };

            xhr.onload = function(){
                if (xhr.status === 200 || xhr.status === 201) {
                    var res;
                    try { res = JSON.parse(xhr.responseText); } catch(e) {
                        showError('Server returned non-JSON: '+xhr.responseText.substring(0,300));
                        return;
                    }
                    jobId         = res.job_id;
                    totalMessages = res.totals.messages;
                    $('#gcc-progress-bar').css('width','45%');
                    $('#gcc-progress-text').text(
                        'Parsed '+res.totals.series+' series, '+totalMessages+' sermons, '+
                        res.totals.topics+' topics. Importing…'
                    );
                    runBatch(0);
                } else if (xhr.status === 401 || xhr.status === 403) {
                    showError('Permission denied (HTTP '+xhr.status+'). Make sure you are logged in as an administrator.');
                } else {
                    showError('Upload failed (HTTP '+xhr.status+'): '+xhr.responseText.substring(0,300));
                }
            };
            xhr.onerror = function(){
                showError('Network error — could not reach the server. Check your internet connection and that the REST API is enabled.');
            };
            xhr.ontimeout = function(){ showError('Upload timed out.'); };
            xhr.timeout = 60000;
            xhr.send(fd);
        });

        // ── Batch processing ────────────────────────────────────────────
        function runBatch(offset) {
            $('#gcc-progress-text').text(
                'Importing '+offset+'–'+Math.min(offset+25,totalMessages)+' of '+totalMessages+'…'
            );
            $.ajax({
                url: REST + 'import/batch',
                method: 'POST',
                headers: restHeaders(),
                contentType: 'application/json',
                data: JSON.stringify({ job_id: jobId }),
                timeout: 90000,
                success: function(res){
                    $.each(res.counters, function(k,v){ grandCounters[k]=(grandCounters[k]||0)+v; });
                    allLog = allLog.concat(res.log);
                    var pct = 45 + Math.round(res.progress * 0.55);
                    $('#gcc-progress-bar').css('width', pct+'%');
                    if (res.done) {
                        $('#gcc-progress-bar').css('width','100%');
                        $('#gcc-progress-text').text('Done!');
                        showResults(res.dry_run);
                    } else {
                        runBatch(res.offset);
                    }
                },
                error: function(xhr, status){
                    var msg = status==='timeout'
                        ? 'A batch timed out. Re-run with "Skip existing" checked to resume.'
                        : 'Batch error (HTTP '+xhr.status+'): '+xhr.responseText.substring(0,200);
                    showError(msg);
                }
            });
        }

        function showResults(dryRun) {
            $('#gcc-start-import').prop('disabled',false);
            $('#gcc-import-results').show();
            $('#gcc-import-headline').text(dryRun ? '✅ Dry run complete' : '✅ Import complete!');
            var s = grandCounters.series+' series, '+grandCounters.sermons+' sermons, '+
                    grandCounters.topics+' topics, '+grandCounters.speakers+' speakers. '+
                    grandCounters.skipped+' skipped. '+grandCounters.errors+' errors.';
            if (!dryRun && grandCounters.sermons>0) s += ' <a href="edit.php?post_type=ss_sermon">View sermons →</a>';
            $('#gcc-import-summary').html(s);
            var $log=$('#gcc-import-log');
            allLog.forEach(function(l){ $log.append('<li>'+l+'</li>'); });
            if (dryRun) {
                $('<div class="notice notice-info" style="margin-bottom:12px;"><p>Uncheck <strong>Dry Run</strong> and import again to save.</p></div>').insertBefore($log);
            }
        }

        function showError(msg) {
            $('#gcc-start-import').prop('disabled',false);
            $('#gcc-import-progress').hide();
            $('#gcc-import-results').show();
            $('#gcc-import-headline').text('⚠️ Import error');
            $('#gcc-import-summary').html('<span style="color:#991b1b;">'+msg+'</span>');
        }
    });
    </script>
    <?php
}
