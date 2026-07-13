<?php
/**
 * Sermon Suite — Sermon Shots integration.
 *
 * API client + admin-only REST proxy. The Sermon Shots API key is stored
 * server-side (option: sermon_suite_shots_api_key) and is never sent to
 * the browser; the editor talks to these proxy endpoints instead.
 *
 * API contract (confirmed against Sermon Shots' own WordPress plugin):
 *   Base:  https://api.sermonshots.com/api/v1
 *   Auth:  auth-token: {key} header
 *   GET  /videos                          — the account's video library
 *   GET  /video/{id}/summary              — AI summary
 *   GET  /video/{id}/discussion-guide     — discussion guide
 *   GET  /video/{id}/devotionals          — devotionals
 *   GET  /video/{id}/quotes               — pull quotes
 *   GET  /video/{id}/transcription        — full transcript
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Sermon_Suite_Shots_API {

    const OPT_KEY  = 'sermon_suite_shots_api_key';
    const BASE_URL = 'https://api.sermonshots.com/api/v1';

    /** Types we can import, mapped to their endpoints. */
    const TYPES = [
        'summary'          => '/video/%s/summary',
        'discussion-guide' => '/video/%s/discussion-guide',
        'devotionals'      => '/video/%s/devotionals',
        'quotes'           => '/video/%s/quotes',
        'transcription'    => '/video/%s/transcription',
    ];

    /* ── HTTP ─────────────────────────────────────────────────── */

    public static function get_key() {
        return trim( (string) get_option( self::OPT_KEY, '' ) );
    }

    public static function has_key() {
        return self::get_key() !== '';
    }

    private static function request( $path, $query = [] ) {
        $key = self::get_key();
        if ( ! $key ) {
            return new WP_Error( 'shots_no_key', 'No Sermon Shots API key saved. Add one under Sermons → Settings.' );
        }

        $url = self::BASE_URL . $path;
        if ( $query ) $url = add_query_arg( $query, $url );

        $resp = wp_remote_get( $url, [
            'headers' => [ 'auth-token' => $key, 'accept' => 'application/json' ],
            'timeout' => 25,
        ] );
        if ( is_wp_error( $resp ) ) return $resp;

        $code = wp_remote_retrieve_response_code( $resp );
        $body = wp_remote_retrieve_body( $resp );
        $json = json_decode( $body, true );

        if ( $code === 401 || $code === 403 ) {
            return new WP_Error( 'shots_auth', 'Sermon Shots rejected the API key (HTTP ' . $code . '). Check the key under Sermons → Settings.' );
        }
        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'shots_http', 'Sermon Shots API error (HTTP ' . $code . ').', [ 'body' => $body ] );
        }
        return ( json_last_error() === JSON_ERROR_NONE ) ? $json : $body;
    }

    /* ── Videos list ──────────────────────────────────────────── */

    /**
     * List the account's videos, normalized to [{id, title}].
     *
     * The /videos endpoint's behavior varies (pagination params, wrapper
     * keys, nesting), so this tries several request variants and mines the
     * response deeply for the first plausible list of videos — the same
     * strategy Sermon Shots' own plugin uses.
     */
    public static function list_videos( $force = false ) {
        $cache_key = 'ss_shots_videos_' . substr( md5( self::get_key() ), 0, 8 );
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) && ! empty( $cached ) ) return $cached;
        }

        $attempts = [
            [ '/videos',      [] ],
            [ '/videos',      [ 'take' => 100 ] ],
            [ '/videos',      [ 'limit' => 100, 'page' => 1 ] ],
            [ '/videos',      [ 'per_page' => 100 ] ],
            [ '/videos/list', [] ],
        ];

        $last_error = null;
        self::$last_raw_sample = '';

        foreach ( $attempts as $a ) {
            $raw = self::request( $a[0], $a[1] );
            if ( is_wp_error( $raw ) ) { $last_error = $raw; continue; }

            // Keep a small sample of the first successful response for diagnostics.
            if ( self::$last_raw_sample === '' ) {
                $enc = wp_json_encode( $raw );
                self::$last_raw_sample = is_string( $enc ) ? substr( $enc, 0, 600 ) : '';
            }

            $out = self::extract_videos( $raw );
            if ( ! empty( $out ) ) {
                set_transient( $cache_key, $out, 10 * MINUTE_IN_SECONDS );
                return $out;
            }
        }

        if ( $last_error && self::$last_raw_sample === '' ) return $last_error;
        // Genuinely empty (or unparseable): cache only briefly so fixes and
        // newly-added videos show up fast.
        set_transient( $cache_key, [], MINUTE_IN_SECONDS );
        return [];
    }

    /** Raw sample of the last /videos response, for diagnostics. */
    public static $last_raw_sample = '';

    /**
     * Deep-mine any response structure for the first list that looks like
     * videos (arrays containing an id-ish key).
     */
    private static function extract_videos( $raw ) {
        $candidates = [];

        // Direct list at the top?
        if ( is_array( $raw ) && isset( $raw[0] ) && is_array( $raw[0] ) ) {
            $candidates[] = $raw;
        }

        // Walk nested wrappers up to 4 levels collecting every list of arrays.
        $walk = function ( $node, $depth ) use ( &$walk, &$candidates ) {
            if ( $depth > 4 || ! is_array( $node ) ) return;
            $is_list = ( $node === [] || array_keys( $node ) === range( 0, count( $node ) - 1 ) );
            if ( $is_list && isset( $node[0] ) && is_array( $node[0] ) ) {
                $candidates[] = $node;
            }
            foreach ( $node as $v ) $walk( $v, $depth + 1 );
        };
        $walk( $raw, 0 );

        foreach ( $candidates as $list ) {
            $out = [];
            foreach ( $list as $v ) {
                if ( ! is_array( $v ) ) continue;
                $id = $v['id'] ?? $v['_id'] ?? $v['videoId'] ?? $v['uuid'] ?? $v['video_id'] ?? null;
                if ( ! $id ) continue;
                $title = $v['title'] ?? $v['name'] ?? $v['filename'] ?? ( 'Video ' . substr( (string) $id, 0, 8 ) );
                $out[] = [ 'id' => (string) $id, 'title' => (string) $title ];
            }
            if ( ! empty( $out ) ) return $out;
        }
        return [];
    }

    /* ── Content fetch + normalization ────────────────────────── */

    /**
     * Fetch one or more content types for a video, normalized to clean
     * HTML strings ready for the editor fields.
     *
     * @return array|WP_Error [ type => html, ..., '_errors' => [type => msg] ]
     */
    public static function get_content( $video_id, array $types ) {
        $video_id = sanitize_text_field( $video_id );
        if ( ! $video_id ) return new WP_Error( 'shots_bad_req', 'Missing video id.' );

        $out    = [];
        $errors = [];
        foreach ( $types as $type ) {
            if ( ! isset( self::TYPES[ $type ] ) ) continue;
            $raw = self::request( sprintf( self::TYPES[ $type ], rawurlencode( $video_id ) ) );
            if ( is_wp_error( $raw ) ) {
                $errors[ $type ] = $raw->get_error_message();
                continue;
            }
            $html = self::normalize_to_html( $raw, $type );
            if ( $html !== '' ) $out[ $type ] = $html;
            else $errors[ $type ] = 'No content available yet for this video.';
        }
        if ( $errors ) $out['_errors'] = $errors;
        return $out;
    }

    /**
     * Reduce whatever shape the API returns (string, {content}, {data:{text}},
     * arrays of items, or a string containing markdown-fenced JSON) to clean
     * paragraph HTML.
     */
    public static function normalize_to_html( $raw, $type = '' ) {
        $raw = self::decode_embedded_json( $raw );

        // Summaries come back as a set of variants (Short / Long / YouTube /
        // Social Media Teaser). The Description field is for archive cards,
        // so prefer the Short variant, falling back to Long.
        if ( $type === 'summary' && is_array( $raw ) ) {
            $variant = self::pick_variant( $raw, [ 'short', 'summary', 'long' ] );
            if ( $variant !== '' ) $raw = $variant;
        }

        $texts = [];
        self::collect_texts( $raw, $texts );
        $texts = array_values( array_filter( array_map( 'trim', $texts ) ) );
        if ( ! $texts ) return '';

        $html = '';
        foreach ( $texts as $t ) {
            // The API sometimes returns literal "\n" sequences.
            $t = strtr( $t, [ '\r\n' => "\n", '\n' => "\n" ] );
            $html .= wpautop( $t );
        }
        return trim( wp_kses_post( $html ) );
    }

    /**
     * If a value is a string containing JSON (optionally wrapped in
     * markdown ``` fences), decode it. Applied recursively one level deep.
     */
    private static function decode_embedded_json( $raw ) {
        if ( is_string( $raw ) ) {
            $s = trim( $raw );
            // Strip markdown code fences: ```json ... ``` or ``` ... ```
            if ( strpos( $s, '```' ) === 0 ) {
                $s = preg_replace( '/^```[a-zA-Z]*\s*/', '', $s );
                $s = preg_replace( '/```\s*$/', '', $s );
                $s = trim( $s );
            }
            if ( $s !== '' && ( $s[0] === '{' || $s[0] === '[' ) ) {
                $decoded = json_decode( $s, true );
                if ( json_last_error() === JSON_ERROR_NONE ) return $decoded;
            }
            return $raw;
        }
        if ( is_array( $raw ) ) {
            foreach ( $raw as $k => $v ) {
                if ( is_string( $v ) && strlen( $v ) > 20 ) {
                    $raw[ $k ] = self::decode_embedded_json( $v );
                }
            }
        }
        return $raw;
    }

    /**
     * From an array of named variants, return the first matching key
     * (case-insensitive, ignoring spaces), searching one level of nesting.
     */
    private static function pick_variant( array $arr, array $preferred ) {
        $flat = [];
        foreach ( $arr as $k => $v ) {
            if ( is_string( $k ) ) $flat[ strtolower( str_replace( ' ', '', $k ) ) ] = $v;
            if ( is_array( $v ) ) {
                foreach ( $v as $k2 => $v2 ) {
                    if ( is_string( $k2 ) && ! isset( $flat[ strtolower( str_replace( ' ', '', $k2 ) ) ] ) ) {
                        $flat[ strtolower( str_replace( ' ', '', $k2 ) ) ] = $v2;
                    }
                }
            }
        }
        foreach ( $preferred as $want ) {
            if ( isset( $flat[ $want ] ) && is_string( $flat[ $want ] ) && trim( $flat[ $want ] ) !== '' ) {
                return trim( $flat[ $want ] );
            }
        }
        return '';
    }

    /** Recursively collect text values from common content keys. */
    private static function collect_texts( $node, array &$texts, $depth = 0 ) {
        if ( $depth > 6 ) return;
        if ( is_string( $node ) ) {
            // Ignore bare URLs / ids — we want prose.
            if ( strlen( $node ) > 2 && ! preg_match( '#^https?://\S+$#i', $node ) ) {
                $texts[] = $node;
            }
            return;
        }
        if ( ! is_array( $node ) ) return;

        // Prefer explicit content keys when present.
        $content_keys = [ 'content', 'text', 'body', 'value', 'description', 'transcript', 'transcription' ];
        $found_key = false;
        foreach ( $content_keys as $k ) {
            if ( isset( $node[ $k ] ) ) {
                $found_key = true;
                self::collect_texts( $node[ $k ], $texts, $depth + 1 );
            }
        }
        if ( $found_key ) return;

        // Otherwise recurse through lists/wrappers.
        foreach ( [ 'data', 'result', 'items', 'results', 'list', 'quotes', 'devotionals' ] as $k ) {
            if ( isset( $node[ $k ] ) ) {
                self::collect_texts( $node[ $k ], $texts, $depth + 1 );
                return;
            }
        }
        // Plain list — walk every element. (PHP 7.4-safe list check.)
        if ( $node === [] || array_keys( $node ) === range( 0, count( $node ) - 1 ) ) {
            foreach ( $node as $v ) self::collect_texts( $v, $texts, $depth + 1 );
        }
    }

    /** Quick connection test: can we list videos? */
    public static function test() {
        $videos = self::list_videos( true );
        if ( is_wp_error( $videos ) ) return $videos;
        $out = [ 'ok' => true, 'video_count' => count( $videos ) ];
        if ( count( $videos ) === 0 && self::$last_raw_sample !== '' ) {
            // Admin-only diagnostic: what did the API actually return?
            $out['raw_sample'] = self::$last_raw_sample;
        }
        return $out;
    }
}

/* ── Admin-only REST proxy ──────────────────────────────────────── */

add_action( 'rest_api_init', function () {
    $ns = 'sermon-suite/v1';

    register_rest_route( $ns, '/shots/videos', [
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
        'callback'            => function ( WP_REST_Request $req ) {
            $videos = Sermon_Suite_Shots_API::list_videos( $req->get_param( 'flush' ) === '1' );
            if ( is_wp_error( $videos ) ) {
                return new WP_Error( $videos->get_error_code(), $videos->get_error_message(), [ 'status' => 400 ] );
            }
            return rest_ensure_response( $videos );
        },
    ] );

    register_rest_route( $ns, '/shots/content', [
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
        'args'                => [
            'video_id' => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'types'    => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
        ],
        'callback'            => function ( WP_REST_Request $req ) {
            $types = array_filter( array_map( 'trim', explode( ',', $req['types'] ?: 'summary,discussion-guide,transcription' ) ) );
            $out   = Sermon_Suite_Shots_API::get_content( $req['video_id'], $types );
            if ( is_wp_error( $out ) ) {
                return new WP_Error( $out->get_error_code(), $out->get_error_message(), [ 'status' => 400 ] );
            }
            return rest_ensure_response( $out );
        },
    ] );

    register_rest_route( $ns, '/shots/test', [
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        'callback'            => function () {
            $r = Sermon_Suite_Shots_API::test();
            if ( is_wp_error( $r ) ) {
                return new WP_Error( $r->get_error_code(), $r->get_error_message(), [ 'status' => 400 ] );
            }
            return rest_ensure_response( $r );
        },
    ] );
} );
