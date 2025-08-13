<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LLMBPM_Core {

    public static function init() {
        // When an update or comment is posted
        add_action( 'bp_activity_posted_update', [ __CLASS__, 'maybe_trigger_from_update' ], 10, 3 );
        add_action( 'bp_activity_comment_posted', [ __CLASS__, 'maybe_trigger_from_comment' ], 10, 2 );

        // In case of AJAX update posts/comments
        add_action( 'bp_ajax_querystring', [ __CLASS__, 'register_hooks' ], 10, 2 );
    }

    public static function register_hooks( $qs, $object ) {
        return $qs;
    }

    protected static function get_settings() {
        $opts = get_option( 'llmbpm_settings', [] );
        $defaults = [
            'provider'      => 'openai',
            'api_key'       => '',
            'endpoint'      => 'https://api.openai.com/v1/chat/completions',
            'model'         => 'gpt-4o-mini',
            'bot_user_id'   => 0,
            'temperature'   => 0.6,
            'max_tokens'    => 300,
            'system_prompt' => 'You are a helpful, friendly BuddyPress community assistant. Be concise and actionable.',
            'language'      => '',
            'reply_prefix'  => '',
        ];
        return wp_parse_args( $opts, $defaults );
    }

    protected static function get_bot_user() {
        $s = self::get_settings();
        $uid = intval( $s['bot_user_id'] );
        if ( $uid > 0 ) {
            $user = get_user_by( 'id', $uid );
            if ( $user ) return $user;
        }
        return false;
    }

    // Detect @mentions of the configured bot in a given text
    protected static function contains_bot_mention( $content ) {
        $bot = self::get_bot_user();
        if ( ! $bot ) return false;

        // Mentions can be in nicename, display_name, or @user_login
        $candidates = array_unique( array_filter( [
            $bot->user_nicename,
            $bot->user_login,
            sanitize_title( $bot->display_name ),
        ] ) );

        foreach ( $candidates as $candidate ) {
            $pattern = '/(^|[^a-zA-Z0-9_])@' . preg_quote( $candidate, '/' ) . '\b/iu';
            if ( preg_match( $pattern, $content ) ) {
                return true;
            }
        }
        return false;
    }

    public static function maybe_trigger_from_update( $content, $user_id, $activity_id ) {
        if ( empty( $content ) || ! $activity_id ) return;

        $bot = self::get_bot_user();
        if ( ! $bot ) return;
        if ( intval( $user_id ) === intval( $bot->ID ) ) return; // avoid loops

        if ( self::contains_bot_mention( $content ) ) {
            self::respond_in_activity( $activity_id, $content, $user_id );
        }
    }

    public static function maybe_trigger_from_comment( $comment_id, $params ) {
        $activity_id = isset( $params['activity_id'] ) ? intval( $params['activity_id'] ) : 0;
        $content     = isset( $params['content'] ) ? (string) $params['content'] : '';
        $user_id     = isset( $params['user_id'] ) ? intval( $params['user_id'] ) : 0;

        if ( ! $activity_id || ! $content ) return;

        $bot = self::get_bot_user();
        if ( ! $bot ) return;
        if ( intval( $user_id ) === intval( $bot->ID ) ) return; // avoid loops

        if ( self::contains_bot_mention( $content ) ) {
            self::respond_in_activity( $activity_id, $content, $user_id, $comment_id );
        }
    }

    protected static function build_prompt( $content, $user_id, $activity_id ) {
        $user = get_user_by( 'id', $user_id );
        $uname = $user ? $user->display_name : ('User#' . $user_id);

        // Attempt to fetch some previous comments for minimal context
        $thread_excerpt = '';
        if ( function_exists( 'bp_activity_get' ) ) {
            $thread = bp_activity_get( [
                'in' => $activity_id,
                'display_comments' => 'threaded',
            ] );
            if ( ! empty( $thread['activities'] ) ) {
                $act = $thread['activities'][0];
                $items = [];
                $items[] = strip_tags( $act->content );
                if ( ! empty( $act->children ) ) {
                    $count = 0;
                    foreach ( $act->children as $child ) {
                        $items[] = $child->user_fullname . ': ' . wp_strip_all_tags( $child->content );
                        $count++;
                        if ( $count >= 5 ) break;
                    }
                }
                $thread_excerpt = implode( "\n", array_slice( $items, 0, 6 ) );
                $thread_excerpt = mb_substr( $thread_excerpt, 0, 1200 );
            }
        }

        $prompt = "BuddyPress activity context (may be truncated):\n" . $thread_excerpt . "\n\n".
                  "Latest user message from {$uname}:\n" . wp_strip_all_tags( $content );

        return $prompt;
    }

    protected static function call_llm( $prompt ) {
        $s = self::get_settings();

        if ( empty( $s['api_key'] ) ) {
            return new WP_Error( 'llmbpm_no_key', 'LLM API key not configured.' );
        }

        $endpoint = ! empty( $s['endpoint'] ) ? $s['endpoint'] : 'https://api.openai.com/v1/chat/completions';

        $body = [
            'model' => $s['model'],
            'messages' => [
                [ 'role' => 'system', 'content' => $s['system_prompt'] ],
                [ 'role' => 'user',   'content' => $prompt ],
            ],
            'temperature' => floatval( $s['temperature'] ),
            'max_tokens'  => intval( $s['max_tokens'] ),
        ];

        /**
         * Filter request body before sending to the LLM endpoint.
         * Return array shaped for the configured endpoint.
         */
        $body = apply_filters( 'llmbpm_request_body', $body );

        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $s['api_key'],
        ];

        /**
         * Filter request headers if needed for 3rd-party providers.
         */
        $headers = apply_filters( 'llmbpm_request_headers', $headers );

        $resp = wp_remote_post( $endpoint, [
            'timeout' => 30,
            'headers' => $headers,
            'body'    => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $resp ) ) {
            return $resp;
        }

        $code = wp_remote_retrieve_response_code( $resp );
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );

        if ( $code >= 400 || ! is_array( $data ) ) {
            return new WP_Error( 'llmbpm_bad_response', 'LLM endpoint error: ' . $code . ' ' . wp_remote_retrieve_body( $resp ) );
        }

        // Try to parse OpenAI chat completions shape
        $text = '';
        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            $text = trim( (string) $data['choices'][0]['message']['content'] );
        } elseif ( isset( $data['output_text'] ) ) {
            $text = trim( (string) $data['output_text'] );
        }

        if ( ! $text ) {
            return new WP_Error( 'llmbpm_empty', 'LLM returned no content.' );
        }

        return $text;
    }

    protected static function respond_in_activity( $activity_id, $content, $user_id, $parent_comment_id = 0 ) {
        $bot = self::get_bot_user();
        if ( ! $bot ) return;

        // Build prompt and call LLM
        $prompt = self::build_prompt( $content, $user_id, $activity_id );
        $reply  = self::call_llm( $prompt );

        if ( is_wp_error( $reply ) ) {
            error_log( '[LLMBPM] '. $reply->get_error_message() );
            return;
        }

        $s = self::get_settings();
        $prefix = trim( (string) $s['reply_prefix'] );
        $final  = $prefix ? ($prefix . ' ' . $reply) : $reply;

        // Post as a comment in the same activity thread
        $args = [
            'activity_id' => $activity_id,
            'content'     => wp_kses_post( $final ),
            'user_id'     => intval( $bot->ID ),
        ];

        if ( $parent_comment_id ) {
            $args['parent_id'] = $parent_comment_id;
        }

        // Create comment
        $cid = bp_activity_new_comment( $args );
        if ( ! $cid || is_wp_error( $cid ) ) {
            error_log( '[LLMBPM] Failed to post bot reply.' );
        }
    }
}
