<?php
/**
 * Settings Page
 * 
 * Admin settings page for Social Bridge.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register settings page
 */
function social_bridge_register_settings_page() {
    add_options_page(
        __('Social Bridge Settings', 'social-bridge'),
        __('Social Bridge', 'social-bridge'),
        'manage_options',
        'social-bridge',
        'social_bridge_render_settings_page'
    );
    
    // Add sync page
    add_submenu_page(
        null, // Hidden from menu
        __('Sync Social Comments', 'social-bridge'),
        __('Sync Social Comments', 'social-bridge'),
        'manage_options',
        'social-bridge-sync',
        'social_bridge_render_sync_page'
    );
}
add_action('admin_menu', 'social_bridge_register_settings_page');

/**
 * Register settings
 */
function social_bridge_register_settings() {
    register_setting('social-bridge', 'social_bridge_general_settings');
    
    // General settings section
    add_settings_section(
        'social_bridge_general_settings',
        __('General Settings', 'social-bridge'),
        'social_bridge_render_general_settings_section',
        'social-bridge'
    );
    
    // Add general settings fields
    add_settings_field(
        'social_bridge_sync_frequency',
        __('Comments Sync Frequency', 'social-bridge'),
        'social_bridge_render_sync_frequency_field',
        'social-bridge',
        'social_bridge_general_settings'
    );
    
    add_settings_field(
        'social_bridge_comment_appearance',
        __('Social Comment Appearance', 'social-bridge'),
        'social_bridge_render_comment_appearance_field',
        'social-bridge',
        'social_bridge_general_settings'
    );
    
    add_settings_field(
        'social_bridge_comment_types',
        __('Comment Types to Import', 'social-bridge'),
        'social_bridge_render_comment_types_field',
        'social-bridge',
        'social_bridge_general_settings'
    );
    
    // Register settings
    register_setting(
        'social-bridge',
        'social_bridge_sync_frequency',
        array(
            'type' => 'string',
            'description' => 'Sync frequency for social media comments',
            'default' => 'hourly',
            'sanitize_callback' => 'sanitize_text_field'
        )
    );
    
    // Settings for comment appearance
    register_setting(
        'social-bridge',
        'social_bridge_comment_appearance',
        array(
            'type' => 'string',
            'description' => 'How social media comments appear on the site',
            'default' => 'integrated',
            'sanitize_callback' => 'sanitize_text_field'
        )
    );
    
    // Settings for which types of comments to show
    register_setting(
        'social-bridge',
        'social_bridge_comment_types',
        array(
            'type' => 'array',
            'description' => 'Types of social interactions to display',
            'default' => array('comment' => '1', 'share' => '1', 'like' => '1'),
            'sanitize_callback' => 'social_bridge_sanitize_comment_types'
        )
    );
    
    // Setting for auto-sync
    register_setting(
        'social-bridge',
        'social_bridge_always_sync',
        array(
            'type' => 'boolean',
            'description' => 'Automatically sync comments on post updates',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        )
    );
}
add_action('admin_init', 'social_bridge_register_settings');

/**
 * Render settings page
 */
function social_bridge_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Get active tab
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url(admin_url('options-general.php?page=social-bridge')); ?>" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('General', 'social-bridge'); ?>
            </a>
            <?php
            // Add tabs for each integration
            $integrations = social_bridge_get_integrations();
            foreach ($integrations as $platform_id => $integration) {
                $platform_name = $integration->get_platform_name();
                ?>
                <a href="?page=social-bridge&tab=<?php echo esc_attr($platform_id); ?>" class="nav-tab <?php echo $active_tab === $platform_id ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($platform_name); ?>
                </a>
                <?php
            }
            ?>
        </h2>
        
        <form action="options.php" method="post">
            <?php
            if ($active_tab === 'general') {
                // General settings
                settings_fields('social-bridge');
                do_settings_sections('social-bridge');
            } else {
                // Platform-specific settings
                settings_fields('social-bridge');
                
                // Find the section ID for this platform
                $section_id = '';
                
                foreach ($integrations as $platform_id => $integration) {
                    if ($platform_id === $active_tab) {
                        // Output the section
                        echo '<div id="social-bridge-' . esc_attr($platform_id) . '-settings">';
                        do_settings_sections('social-bridge');
                        echo '</div>';
                        break;
                    }
                }
            }
            
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Render general settings section
 */
function social_bridge_render_general_settings_section() {
    echo '<p>' . __('Configure general settings for Social Bridge integrations.', 'social-bridge') . '</p>';
}

/**
 * Render sync frequency field
 */
function social_bridge_render_sync_frequency_field() {
    $frequency = get_option('social_bridge_sync_frequency', 'hourly');
    $options = array(
        'hourly' => __('Hourly', 'social-bridge'),
        'twicedaily' => __('Twice Daily', 'social-bridge'),
        'daily' => __('Daily', 'social-bridge'),
        'social_bridge_weekly' => __('Weekly', 'social-bridge')
    );
    
    ?>
    <select name="social_bridge_sync_frequency">
        <?php foreach ($options as $value => $label) : ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected($frequency, $value); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description"><?php esc_html_e('How often should Social Bridge check for new comments on social platforms?', 'social-bridge'); ?></p>
    <?php
}

/**
 * Render comment appearance field
 */
function social_bridge_render_comment_appearance_field() {
    $appearance = get_option('social_bridge_comment_appearance', 'integrated');
    $options = array(
        'integrated' => __('Fully integrated (look like WordPress comments)', 'social-bridge'),
        'styled' => __('Styled differently, but in the same comment section', 'social-bridge'),
        'separate' => __('In a separate section below WordPress comments', 'social-bridge')
    );
    
    ?>
    <fieldset>
        <?php foreach ($options as $value => $label) : ?>
            <label>
                <input type="radio" name="social_bridge_comment_appearance" value="<?php echo esc_attr($value); ?>" <?php checked($appearance, $value); ?>>
                <?php echo esc_html($label); ?>
            </label>
            <br>
        <?php endforeach; ?>
    </fieldset>
    <p class="description"><?php esc_html_e('How should social media comments appear on your WordPress site?', 'social-bridge'); ?></p>
    <?php
}

/**
 * Render comment types field
 */
function social_bridge_render_comment_types_field() {
    $types = get_option('social_bridge_comment_types', array('comment' => '1', 'share' => '1', 'like' => '1'));
    
    if (!is_array($types)) {
        $types = array('comment' => '1', 'share' => '1', 'like' => '1');
    }
    
    ?>
    <fieldset>
        <label>
            <input type="checkbox" name="social_bridge_comment_types[comment]" value="1" <?php checked(isset($types['comment']) && $types['comment'] === '1'); ?>>
            <?php _e('Comments/Replies', 'social-bridge'); ?>
        </label>
        <br>
        
        <label>
            <input type="checkbox" name="social_bridge_comment_types[share]" value="1" <?php checked(isset($types['share']) && $types['share'] === '1'); ?>>
            <?php _e('Shares/Retweets/Reblogs (as pingbacks)', 'social-bridge'); ?>
        </label>
        <br>
        
        <label>
            <input type="checkbox" name="social_bridge_comment_types[like]" value="1" <?php checked(isset($types['like']) && $types['like'] === '1'); ?>>
            <?php _e('Likes/Favorites', 'social-bridge'); ?>
        </label>
    </fieldset>
    <p class="description"><?php esc_html_e('Which types of social interactions should be imported?', 'social-bridge'); ?></p>
    <?php
}

/**
 * Render sync page
 */
function social_bridge_render_sync_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $platform = isset($_GET['platform']) ? sanitize_text_field($_GET['platform']) : '';
    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
    
    $post = get_post($post_id);
    
    if (!$post) {
        wp_die(__('Invalid post ID.', 'social-bridge'));
    }
    
    $results = false;
    $error = false;
    
    // Handle sync request
    if (isset($_POST['social_bridge_sync']) && wp_verify_nonce($_POST['social_bridge_sync_nonce'], 'social_bridge_sync')) {
        $results = social_bridge_manual_sync($post_id, $platform ?: null);
        
        if (is_wp_error($results)) {
            $error = $results->get_error_message();
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Sync Social Comments', 'social-bridge'); ?></h1>
        
        <div class="notice notice-info">
            <p>
                <?php 
                /* translators: %s is the post title */
                echo sprintf(
                    __('You are syncing social interactions for: <strong>%s</strong>', 'social-bridge'), 
                    esc_html($post->post_title)
                ); 
                ?>
            </p>
        </div>
        
        <?php if ($error) : ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($error); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($results && !is_wp_error($results)) : ?>
            <div class="notice notice-success">
                <p><?php esc_html_e('Sync completed successfully!', 'social-bridge'); ?></p>
                <ul>
                    <?php foreach ($results as $platform_id => $count) : ?>
                        <li>
                            <?php
                            $platform_name = social_bridge_get_platform_name($platform_id);
                            /* translators: %1$s is the platform name, %2$d is the number of interactions synced */
                            echo sprintf(
                                __('<strong>%1$s</strong>: %2$d new interactions synced', 'social-bridge'),
                                esc_html($platform_name),
                                intval($count)
                            );
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <?php wp_nonce_field('social_bridge_sync', 'social_bridge_sync_nonce'); ?>
            
            <p>
                <?php
                if ($platform) {
                    $platform_name = social_bridge_get_platform_name($platform);
                    /* translators: %s is the platform name */
                    echo sprintf(
                        __('You are about to sync comments and interactions from <strong>%s</strong>.', 'social-bridge'),
                        esc_html($platform_name)
                    );
                } else {
                    esc_html_e('You are about to sync comments and interactions from all configured social platforms.', 'social-bridge');
                }
                ?>
            </p>
            
            <p>
                <?php esc_html_e('This will import any new comments, shares, and likes from the social media post to your WordPress site.', 'social-bridge'); ?>
            </p>
            
            <?php
            // Get post URLs
            $post_urls = social_bridge_get_post_urls($post_id);
            
            if (empty($post_urls)) {
                ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('No social media URLs found for this post. Please add a URL in the post editor and try again.', 'social-bridge'); ?></p>
                </div>
                <?php
            } else {
                ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Social Media URLs', 'social-bridge'); ?></th>
                        <td>
                            <ul>
                                <?php foreach ($post_urls as $platform_id => $data) : ?>
                                    <li>
                                        <strong><?php echo esc_html($data['name']); ?>:</strong>
                                        <a href="<?php echo esc_url($data['url']); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html($data['url']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="social_bridge_sync" class="button button-primary" value="<?php esc_attr_e('Sync Now', 'social-bridge'); ?>">
                    <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>" class="button"><?php esc_html_e('Back to Editor', 'social-bridge'); ?></a>
                </p>
                <?php
            }
            ?>
        </form>
    </div>
    <?php
}

/**
 * Update sync schedule when frequency changes
 * 
 * @param string $old_value Old option value.
 * @param string $new_value New option value.
 */
function social_bridge_update_cron_schedule($old_value, $new_value) {
    if ($old_value !== $new_value) {
        // Clear existing schedule
        wp_clear_scheduled_hook('social_bridge_sync_comments');
        
        // Set up new schedule
        wp_schedule_event(time(), $new_value, 'social_bridge_sync_comments');
    }
}
add_action('update_option_social_bridge_sync_frequency', 'social_bridge_update_cron_schedule', 10, 2);

/**
 * Sanitize comment types
 * 
 * @param array $input The input array.
 * @return array Sanitized array.
 */
function social_bridge_sanitize_comment_types($input) {
    if (!is_array($input)) {
        return array();
    }
    
    $sanitized = array();
    $allowed_types = array('comment', 'share', 'like');
    
    foreach ($allowed_types as $type) {
        $sanitized[$type] = isset($input[$type]) ? '1' : '0';
    }
    
    return $sanitized;
} 