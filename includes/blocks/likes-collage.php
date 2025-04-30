<?php
/**
 * Likes Collage Block
 * 
 * Registers a block and shortcode to display social media likes as an avatar collage.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register likes collage block
 */
function social_bridge_register_likes_collage_block() {
    // Skip block registration if Gutenberg is not available
    if (!function_exists('register_block_type')) {
        return;
    }
    
    // Register block script
    wp_register_script(
        'social-bridge-likes-collage-block',
        SOCIAL_BRIDGE_PLUGIN_URL . 'assets/js/likes-collage-block.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
        SOCIAL_BRIDGE_VERSION,
        true // Load in footer
    );
    
    // Register block style
    wp_register_style(
        'social-bridge-likes-collage-block',
        SOCIAL_BRIDGE_PLUGIN_URL . 'assets/css/likes-collage-block.css',
        array(),
        SOCIAL_BRIDGE_VERSION
    );
    
    // Register block
    register_block_type('social-bridge/likes-collage', array(
        'editor_script' => 'social-bridge-likes-collage-block',
        'editor_style' => 'social-bridge-likes-collage-block',
        'attributes' => array(
            'platform' => array(
                'type' => 'string',
                'default' => ''
            ),
            'maxUsers' => array(
                'type' => 'number',
                'default' => 8
            ),
            'avatarSize' => array(
                'type' => 'number',
                'default' => 48
            ),
            'showTotal' => array(
                'type' => 'boolean',
                'default' => true
            ),
            'className' => array(
                'type' => 'string',
                'default' => ''
            )
        ),
        'render_callback' => 'social_bridge_render_likes_collage_block'
    ));
    
    // Add inline data for available platforms
    $integrations = social_bridge_get_integrations();
    $platforms = array();
    
    foreach ($integrations as $platform_id => $integration) {
        if ($integration->is_configured()) {
            $platforms[] = array(
                'id' => $platform_id,
                'name' => $integration->get_platform_name(),
                'icon' => $integration->get_platform_icon()
            );
        }
    }
    
    wp_localize_script('social-bridge-likes-collage-block', 'socialBridgePlatforms', $platforms);
}
add_action('init', 'social_bridge_register_likes_collage_block');

/**
 * Render likes collage block
 * 
 * @param array $attributes Block attributes.
 * @return string Block output.
 */
function social_bridge_render_likes_collage_block($attributes) {
    global $post;
    
    if (!$post) {
        return '';
    }
    
    $platform = isset($attributes['platform']) ? $attributes['platform'] : '';
    $max_users = isset($attributes['maxUsers']) ? intval($attributes['maxUsers']) : 8;
    $avatar_size = isset($attributes['avatarSize']) ? intval($attributes['avatarSize']) : 48;
    $show_total = isset($attributes['showTotal']) ? (bool) $attributes['showTotal'] : true;
    $class_name = isset($attributes['className']) ? $attributes['className'] : '';
    
    // Get post likes
    $likes = social_bridge_get_post_likes($post->ID, $platform);
    
    if (empty($likes)) {
        return '<div class="social-bridge-likes-collage-empty">' . __('No likes found.', 'social-bridge') . '</div>';
    }
    
    $output = '<div class="social-bridge-likes-collage-wrapper ' . esc_attr($class_name) . '">';
    
    // Loop through platforms
    foreach ($likes as $platform_id => $platform_data) {
        if (!empty($platform_data['users'])) {
            $output .= '<div class="social-bridge-likes-platform">';
            
            if ($show_total) {
                $count = count($platform_data['users']);
                $output .= '<div class="social-bridge-likes-count">';
                $output .= '<span class="dashicons ' . esc_attr($platform_data['icon']) . '"></span> ';
                $output .= sprintf(
                /* translators: %1$s is the number of likes, %2$s is the platform name */
                __('%1$s likes on %2$s', 'social-bridge'),
                    '<strong>' . $count . '</strong>',
                    '<strong>' . esc_html($platform_data['name']) . '</strong>'
                );
                $output .= '</div>';
            }
            
            // Generate avatar collage
            $output .= social_bridge_generate_avatar_collage($platform_data['users'], $max_users, $avatar_size);
            
            $output .= '</div>';
        }
    }
    
    $output .= '</div>';
    
    return $output;
}

/**
 * Register likes collage shortcode
 * 
 * @param array $atts Shortcode attributes.
 * @return string Shortcode output.
 */
function social_bridge_likes_collage_shortcode($atts) {
    $attributes = shortcode_atts(array(
        'platform' => '',
        'max_users' => 8,
        'avatar_size' => 48,
        'show_total' => true,
        'class' => ''
    ), $atts);
    
    // Convert shortcode attributes to block attributes
    $block_attributes = array(
        'platform' => $attributes['platform'],
        'maxUsers' => intval($attributes['max_users']),
        'avatarSize' => intval($attributes['avatar_size']),
        'showTotal' => $attributes['show_total'] === 'false' ? false : true,
        'className' => $attributes['class']
    );
    
    return social_bridge_render_likes_collage_block($block_attributes);
}
add_shortcode('social_bridge_likes', 'social_bridge_likes_collage_shortcode');