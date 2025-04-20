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
                /* translators: %1$s is the number of likes, %2$s is the platform name */
                $output .= sprintf(
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

/**
 * Create likes collage block JavaScript file
 */
function social_bridge_create_likes_collage_block_js() {
    $js_dir = SOCIAL_BRIDGE_PLUGIN_DIR . 'assets/js';
    
    // Create directory if it doesn't exist
    if (!file_exists($js_dir)) {
        wp_mkdir_p($js_dir);
    }
    
    $js_file = $js_dir . '/likes-collage-block.js';
    
    // Don't overwrite existing file
    if (file_exists($js_file)) {
        return;
    }
    
    $content = 
        '(function(blocks, element, blockEditor, components) {' . "\n" .
        '    var el = element.createElement;' . "\n" .
        '    var InspectorControls = blockEditor.InspectorControls;' . "\n" .
        '    var PanelBody = components.PanelBody;' . "\n" .
        '    var SelectControl = components.SelectControl;' . "\n" .
        '    var RangeControl = components.RangeControl;' . "\n" .
        '    var ToggleControl = components.ToggleControl;' . "\n" .
        '    var ServerSideRender = components.ServerSideRender;' . "\n" .
        '    ' . "\n" .
        '    blocks.registerBlockType(\'social-bridge/likes-collage\', {' . "\n" .
        '        title: \'Social Likes Collage\',' . "\n" .
        '        icon: \'groups\',' . "\n" .
        '        category: \'widgets\',' . "\n" .
        '        attributes: {' . "\n" .
        '            platform: {' . "\n" .
        '                type: \'string\',' . "\n" .
        '                default: \'\'' . "\n" .
        '            },' . "\n" .
        '            maxUsers: {' . "\n" .
        '                type: \'number\',' . "\n" .
        '                default: 8' . "\n" .
        '            },' . "\n" .
        '            avatarSize: {' . "\n" .
        '                type: \'number\',' . "\n" .
        '                default: 48' . "\n" .
        '            },' . "\n" .
        '            showTotal: {' . "\n" .
        '                type: \'boolean\',' . "\n" .
        '                default: true' . "\n" .
        '            }' . "\n" .
        '        },' . "\n" .
        '        ' . "\n" .
        '        edit: function(props) {' . "\n" .
        '            var attributes = props.attributes;' . "\n" .
        '            ' . "\n" .
        '            // Prepare platform options' . "\n" .
        '            var platformOptions = [' . "\n" .
        '                { value: \'\', label: \'All Platforms\' }' . "\n" .
        '            ];' . "\n" .
        '            ' . "\n" .
        '            if (typeof socialBridgePlatforms !== \'undefined\') {' . "\n" .
        '                socialBridgePlatforms.forEach(function(platform) {' . "\n" .
        '                    platformOptions.push({' . "\n" .
        '                        value: platform.id,' . "\n" .
        '                        label: platform.name' . "\n" .
        '                    });' . "\n" .
        '                });' . "\n" .
        '            }' . "\n" .
        '            ' . "\n" .
        '            return [' . "\n" .
        '                el(InspectorControls, { key: \'inspector\' },' . "\n" .
        '                    el(PanelBody, { title: \'Settings\', initialOpen: true },' . "\n" .
        '                        el(SelectControl, {' . "\n" .
        '                            label: \'Platform\',' . "\n" .
        '                            value: attributes.platform,' . "\n" .
        '                            options: platformOptions,' . "\n" .
        '                            onChange: function(value) {' . "\n" .
        '                                props.setAttributes({ platform: value });' . "\n" .
        '                            }' . "\n" .
        '                        }),' . "\n" .
        '                        el(RangeControl, {' . "\n" .
        '                            label: \'Maximum Users\',' . "\n" .
        '                            value: attributes.maxUsers,' . "\n" .
        '                            min: 1,' . "\n" .
        '                            max: 50,' . "\n" .
        '                            onChange: function(value) {' . "\n" .
        '                                props.setAttributes({ maxUsers: value });' . "\n" .
        '                            }' . "\n" .
        '                        }),' . "\n" .
        '                        el(RangeControl, {' . "\n" .
        '                            label: \'Avatar Size\',' . "\n" .
        '                            value: attributes.avatarSize,' . "\n" .
        '                            min: 16,' . "\n" .
        '                            max: 128,' . "\n" .
        '                            onChange: function(value) {' . "\n" .
        '                                props.setAttributes({ avatarSize: value });' . "\n" .
        '                            }' . "\n" .
        '                        }),' . "\n" .
        '                        el(ToggleControl, {' . "\n" .
        '                            label: \'Show Total Count\',' . "\n" .
        '                            checked: attributes.showTotal,' . "\n" .
        '                            onChange: function(value) {' . "\n" .
        '                                props.setAttributes({ showTotal: value });' . "\n" .
        '                            }' . "\n" .
        '                        })' . "\n" .
        '                    )' . "\n" .
        '                ),' . "\n" .
        '                el(ServerSideRender, {' . "\n" .
        '                    block: \'social-bridge/likes-collage\',' . "\n" .
        '                    attributes: attributes' . "\n" .
        '                })' . "\n" .
        '            ];' . "\n" .
        '        },' . "\n" .
        '        ' . "\n" .
        '        save: function() {' . "\n" .
        '            return null; // Dynamic block, server-side rendered' . "\n" .
        '        }' . "\n" .
        '    });' . "\n" .
        '})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components);';
    
    // Write the file
    file_put_contents($js_file, $content);
}

/**
 * Create likes collage block CSS file
 */
function social_bridge_create_likes_collage_block_css() {
    $css_dir = SOCIAL_BRIDGE_PLUGIN_DIR . 'assets/css';
    
    // Create directory if it doesn't exist
    if (!file_exists($css_dir)) {
        wp_mkdir_p($css_dir);
    }
    
    $css_file = $css_dir . '/likes-collage-block.css';
    
    // Don't overwrite existing file
    if (file_exists($css_file)) {
        return;
    }
    
    $content = 
        '.social-bridge-likes-collage-wrapper {' . "\n" .
        '    margin: 20px 0;' . "\n" .
        '}' . "\n" .
        '' . "\n" .
        '.social-bridge-likes-platform {' . "\n" .
        '    margin-bottom: 20px;' . "\n" .
        '}' . "\n" .
        '' . "\n" .
        '.social-bridge-likes-count {' . "\n" .
        '    margin-bottom: 10px;' . "\n" .
        '    font-size: 16px;' . "\n" .
        '}' . "\n" .
        '' . "\n" .
        '.social-bridge-likes-count .dashicons {' . "\n" .
        '    vertical-align: middle;' . "\n" .
        '}' . "\n" .
        '' . "\n" .
        '.social-bridge-avatar-collage {' . "\n" .
        '    display: flex;' . "\n" .
        '    flex-wrap: wrap;' . "\n" .
        '    gap: 5px;' . "\n" .
        '}' . "\n" .
        '' . "\n" .
        '.social-bridge-avatar {' . "\n" .
        '    position: relative;' . "\n" .
        '}' . "\n" .
        '' . "\n" .
        '.social-bridge-avatar img {' . "\n" .
        '    border-radius: 50%;' . "\n" .
        '    border: 2px solid #fff;' . "\n" .
        '    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);' . "\n" .
        '}' . "\n" .
        '' . "\n" .
        '.social-bridge-avatar-more {' . "\n" .
        '    display: flex;' . "\n" .
        '    align-items: center;' . "\n" .
        '    justify-content: center;' . "\n" .
        '    background-color: #f0f0f0;' . "\n" .
        '    border-radius: 50%;' . "\n" .
        '    width: 48px;' . "\n" .
        '    height: 48px;' . "\n" .
        '    border: 2px solid #fff;' . "\n" .
        '    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);' . "\n" .
        '    font-size: 14px;' . "\n" .
        '    color: #666;' . "\n" .
        '}' . "\n" .
        '' . "\n" .
        '.social-bridge-likes-collage-empty {' . "\n" .
        '    font-style: italic;' . "\n" .
        '    color: #888;' . "\n" .
        '    padding: 10px;' . "\n" .
        '    background: #f9f9f9;' . "\n" .
        '    border: 1px solid #eee;' . "\n" .
        '    border-radius: 3px;' . "\n" .
        '}';
    
    // Write the file
    file_put_contents($css_file, $content);
}

/**
 * Create block assets on plugin activation
 */
function social_bridge_create_block_assets() {
    social_bridge_create_likes_collage_block_js();
    social_bridge_create_likes_collage_block_css();
}
register_activation_hook(SOCIAL_BRIDGE_PLUGIN_BASENAME, 'social_bridge_create_block_assets');

// Create block assets on first run
add_action('admin_init', function() {
    if (get_option('social_bridge_block_assets_created') !== '1') {
        social_bridge_create_block_assets();
        update_option('social_bridge_block_assets_created', '1');
    }
}); 