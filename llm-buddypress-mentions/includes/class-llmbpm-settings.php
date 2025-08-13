<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LLMBPM_Settings {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/llm-bp-mentions.php' ), [ __CLASS__, 'quick_link' ] );
    }

    public static function quick_link( $links ) {
        $settings_link = '<a href="' . admin_url( 'options-general.php?page=llmbpm-settings' ) . '">Settings</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public static function menu() {
        add_options_page(
            'LLM BuddyPress Mentions',
            'LLM Mentions',
            'manage_options',
            'llmbpm-settings',
            [ __CLASS__, 'render' ]
        );
    }

    public static function register() {
        register_setting( 'llmbpm', 'llmbpm_settings', [ __CLASS__, 'sanitize' ] );

        add_settings_section( 'llmbpm_main', 'Main Settings', '__return_false', 'llmbpm' );

        add_settings_field( 'bot_user_id', 'Bot User', [ __CLASS__, 'field_bot_user' ], 'llmbpm', 'llmbpm_main' );
        add_settings_field( 'api_key', 'API Key', [ __CLASS__, 'field_api_key' ], 'llmbpm', 'llmbpm_main' );
        add_settings_field( 'endpoint', 'API Endpoint', [ __CLASS__, 'field_endpoint' ], 'llmbpm', 'llmbpm_main' );
        add_settings_field( 'model', 'Model', [ __CLASS__, 'field_model' ], 'llmbpm', 'llmbpm_main' );
        add_settings_field( 'temperature', 'Temperature', [ __CLASS__, 'field_temperature' ], 'llmbpm', 'llmbpm_main' );
        add_settings_field( 'max_tokens', 'Max Tokens', [ __CLASS__, 'field_max_tokens' ], 'llmbpm', 'llmbpm_main' );
        add_settings_field( 'system_prompt', 'System Prompt', [ __CLASS__, 'field_system_prompt' ], 'llmbpm', 'llmbpm_main' );
        add_settings_field( 'reply_prefix', 'Reply Prefix (optional)', [ __CLASS__, 'field_reply_prefix' ], 'llmbpm', 'llmbpm_main' );
        add_settings_field( 'language', 'Force Language (optional)', [ __CLASS__, 'field_language' ], 'llmbpm', 'llmbpm_main' );
    }

    public static function sanitize( $input ) {
        $out = [];
        $out['provider']      = 'openai';
        $out['api_key']       = sanitize_text_field( $input['api_key'] ?? '' );
        $out['endpoint']      = esc_url_raw( $input['endpoint'] ?? 'https://api.openai.com/v1/chat/completions' );
        $out['model']         = sanitize_text_field( $input['model'] ?? 'gpt-4o-mini' );
        $out['bot_user_id']   = intval( $input['bot_user_id'] ?? 0 );
        $out['temperature']   = floatval( $input['temperature'] ?? 0.6 );
        $out['max_tokens']    = intval( $input['max_tokens'] ?? 300 );
        $out['system_prompt'] = wp_kses_post( $input['system_prompt'] ?? '' );
        $out['reply_prefix']  = sanitize_text_field( $input['reply_prefix'] ?? '' );
        $out['language']      = sanitize_text_field( $input['language'] ?? '' );
        return $out;
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $s = get_option( 'llmbpm_settings', [] );
        ?>
        <div class="wrap">
            <h1>LLM BuddyPress Mentions</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'llmbpm' ); do_settings_sections( 'llmbpm' ); submit_button(); ?>
            </form>

            <hr/>
            <h2>How it works</h2>
            <ol>
                <li>Create or choose a WordPress user that will act as the bot (e.g., <code>grok</code>).</li>
                <li>Select that user in <strong>Bot User</strong> and enter your API credentials.</li>
                <li>In BuddyPress activity, when someone writes <code>@botname</code>, the plugin will send that message and some context to the LLM and post a threaded reply as the bot.</li>
            </ol>
            <p><em>Tip:</em> Use the <strong>System Prompt</strong> to define tone and capabilities. You can also change the endpoint/headers via filters <code>llmbpm_request_body</code> and <code>llmbpm_request_headers</code> if using a non-OpenAI provider.</p>
        </div>
        <?php
    }

    protected static function users_dropdown( $name, $selected = 0 ) {
        $args = array(
            'name' => $name,
            'selected' => $selected,
            'show' => 'display_name',
            'echo' => 0,
        );
        return wp_dropdown_users( $args );
    }

    public static function field_bot_user() {
        $s = get_option( 'llmbpm_settings', [] );
        echo self::users_dropdown( 'llmbpm_settings[bot_user_id]', intval( $s['bot_user_id'] ?? 0 ) );
        echo '<p class="description">Pick the BuddyPress user to act as the LLM agent. Mention this user (e.g., @grok) to trigger replies.</p>';
    }

    public static function field_api_key() {
        $s = get_option( 'llmbpm_settings', [] );
        printf( '<input type="password" name="llmbpm_settings[api_key]" value="%s" class="regular-text" autocomplete="off" />', esc_attr( $s['api_key'] ?? '' ) );
        echo '<p class="description">OpenAI (or compatible) API key.</p>';
    }

    public static function field_endpoint() {
        $s = get_option( 'llmbpm_settings', [] );
        $val = esc_attr( $s['endpoint'] ?? 'https://api.openai.com/v1/chat/completions' );
        printf( '<input type="url" name="llmbpm_settings[endpoint]" value="%s" class="regular-text code" />', $val );
        echo '<p class="description">Default: https://api.openai.com/v1/chat/completions. Change if using a different provider.</p>';
    }

    public static function field_model() {
        $s = get_option( 'llmbpm_settings', [] );
        $val = esc_attr( $s['model'] ?? 'gpt-4o-mini' );
        printf( '<input type="text" name="llmbpm_settings[model]" value="%s" class="regular-text" />', $val );
        echo '<p class="description">Model name, e.g., gpt-4o-mini, gpt-4.1, etc.</p>';
    }

    public static function field_temperature() {
        $s = get_option( 'llmbpm_settings', [] );
        $val = esc_attr( $s['temperature'] ?? '0.6' );
        printf( '<input type="number" step="0.1" min="0" max="2" name="llmbpm_settings[temperature]" value="%s" class="small-text" />', $val );
    }

    public static function field_max_tokens() {
        $s = get_option( 'llmbpm_settings', [] );
        $val = esc_attr( $s['max_tokens'] ?? '300' );
        printf( '<input type="number" step="1" min="1" name="llmbpm_settings[max_tokens]" value="%s" class="small-text" />', $val );
    }

    public static function field_system_prompt() {
        $s = get_option( 'llmbpm_settings', [] );
        $val = esc_textarea( $s['system_prompt'] ?? '' );
        printf( '<textarea name="llmbpm_settings[system_prompt]" rows="5" class="large-text code">%s</textarea>', $val );
    }

    public static function field_reply_prefix() {
        $s = get_option( 'llmbpm_settings', [] );
        $val = esc_attr( $s['reply_prefix'] ?? '' );
        printf( '<input type="text" name="llmbpm_settings[reply_prefix]" value="%s" class="regular-text" />', $val );
        echo '<p class="description">Optional prefix added to every reply, e.g., "🤖".</p>';
    }

    public static function field_language() {
        $s = get_option( 'llmbpm_settings', [] );
        $val = esc_attr( $s['language'] ?? '' );
        printf( '<input type="text" name="llmbpm_settings[language]" value="%s" class="regular-text" />', $val );
        echo '<p class="description">Optional forced language code (e.g., en, es). You can incorporate this into your System Prompt.</p>';
    }
}
