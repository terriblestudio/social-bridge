<?php
/**
 * Social Integration Base Class
 * 
 * Abstract class that defines the structure for social media platform integrations.
 */

abstract class Social_Bridge_Integration {
    
    /**
     * Platform ID (lowercase, alphanumeric only)
     *
     * @var string
     */
    protected $platform_id;
    
    /**
     * Platform display name
     *
     * @var string
     */
    protected $platform_name;
    
    /**
     * Platform icon CSS class
     *
     * @var string
     */
    protected $platform_icon;
    
    /**
     * Whether the integration is properly configured
     *
     * @var bool
     */
    protected $is_configured = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Register meta box
        add_action('add_meta_boxes', array($this, 'register_meta_box'));
        
        // Save post meta
        add_action('save_post', array($this, 'save_meta_box_data'));
        
        // Initialize integration
        $this->init();
        
        // Check if integration is configured
        $this->is_configured = $this->check_configuration();
        
        // Filter comments to include social interactions
        add_filter('the_comments', array($this, 'filter_comments'), 10, 2);
        
        // Filter comment counts
        add_filter('get_comments_number', array($this, 'filter_comment_count'), 10, 2);
    }
    
    /**
     * Initialize the integration
     */
    protected function init() {
        // To be implemented by child classes
    }
    
    /**
     * Check if the integration is properly configured
     *
     * @return bool
     */
    abstract public function check_configuration();
    
    /**
     * Register integration settings
     */
    abstract public function register_settings();
    
    /**
     * Register meta box for post editor
     */
    public function register_meta_box() {
        add_meta_box(
            'social-bridge-' . $this->platform_id,
            /* translators: %s is the platform name */
            sprintf(__('%s Integration', 'social-bridge'), $this->platform_name),
            array($this, 'render_meta_box'),
            'post',
            'side',
            'default'
        );
        
        // Also add to pages
        add_meta_box(
            'social-bridge-' . $this->platform_id,
            /* translators: %s is the platform name */
            sprintf(__('%s Integration', 'social-bridge'), $this->platform_name),
            array($this, 'render_meta_box'),
            'page',
            'side',
            'default'
        );
    }
    
    /**
     * Render meta box content
     *
     * @param WP_Post $post The post object.
     */
    public function render_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('social_bridge_' . $this->platform_id . '_meta_box', 'social_bridge_' . $this->platform_id . '_meta_box_nonce');
        
        // Get saved value
        $post_url = get_post_meta($post->ID, '_social_bridge_' . $this->platform_id . '_url', true);
        
        // Output field
        ?>
        <p>
            <label for="social_bridge_<?php echo esc_attr($this->platform_id); ?>_url">
                <?php 
                /* translators: %s is the platform name */
                echo esc_html(sprintf(__('%s Post URL:', 'social-bridge'), $this->platform_name)); 
                ?>
            </label>
            <input type="url" id="social_bridge_<?php echo esc_attr($this->platform_id); ?>_url" 
                name="social_bridge_<?php echo esc_attr($this->platform_id); ?>_url" 
                value="<?php echo esc_attr($post_url); ?>" class="widefat" 
                placeholder="<?php 
                /* translators: %s is the platform name */
                echo esc_attr(sprintf(__('Enter %s URL', 'social-bridge'), $this->platform_name)); 
                ?>">
        </p>
        <?php if (!empty($post_url)) : ?>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=social-bridge-sync&platform=' . $this->platform_id . '&post_id=' . $post->ID)); ?>" class="button">
                    <?php esc_html_e('Sync Comments Now', 'social-bridge'); ?>
                </a>
            </p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Save meta box data
     *
     * @param int $post_id The post ID.
     */
    public function save_meta_box_data($post_id) {
        // Check if nonce is set
        $nonce_name = 'social_bridge_' . $this->platform_id . '_meta_box_nonce';
        if (!isset($_POST[$nonce_name])) {
            return;
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput
        // The value is sanitized before being passed to wp_verify_nonce, although as the value is
        // never echoed back to the client anyways, sanitization is likely not needed.
        // Verify that the nonce is valid
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_name])), 'social_bridge_' . $this->platform_id . '_meta_box')) {
            return;
        }
        // phpcs:enable
        
        // If this is an autosave, we don't want to do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check the user's permissions
        if (isset($_POST['post_type']) && 'page' === $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }
        
        // Sanitize and save the post URL
        $field_name = 'social_bridge_' . $this->platform_id . '_url';
        if (isset($_POST[$field_name])) {
            $post_url = sanitize_url(wp_unslash([$field_name]));
            update_post_meta($post_id, '_' . $field_name, $post_url);
            
            // If URL changed and not empty, schedule a sync
            if (!empty($post_url)) {
                $this->schedule_post_sync($post_id);
            }
        }
    }
    
    /**
     * Schedule a comment sync for a specific post
     *
     * @param int $post_id The post ID.
     */
    public function schedule_post_sync($post_id) {
        wp_schedule_single_event(time() + 60, 'social_bridge_sync_post_comments', array(
            'platform' => $this->platform_id,
            'post_id' => $post_id
        ));
    }
    
    /**
     * Sync comments for a specific post
     *
     * @param int $post_id The post ID.
     * @return int|WP_Error Number of comments synced or error.
     */
    abstract public function sync_post_comments($post_id);
    
    /**
     * Filter comments to include social interactions
     *
     * @param array $comments Array of comment objects.
     * @param int $post_id The post ID.
     * @return array Modified array of comment objects.
     */
    public function filter_comments($comments, $query) {
        // If integration is not configured, return original comments
        if (!$this->is_configured) {
            return $comments;
        }

        if (!is_singular() || !is_main_query()) {
            return $comments;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return $comments;
        }
        
        // Get social URL for this post
        $post_url = get_post_meta($post_id, '_social_bridge_' . $this->platform_id . '_url', true);
        if (empty($post_url)) {
            return $comments;
        }
        
        // Get social interactions from database and merge with comments
        global $wpdb;
        $table_name = $wpdb->prefix . 'social_bridge_interactions';
        
        $interactions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE post_id = %d AND platform = %s AND comment_id = 0",
                $post_id,
                $this->platform_id
            )
        );
        
        if (empty($interactions)) {
            return $comments;
        }

        $types = get_option('social_bridge_comment_types', array('comment' => '1', 'share' => '1', 'like' => '1'));

        if (!is_array($types)) {
            $types = array('comment' => '1', 'share' => '1', 'like' => '1');
        }
        
        // Convert interactions to comment objects and merge
        foreach ($interactions as $interaction) {
            $interaction_data = json_decode($interaction->interaction_data, true);
            foreach ($comments as $comment) {
                if ($comment->content != null && $interaction_data['content'] != null && strcmp($comment->content, $interaction_data['content']) === 0) {
                    return $comments;
                }
            }

            if (!array_key_exists($interaction->interaction_type, $types) || $types[$interaction->interaction_type] != '1' ) {
                continue;
            }

            $_comment = [
                //'comment_ID' => 'social_' . $this->platform_id . '_' . $interaction->id,
                'comment_ID' => crc32($interaction_data['author_url'] . $interaction_data['content']),
                'comment_post_ID' => $post_id,
                'comment_author' => ((isset($interaction_data['author_name']) && $interaction_data['author_name'] != "") ? $interaction_data['author_name'] : __('Social User', 'social-bridge')) . ' via ' . $this->platform_name,
                'comment_author_email' => '',
                'comment_author_url' => isset($interaction_data['author_url']) ? $interaction_data['author_url'] : '',
                'comment_author_IP' => '',
                'comment_date' => $interaction->interaction_date,
                //'comment_date_gmt' => $interaction->interaction_date_gmt,
                'comment_content' => isset($interaction_data['content']) ? $interaction_data['content'] : '',
                'comment_karma' => 0,
                'comment_approved' => 1,
                'comment_agent' => '',
                'comment_type' => ($interaction->interaction_type === 'share') ? 'pingback' : 'comment',
                'comment_parent' => 0,
                'user_id' => 0
            ];
            
            // Add to comments array
            $fake_comment = new WP_Comment((object)$_comment);
            wp_cache_add( $fake_comment->comment_ID, $fake_comment, 'comment' );
            array_unshift($comments, $fake_comment);
        }
        
        return $comments;
    }
    
    /**
     * Filter comment count to include social interactions
     *
     * @param int $count The comment count.
     * @param int $post_id The post ID.
     * @return int Modified comment count.
     */
    public function filter_comment_count($count, $post_id) {
        // If integration is not configured, return original count
        if (!$this->is_configured) {
            return $count;
        }
        
        // Get social URL for this post
        $post_url = get_post_meta($post_id, '_social_bridge_' . $this->platform_id . '_url', true);
        if (empty($post_url)) {
            return $count;
        }
        
        // Get social interactions count from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'social_bridge_interactions';
        
        $interaction_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE post_id = %d AND platform = %s AND comment_id = 0",
                $post_id,
                $this->platform_id
            )
        );
        
        return $count + (int) $interaction_count;
    }
    
    /**
     * Get the platform ID
     *
     * @return string
     */
    public function get_platform_id() {
        return $this->platform_id;
    }
    
    /**
     * Get the platform name
     *
     * @return string
     */
    public function get_platform_name() {
        return $this->platform_name;
    }
    
    /**
     * Get the platform icon
     *
     * @return string
     */
    public function get_platform_icon() {
        return $this->platform_icon;
    }
    
    /**
     * Check if integration is configured
     *
     * @return bool
     */
    public function is_configured() {
        return $this->is_configured;
    }
    
    /**
     * Get all users who liked a post
     *
     * @param int $post_id The post ID.
     * @return array Array of user data.
     */
    abstract public function get_post_likes($post_id);
} 