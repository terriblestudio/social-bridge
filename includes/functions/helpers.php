<?php
/**
 * Helper Functions
 * 
 * Utility functions for the Social Bridge plugin.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Format a social media handle
 * 
 * @param string $handle The social media handle.
 * @param string $platform The platform ID.
 * @return string Formatted handle.
 */
function social_bridge_format_handle($handle, $platform) {
    switch ($platform) {
        case 'bluesky':
            return '@' . ltrim($handle, '@');
        
        case 'mastodon':
            return '@' . ltrim($handle, '@');
        
        default:
            return apply_filters('social_bridge_format_handle', $handle, $platform);
    }
}

/**
 * Get the profile URL for a social media handle
 * 
 * @param string $handle The social media handle.
 * @param string $platform The platform ID.
 * @return string Profile URL.
 */
function social_bridge_get_profile_url($handle, $platform) {
    switch ($platform) {
        case 'bluesky':
            return 'https://bsky.app/profile/' . ltrim($handle, '@');
        
        case 'mastodon':
            // For Mastodon, the handle should include the instance
            if (strpos($handle, '@') === 0) {
                $handle = substr($handle, 1);
            }
            
            if (strpos($handle, '@') !== false) {
                list($username, $instance) = explode('@', $handle);
                return 'https://' . $instance . '/@' . $username;
            }
            
            return '';
        
        default:
            return apply_filters('social_bridge_profile_url', '', $handle, $platform);
    }
}

/**
 * Format an interaction date
 * 
 * @param string $date The date string.
 * @param string $format Optional format string.
 * @return string Formatted date.
 */
function social_bridge_format_date($date, $format = '') {
    if (empty($format)) {
        $format = get_option('date_format') . ' ' . get_option('time_format');
    }
    
    $timestamp = strtotime($date);
    
    return date_i18n($format, $timestamp);
}

/**
 * Get a human-readable time difference
 * 
 * @param string $date The date string.
 * @return string Human-readable time difference.
 */
function social_bridge_time_diff($date) {
    $timestamp = strtotime($date);
    
    return human_time_diff($timestamp, current_time('timestamp'));
}

/**
 * Generate a collage of user avatars
 * 
 * @param array $users Array of user data with avatars.
 * @param int $max_users Maximum number of users to show.
 * @param int $size Avatar size in pixels.
 * @return string HTML for the avatar collage.
 */
function social_bridge_generate_avatar_collage($users, $max_users = 8, $size = 48) {
    if (empty($users)) {
        return '';
    }
    
    // Limit the number of users
    $total_users = count($users);
    $users = array_slice($users, 0, $max_users);
    
    $html = '<div class="social-bridge-avatar-collage">';
    
    foreach ($users as $user) {
        $avatar_url = isset($user['avatar']) ? $user['avatar'] : '';
        $name = isset($user['name']) ? $user['name'] : '';
        $url = isset($user['url']) ? $user['url'] : '';
        
        if (empty($avatar_url)) {
            continue;
        }
        
        $html .= '<div class="social-bridge-avatar">';
        if (!empty($url)) {
            $html .= '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr($name) . '">';
        }
        $html .= '<img src="' . esc_url($avatar_url) . '" alt="' . esc_attr($name) . '" width="' . esc_attr($size) . '" height="' . esc_attr($size) . '" loading="lazy">';
        if (!empty($url)) {
            $html .= '</a>';
        }
        $html .= '</div>';
    }
    
    // Show remaining count if needed
    if ($total_users > $max_users) {
        $remaining = $total_users - $max_users;
        $html .= '<div class="social-bridge-avatar social-bridge-avatar-more">';
        $html .= '<span>+' . esc_html($remaining) . '</span>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Get the platform name from its ID
 * 
 * @param string $platform_id The platform ID.
 * @return string Platform name.
 */
function social_bridge_get_platform_name($platform_id) {
    $integration = social_bridge_get_integration($platform_id);
    
    if ($integration) {
        return $integration->get_platform_name();
    }
    
    switch ($platform_id) {
        case 'bluesky':
            return 'Bluesky';
        
        case 'mastodon':
            return 'Mastodon';
        
        default:
            return apply_filters('social_bridge_platform_name', ucfirst($platform_id), $platform_id);
    }
}

/**
 * Get the platform icon from its ID
 * 
 * @param string $platform_id The platform ID.
 * @return string Platform icon CSS class.
 */
function social_bridge_get_platform_icon($platform_id) {
    $integration = social_bridge_get_integration($platform_id);
    
    if ($integration) {
        return $integration->get_platform_icon();
    }
    
    switch ($platform_id) {
        case 'bluesky':
            return 'dashicons-cloud';
        
        case 'mastodon':
            return 'dashicons-share';
        
        default:
            return apply_filters('social_bridge_platform_icon', 'dashicons-share-alt', $platform_id);
    }
}

/**
 * Parse content for mentions, hashtags, and links
 * 
 * @param string $content The content to parse.
 * @param string $platform The platform ID.
 * @return string Parsed content with HTML links.
 */
function social_bridge_parse_content($content, $platform) {
    // Convert mentions to links
    $content = preg_replace_callback('/@([a-zA-Z0-9_.]+)/', function($matches) use ($platform) {
        $handle = $matches[1];
        $url = social_bridge_get_profile_url($handle, $platform);
        
        if (!empty($url)) {
            return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">@' . esc_html($handle) . '</a>';
        }
        
        return $matches[0];
    }, $content);
    
    // Convert hashtags to links
    $content = preg_replace_callback('/#([a-zA-Z0-9_]+)/', function($matches) use ($platform) {
        $tag = $matches[1];
        $url = '';
        
        switch ($platform) {
            case 'bluesky':
                $url = 'https://bsky.app/search?q=%23' . $tag;
                break;
            
            case 'mastodon':
                $instance_url = get_option('social_bridge_mastodon_instance_url', '');
                if (!empty($instance_url)) {
                    $url = rtrim($instance_url, '/') . '/tags/' . $tag;
                }
                break;
        }
        
        if (!empty($url)) {
            return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">#' . esc_html($tag) . '</a>';
        }
        
        return $matches[0];
    }, $content);
    
    // Convert URLs to links if not already linked
    $content = preg_replace_callback('#(?<!href=["|\'])https?://[^\s<]+#i', function($matches) {
        $url = $matches[0];
        return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a>';
    }, $content);
    
    return $content;
} 