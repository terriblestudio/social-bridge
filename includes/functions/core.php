<?php
/**
 * Core Functions
 * 
 * Essential functions for the Social Bridge plugin.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Get all available social integrations
 * 
 * @return array Array of integration objects.
 */
function social_bridge_get_integrations() {
    static $integrations = null;
    
    if ($integrations === null) {
        $integrations = array();
        
        // Find all integration classes
        global $wp_filter;
        
        if (isset($wp_filter['plugins_loaded'])) {
            foreach ($wp_filter['plugins_loaded']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (is_array($callback['function']) && is_object($callback['function'][0])) {
                        $obj = $callback['function'][0];
                        if ($obj instanceof Social_Bridge_Integration) {
                            $integrations[$obj->get_platform_id()] = $obj;
                        }
                    }
                }
            }
        }
        
        // Allow extensions to register integrations
        $integrations = apply_filters('social_bridge_integrations', $integrations);
    }
    
    return $integrations;
}

/**
 * Get a specific integration by ID
 * 
 * @param string $platform_id The platform ID.
 * @return Social_Bridge_Integration|null The integration object or null if not found.
 */
function social_bridge_get_integration($platform_id) {
    $integrations = social_bridge_get_integrations();
    
    return isset($integrations[$platform_id]) ? $integrations[$platform_id] : null;
}

/**
 * Check if a platform is integrated
 * 
 * @param string $platform_id The platform ID.
 * @return bool Whether the platform is integrated.
 */
function social_bridge_is_platform_integrated($platform_id) {
    $integration = social_bridge_get_integration($platform_id);
    
    return $integration !== null && $integration->is_configured();
}

/**
 * Register cron schedules
 * 
 * @param array $schedules WP cron schedules.
 * @return array Modified WP cron schedules.
 */
function social_bridge_cron_schedules($schedules) {
    // Add a weekly schedule
    $schedules['social_bridge_weekly'] = array(
        'interval' => 7 * 24 * 60 * 60,
        'display' => __('Weekly', 'social-bridge')
    );
    
    return $schedules;
}
add_filter('cron_schedules', 'social_bridge_cron_schedules');

/**
 * Sync comments from all platforms for a specific post
 * 
 * @param array $args Arguments array with platform and post_id.
 */
function social_bridge_sync_post_comments_callback($args) {
    if (!isset($args['platform']) || !isset($args['post_id'])) {
        return;
    }
    
    $platform_id = $args['platform'];
    $post_id = $args['post_id'];
    
    $integration = social_bridge_get_integration($platform_id);
    if ($integration && $integration->is_configured()) {
        $integration->sync_post_comments($post_id);
    }
}
add_action('social_bridge_sync_post_comments', 'social_bridge_sync_post_comments_callback');

/**
 * Manually trigger a sync for a specific post
 * 
 * @param int $post_id The post ID.
 * @param string $platform_id Optional platform ID. If not provided, all platforms will be synced.
 * @return array|WP_Error Results or error.
 */
function social_bridge_manual_sync($post_id, $platform_id = null) {
    $results = array();
    
    if ($platform_id) {
        // Sync specific platform
        $integration = social_bridge_get_integration($platform_id);
        if ($integration && $integration->is_configured()) {
            $results[$platform_id] = $integration->sync_post_comments($post_id);
        } else {
            return new WP_Error('invalid_platform', __('Invalid or unconfigured platform', 'social-bridge'));
        }
    } else {
        // Sync all platforms
        $integrations = social_bridge_get_integrations();
        
        foreach ($integrations as $integration) {
            if ($integration->is_configured()) {
                $platform_id = $integration->get_platform_id();
                $results[$platform_id] = $integration->sync_post_comments($post_id);
            }
        }
    }
    
    return $results;
}

/**
 * Get social media URLs for a post
 * 
 * @param int $post_id The post ID.
 * @return array Array of platform => URL pairs.
 */
function social_bridge_get_post_urls($post_id) {
    $urls = array();
    $integrations = social_bridge_get_integrations();
    
    foreach ($integrations as $platform_id => $integration) {
        $url = get_post_meta($post_id, '_social_bridge_' . $platform_id . '_url', true);
        if (!empty($url)) {
            $urls[$platform_id] = array(
                'url' => $url,
                'name' => $integration->get_platform_name(),
                'icon' => $integration->get_platform_icon()
            );
        }
    }
    
    return $urls;
}

/**
 * Get all users who liked a post across all platforms
 * 
 * @param int $post_id The post ID.
 * @param string $platform_id Optional platform ID to filter by.
 * @return array Array of platform => users arrays.
 */
function social_bridge_get_post_likes($post_id, $platform_id = null) {
    $likes = array();
    
    if ($platform_id) {
        // Get likes for a specific platform
        $integration = social_bridge_get_integration($platform_id);
        if ($integration && $integration->is_configured()) {
            $likes[$platform_id] = array(
                'users' => $integration->get_post_likes($post_id),
                'name' => $integration->get_platform_name(),
                'icon' => $integration->get_platform_icon()
            );
        }
    } else {
        // Get likes for all platforms
        $integrations = social_bridge_get_integrations();
        
        foreach ($integrations as $integration) {
            if ($integration->is_configured()) {
                $platform_id = $integration->get_platform_id();
                $platform_likes = $integration->get_post_likes($post_id);
                
                if (!empty($platform_likes)) {
                    $likes[$platform_id] = array(
                        'users' => $platform_likes,
                        'name' => $integration->get_platform_name(),
                        'icon' => $integration->get_platform_icon()
                    );
                }
            }
        }
    }
    
    return $likes;
}

/**
 * Check for plugin updates
 */
function social_bridge_check_for_updates() {
    $current_version = get_option('social_bridge_version', '0.0.0');
    
    if (version_compare($current_version, SOCIAL_BRIDGE_VERSION, '<')) {
        // Perform update tasks if needed
        if (version_compare($current_version, '0.1.0', '<')) {
            // Initial setup or upgrade to 0.1.0
            social_bridge_create_tables();
        }
        
        // Update version in database
        update_option('social_bridge_version', SOCIAL_BRIDGE_VERSION);
    }
}
add_action('plugins_loaded', 'social_bridge_check_for_updates', 5);