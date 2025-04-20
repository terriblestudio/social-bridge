<?php
/**
 * Plugin Name: Social Bridge
 * Plugin URI: https://terrible.studio/plugins/social-bridge
 * Description: Integrates WordPress with social media platforms and allows for comment synchronization.
 * Version: 0.1.0
 * Author: terrible studio
 * Author URI: https://terrible.studio
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: social-bridge
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('SOCIAL_BRIDGE_VERSION', '0.1.0');
define('SOCIAL_BRIDGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SOCIAL_BRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SOCIAL_BRIDGE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load dependencies
require_once SOCIAL_BRIDGE_PLUGIN_DIR . 'includes/functions/core.php';
require_once SOCIAL_BRIDGE_PLUGIN_DIR . 'includes/functions/helpers.php';

// Initialize plugin
function social_bridge_init() {
    // Load textdomain for translations
    load_plugin_textdomain('social-bridge', false, dirname(SOCIAL_BRIDGE_PLUGIN_BASENAME) . '/languages');
    
    // Include required files
    if (is_admin()) {
        require_once SOCIAL_BRIDGE_PLUGIN_DIR . 'includes/admin/settings.php';
        require_once SOCIAL_BRIDGE_PLUGIN_DIR . 'includes/admin/meta-boxes.php';
    }
    
    // Load integrations
    require_once SOCIAL_BRIDGE_PLUGIN_DIR . 'includes/integrations/class-social-integration.php';
    require_once SOCIAL_BRIDGE_PLUGIN_DIR . 'includes/integrations/bluesky/class-bluesky-integration.php';
    require_once SOCIAL_BRIDGE_PLUGIN_DIR . 'includes/integrations/mastodon/class-mastodon-integration.php';
    
    // Load blocks
    require_once SOCIAL_BRIDGE_PLUGIN_DIR . 'includes/blocks/likes-collage.php';
    
    // Load cron functionality
    require_once SOCIAL_BRIDGE_PLUGIN_DIR . 'includes/cron/sync-comments.php';
}
add_action('plugins_loaded', 'social_bridge_init');

// Activation hook
register_activation_hook(__FILE__, 'social_bridge_activate');
function social_bridge_activate() {
    // Set up cron jobs
    if (!wp_next_scheduled('social_bridge_sync_comments')) {
        wp_schedule_event(time(), 'hourly', 'social_bridge_sync_comments');
    }
    
    // Create necessary database tables
    social_bridge_create_tables();
    
    // Create languages directory if it doesn't exist
    $languages_dir = dirname(SOCIAL_BRIDGE_PLUGIN_BASENAME) . '/languages';
    if (!file_exists($languages_dir)) {
        wp_mkdir_p($languages_dir);
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'social_bridge_deactivate');
function social_bridge_deactivate() {
    // Clear cron jobs
    wp_clear_scheduled_hook('social_bridge_sync_comments');
}

// Create database tables if needed
function social_bridge_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $table_name = $wpdb->prefix . 'social_bridge_interactions';
    
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        platform varchar(50) NOT NULL,
        interaction_type varchar(50) NOT NULL,
        interaction_id varchar(255) NOT NULL,
        interaction_data longtext NOT NULL,
        interaction_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        comment_id bigint(20) NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY interaction_id (platform, interaction_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Register API endpoints
require_once SOCIAL_BRIDGE_PLUGIN_DIR . 'includes/api/endpoints.php';

// Add plugin action links
function social_bridge_add_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=social-bridge') . '">' . __('Settings', 'social-bridge') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . SOCIAL_BRIDGE_PLUGIN_BASENAME, 'social_bridge_add_action_links'); 