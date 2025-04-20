<?php
/**
 * Meta Boxes
 * 
 * Post editor meta boxes for Social Bridge.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Add meta box for social interactions in post editor sidebar
 */
function social_bridge_add_interactions_meta_box() {
    add_meta_box(
        'social-bridge-interactions',
        __('Social Interactions', 'social-bridge'),
        'social_bridge_render_interactions_meta_box',
        array('post', 'page'),
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'social_bridge_add_interactions_meta_box');

/**
 * Render social interactions meta box
 * 
 * @param WP_Post $post The post object.
 */
function social_bridge_render_interactions_meta_box($post) {
    // Get post URLs
    $post_urls = social_bridge_get_post_urls($post->ID);
    
    // Get like counts
    $platform_likes = social_bridge_get_post_likes($post->ID);
    
    ?>
    <div class="social-bridge-interactions">
        <?php if (!empty($post_urls)) : ?>
            <p>
                <?php esc_html_e('Connected social media posts:', 'social-bridge'); ?>
            </p>
            
            <ul class="social-bridge-post-urls">
                <?php foreach ($post_urls as $platform_id => $data) : ?>
                    <li>
                        <span class="dashicons <?php echo esc_attr($data['icon']); ?>"></span>
                        <a href="<?php echo esc_url($data['url']); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($data['name']); ?>
                        </a>
                        
                        <?php if (isset($platform_likes[$platform_id])) : ?>
                            <span class="social-bridge-like-count">
                                (<?php echo count($platform_likes[$platform_id]['users']); ?> <?php esc_html_e('likes', 'social-bridge'); ?>)
                            </span>
                        <?php endif; ?>
                        
                        <div class="social-bridge-actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=social-bridge-sync&platform=' . $platform_id . '&post_id=' . $post->ID)); ?>" class="button button-small">
                                <?php esc_html_e('Sync', 'social-bridge'); ?>
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p>
                <?php esc_html_e('No social media posts connected yet. Add URLs in the platform-specific meta boxes below.', 'social-bridge'); ?>
            </p>
        <?php endif; ?>
    </div>
    
    <style>
        .social-bridge-post-urls {
            margin: 0;
        }
        .social-bridge-post-urls li {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        .social-bridge-post-urls li:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .social-bridge-post-urls .dashicons {
            vertical-align: middle;
            margin-right: 3px;
        }
        .social-bridge-actions {
            margin-top: 5px;
        }
        .social-bridge-like-count {
            font-size: 90%;
            opacity: 0.7;
        }
    </style>
    <?php
}

/**
 * Add meta box for viewing social comments in post editor
 */
function social_bridge_add_comments_meta_box() {
    // Check if post has social URLs
    $screen = get_current_screen();
    
    if ($screen && in_array($screen->base, array('post')) && isset($_GET['post'])) {
        $post_id = intval($_GET['post']);
        $post_urls = social_bridge_get_post_urls($post_id);
        
        if (!empty($post_urls)) {
            add_meta_box(
                'social-bridge-comments',
                __('Social Comments', 'social-bridge'),
                'social_bridge_render_comments_meta_box',
                null,
                'normal',
                'default'
            );
        }
    }
}
add_action('add_meta_boxes', 'social_bridge_add_comments_meta_box');

/**
 * Render social comments meta box
 * 
 * @param WP_Post $post The post object.
 */
function social_bridge_render_comments_meta_box($post) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'social_bridge_interactions';
    
    // Get comments for this post (only comment type)
    $interactions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d AND interaction_type = %s ORDER BY interaction_date DESC",
            $post->ID,
            'comment'
        )
    );
    
    if (empty($interactions)) {
        echo '<p>' . esc_html(__('No social comments found for this post.', 'social-bridge')) . '</p>';
        
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=social-bridge-sync&post_id=' . $post->ID)) . '" class="button">';
        echo esc_html(__('Sync Comments Now', 'social-bridge'));
        echo '</a></p>';
        
        return;
    }
    
    ?>
    <div class="social-bridge-comments-wrapper">
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=social-bridge-sync&post_id=' . $post->ID)); ?>" class="button">
                <?php esc_html_e('Sync Comments Now', 'social-bridge'); ?>
            </a>
        </p>
        
        <div class="social-bridge-comments">
            <?php foreach ($interactions as $interaction) : ?>
                <?php
                $data = json_decode($interaction->interaction_data, true);
                $platform = $interaction->platform;
                $platform_name = social_bridge_get_platform_name($platform);
                $platform_icon = social_bridge_get_platform_icon($platform);
                ?>
                
                <div class="social-bridge-comment">
                    <div class="social-bridge-comment-author">
                        <?php if (!empty($data['author_avatar'])) : ?>
                            <img src="<?php echo esc_url($data['author_avatar']); ?>" alt="<?php echo esc_attr($data['author_name']); ?>" width="32" height="32" class="social-bridge-avatar" loading="lazy">
                        <?php endif; ?>
                        
                        <span class="social-bridge-author-name">
                            <?php if (!empty($data['author_url'])) : ?>
                                <a href="<?php echo esc_url($data['author_url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html($data['author_name']); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html($data['author_name']); ?>
                            <?php endif; ?>
                        </span>
                        
                        <span class="social-bridge-platform">
                            <span class="dashicons <?php echo esc_attr($platform_icon); ?>"></span>
                            <?php echo esc_html($platform_name); ?>
                        </span>
                        
                        <span class="social-bridge-comment-date">
                            <?php echo esc_html(social_bridge_time_diff($interaction->interaction_date)); ?> <?php esc_html_e('ago', 'social-bridge'); ?>
                        </span>
                    </div>
                    
                    <div class="social-bridge-comment-content">
                        <?php echo social_bridge_parse_content($data['content'], $platform); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <style>
        .social-bridge-comments {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            background: #f9f9f9;
        }
        .social-bridge-comment {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .social-bridge-comment:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .social-bridge-comment-author {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            flex-wrap: wrap;
        }
        .social-bridge-avatar {
            border-radius: 50%;
            margin-right: 10px;
        }
        .social-bridge-author-name {
            font-weight: bold;
            margin-right: 10px;
        }
        .social-bridge-platform {
            background: #eee;
            border-radius: 3px;
            padding: 2px 5px;
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            margin-right: 10px;
        }
        .social-bridge-platform .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            margin-right: 3px;
        }
        .social-bridge-comment-date {
            color: #777;
            font-size: 12px;
        }
        .social-bridge-comment-content {
            margin-left: 42px;
        }
    </style>
    <?php
} 