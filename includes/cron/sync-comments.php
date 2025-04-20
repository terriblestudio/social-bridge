<?php
/**
 * Sync Comments
 * 
 * Handles comment synchronization via WordPress cron.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Main function to run the sync process.
 * 
 * This is the function that gets triggered by the cron event.
 */
function social_bridge_sync_all_comments() {
    $integrations = social_bridge_get_integrations();
    $sync_count = 0;
    $error_count = 0;
    
    foreach ($integrations as $integration) {
        if ($integration->is_configured()) {
            try {
                // Call the sync method of each integration
                $integration->sync_comments();
                $sync_count++;
            } catch (Exception $e) {
                // phpcs:disable WordPress.PHP.DevelopmentFunctions
                // Log error. This is a cron job so not user-facing; if errors happen, it should be logged.
                error_log('Social Bridge Error (' . $integration->get_platform_name() . '): ' . $e->getMessage());
                $error_count++;
                // phpcs:enable
            }
        }
    }
    
    // Log completion
    $time = current_time('mysql');
    update_option('social_bridge_last_sync', array(
        'time' => $time,
        'integrations' => $sync_count,
        'errors' => $error_count
    ));
    
    do_action('social_bridge_after_sync', $sync_count, $error_count);
    
    return array(
        'time' => $time,
        'integrations' => $sync_count,
        'errors' => $error_count
    );
}
add_action('social_bridge_sync_comments', 'social_bridge_sync_all_comments');

/**
 * Check if sync is currently running
 * 
 * @return bool
 */
function social_bridge_is_sync_running() {
    $lock_time = get_option('social_bridge_sync_lock');
    
    if (!$lock_time) {
        return false;
    }
    
    // If lock is older than 10 minutes, assume something went wrong and reset
    if (time() - $lock_time > 600) {
        delete_option('social_bridge_sync_lock');
        return false;
    }
    
    return true;
}

/**
 * Set sync lock
 */
function social_bridge_set_sync_lock() {
    update_option('social_bridge_sync_lock', time());
}

/**
 * Release sync lock
 */
function social_bridge_release_sync_lock() {
    delete_option('social_bridge_sync_lock');
}

/**
 * Run before syncing comments
 */
function social_bridge_before_sync() {
    if (social_bridge_is_sync_running()) {
        return false;
    }
    
    social_bridge_set_sync_lock();
    return true;
}
add_action('social_bridge_sync_comments', 'social_bridge_before_sync', 5);

/**
 * Run after syncing comments
 */
function social_bridge_after_sync() {
    social_bridge_release_sync_lock();
}
add_action('social_bridge_after_sync', 'social_bridge_after_sync', 99);

/**
 * Manually trigger a sync operation
 * 
 */
function social_bridge_trigger_manual_sync() {
    // Check if sync is already running
    if (social_bridge_is_sync_running()) {
        return new WP_Error('sync_running', __('A sync operation is already in progress. Please try again later.', 'social-bridge'));
    }
    
    // Prevent timeout
    set_time_limit(300);
    
    // Run the sync
    social_bridge_before_sync();
    //$result = social_bridge_sync_all_comments();
    do_action('social_bridge_sync_comments');
    social_bridge_after_sync();
}

/**
 * Schedule the next run if not already scheduled
 */
function social_bridge_ensure_scheduled_sync() {
    if (!wp_next_scheduled('social_bridge_sync_comments')) {
        // Get frequency from options
        $frequency = get_option('social_bridge_sync_frequency', 'hourly');
        
        wp_schedule_event(time(), $frequency, 'social_bridge_sync_comments');
    }
}
add_action('admin_init', 'social_bridge_ensure_scheduled_sync'); 