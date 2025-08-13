<?php
/**
 * Plugin Name: LLM BuddyPress Mentions
 * Description: Let users summon an LLM agent by mentioning a specific BuddyPress user (e.g., @grok). The agent replies in the same activity thread.
 * Version: 1.0.0
 * Author: Cryptoball cryptoball7@gmail.com
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPLv3
 * Text Domain: llm-bp-mentions
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LLM_BP_MENTIONS_VERSION', '1.0.0' );
define( 'LLM_BP_MENTIONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'LLM_BP_MENTIONS_URL', plugin_dir_url( __FILE__ ) );

// Simple autoloader.
spl_autoload_register( function( $class ) {
    if ( strpos( $class, 'LLMBPM_' ) === 0 ) {
        $rel = 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
        $path = LLM_BP_MENTIONS_DIR . $rel;
        if ( file_exists( $path ) ) require_once $path;
    }
} );

// Check BuddyPress.
function llmbpm_needs_buddypress_notice() {
    if ( ! class_exists( 'BuddyPress' ) ) {
        echo '<div class="notice notice-error"><p><strong>LLM BuddyPress Mentions</strong> requires BuddyPress to be active.</p></div>';
    }
}
add_action( 'admin_notices', 'llmbpm_needs_buddypress_notice' );

// Boot.
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'BuddyPress' ) ) return;
    LLMBPM_Settings::init();
    LLMBPM_Core::init();
} );

// Activation: create default options.
register_activation_hook( __FILE__, function() {
    if ( get_option( 'llmbpm_settings' ) === false ) {
        add_option( 'llmbpm_settings', array(
            'provider'     => 'openai',
            'api_key'      => '',
            'endpoint'     => 'https://api.openai.com/v1/chat/completions',
            'model'        => 'gpt-4o-mini',
            'bot_user_id'  => 0,
            'temperature'  => 0.6,
            'max_tokens'   => 300,
            'system_prompt'=> "You are a helpful, friendly BuddyPress community assistant. Be concise and actionable. When unsure, ask a brief clarifying question.",
            'language'     => '',
            'reply_prefix' => '',
        ) );
    }
} );
