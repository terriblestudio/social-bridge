<?php
/**
 * Bluesky Integration Class
 * 
 * Integrates WordPress with Bluesky social platform.
 */

if (!class_exists('Social_Bridge_Integration')) {
    return;
}

class Social_Bridge_Bluesky_Integration extends Social_Bridge_Integration {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->platform_id = 'bluesky';
        $this->platform_name = 'Bluesky';
        $this->platform_icon = 'dashicons-cloud';
        
        // Call parent constructor
        parent::__construct();
    }
    
    /**
     * Initialize the integration
     */
    protected function init() {
        // Register assets
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
        
        // Register cron action for syncing comments
        add_action('social_bridge_sync_comments', array($this, 'sync_comments'));
    }
    
    /**
     * Register frontend assets
     */
    public function register_assets() {
        wp_register_style(
            'social-bridge-bluesky',
            SOCIAL_BRIDGE_PLUGIN_URL . 'assets/css/bluesky.css',
            array(),
            SOCIAL_BRIDGE_VERSION
        );
    }
    
    /**
     * Register admin assets
     */
    public function register_admin_assets() {
        wp_register_style(
            'social-bridge-bluesky-admin',
            SOCIAL_BRIDGE_PLUGIN_URL . 'assets/css/bluesky-admin.css',
            array(),
            SOCIAL_BRIDGE_VERSION
        );
        
        wp_register_script(
            'social-bridge-bluesky-admin',
            SOCIAL_BRIDGE_PLUGIN_URL . 'assets/js/bluesky-admin.js',
            array('jquery'),
            SOCIAL_BRIDGE_VERSION,
            true
        );
    }
    
    /**
     * Check if the integration is properly configured
     *
     * @return bool
     */
    public function check_configuration() {
        $handle = get_option('social_bridge_bluesky_handle');
        $app_password = get_option('social_bridge_bluesky_app_password');
        
        return !empty($handle) && !empty($app_password);
    }
    
    /**
     * Register integration settings
     */
    public function register_settings() {
        // Register settings section
        add_settings_section(
            'social_bridge_bluesky_settings',
            __('Bluesky Settings', 'social-bridge'),
            array($this, 'render_settings_section'),
            'social-bridge'
        );
        
        // Register settings fields
        register_setting(
            'social-bridge',
            'social_bridge_bluesky_handle',
            array(
                'type' => 'string',
                'description' => 'Bluesky handle',
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        register_setting(
            'social-bridge',
            'social_bridge_bluesky_app_password',
            array(
                'type' => 'string',
                'description' => 'Bluesky app password',
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        
        // Add settings fields
        add_settings_field(
            'social_bridge_bluesky_handle',
            __('Bluesky Handle', 'social-bridge'),
            array($this, 'render_bluesky_handle_field'),
            'social-bridge',
            'social_bridge_bluesky_settings'
        );
        
        add_settings_field(
            'social_bridge_bluesky_app_password',
            __('App Password', 'social-bridge'),
            array($this, 'render_bluesky_app_password_field'),
            'social-bridge',
            'social_bridge_bluesky_settings'
        );
    }
    
    /**
     * Render settings section
     */
    public function render_settings_section() {
        echo '<p>' . esc_html(__('Connect your Bluesky account by providing your handle and app password below.', 'social-bridge')) . '</p>';
    }
    
    /**
     * Render handle field
     */
    public function render_bluesky_handle_field() {
        $handle = get_option('social_bridge_bluesky_handle', '');
        ?>
        <input type="text" name="social_bridge_bluesky_handle" value="<?php echo esc_attr($handle); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e('Enter your Bluesky handle, e.g. username.bsky.social', 'social-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * Render app password field
     */
    public function render_bluesky_app_password_field() {
        $app_password = get_option('social_bridge_bluesky_app_password', '');
        ?>
        <input type="password" name="social_bridge_bluesky_app_password" value="<?php echo esc_attr($app_password); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e('Enter your Bluesky app password. You can generate one in your Bluesky account settings.', 'social-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * Parse Bluesky post ID from URL
     * 
     * @param string $url The Bluesky post URL.
     * @return array|false Post data or false if invalid URL.
     */
    public function parse_post_url($url) {
        // Example URL: https://bsky.app/profile/username.bsky.social/post/3kf5xigv4kd2r
        $pattern = '#bsky\.app/profile/([^/]+)/post/([^/]+)#';
        
        if (preg_match($pattern, $url, $matches)) {
            return array(
                'author' => $matches[1],
                'post_id' => $matches[2]
            );
        }
        
        return false;
    }
    
    /**
     * Make authenticated request to Bluesky API
     * 
     * @param string $endpoint API endpoint.
     * @param array $data Request data.
     * @param string $method HTTP method (GET or POST).
     * @return array|WP_Error Response data or error.
     */
    protected function api_request($endpoint, $data = array(), $method = 'GET') {
        $handle = get_option('social_bridge_bluesky_handle');
        $app_password = get_option('social_bridge_bluesky_app_password');
        
        if (empty($handle) || empty($app_password)) {
            return new WP_Error('authentication_error', __('Bluesky API credentials not configured', 'social-bridge'));
        }
        
        // Bluesky ATP API base URL
        $base_url = 'https://bsky.social/xrpc/';
        $url = $base_url . $endpoint;
        
        // Prepare request arguments
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        );
        
        // Get authentication token if needed
        static $auth_token = null;
        
        if ($auth_token === null) {
            $auth_result = $this->get_auth_token($handle, $app_password);
            
            if (is_wp_error($auth_result)) {
                return $auth_result;
            }
            
            $auth_token = $auth_result;
        }
        
        // Add auth token to headers
        $args['headers']['Authorization'] = 'Bearer ' . $auth_token;
        
        // Add data to request based on method
        if ($method === 'POST') {
            $args['method'] = 'POST';
            $args['body'] = json_encode($data);
        } else {
            // Add query params for GET requests
            if (!empty($data)) {
                $url = add_query_arg($data, $url);
            }
        }
        
        // Make the request
        $response = wp_remote_request($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_message = wp_remote_retrieve_response_message($response);
            /* translators: %1$s is the error message, %2$d is the response code */
            return new WP_Error('api_error', sprintf(__('Bluesky API error: %1$s (%2$d)', 'social-bridge'), $error_message, $response_code));
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Error parsing API response', 'social-bridge'));
        }
        
        return $data;
    }
    
    /**
     * Get authentication token
     * 
     * @param string $handle Bluesky handle.
     * @param string $app_password App password.
     * @return string|WP_Error Authentication token or error.
     */
    protected function get_auth_token($handle, $app_password) {
        // Create session
        $url = 'https://bsky.social/xrpc/com.atproto.server.createSession';
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'identifier' => $handle,
                'password' => $app_password
            )),
            'timeout' => 15,
        );
        
        $response = wp_remote_request($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_message = wp_remote_retrieve_response_message($response);
            /* translators: %1$s is the handle, %2$s is the error message */
            return new WP_Error('did_resolution_error', sprintf(__('Could not resolve DID for handle %1$s: %2$s', 'social-bridge'), $handle, $error_message));
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['accessJwt'])) {
            return new WP_Error('auth_error', __('Invalid authentication response', 'social-bridge'));
        }
        
        return $data['accessJwt'];
    }
    
    /**
     * Get post data from Bluesky
     * 
     * @param string $author Post author handle.
     * @param string $post_id Post ID.
     * @return array|WP_Error Post data or error.
     */
    public function get_post_data($author, $post_id) {
        return $this->api_request('app.bsky.feed.getPostThread', array(
            'uri' => "at://{$author}/app.bsky.feed.post/{$post_id}",
            'depth' => 5
        ));
    }
    
    /**
     * Get likes for a post
     * 
     * @param string $author Post author handle.
     * @param string $post_id Post ID.
     * @return array|WP_Error List of likes or error.
     */
    public function get_post_likes_data($author, $post_id) {
        return $this->api_request('app.bsky.feed.getLikes', array(
            'uri' => "at://{$author}/app.bsky.feed.post/{$post_id}",
            'limit' => 100
        ));
    }
    
    /**
     * Get reposts for a post
     * 
     * @param string $author Post author handle.
     * @param string $post_id Post ID.
     * @return array|WP_Error List of reposts or error.
     */
    public function get_post_reposts_data($author, $post_id) {
        return $this->api_request('app.bsky.feed.getRepostedBy', array(
            'uri' => "at://{$author}/app.bsky.feed.post/{$post_id}",
            'limit' => 100
        ));
    }
    
    /**
     * Sync comments for a specific post
     *
     * @param int $post_id The WordPress post ID.
     * @return int|WP_Error Number of comments synced or error.
     */
    public function sync_post_comments($post_id) {
        // Get Bluesky post URL from post meta
        $post_url = get_post_meta($post_id, '_social_bridge_bluesky_url', true);
        if (empty($post_url)) {
            return new WP_Error('no_url', __('No Bluesky post URL found for this post', 'social-bridge'));
        }
        
        // Parse post URL
        $parsed_url = $this->parse_post_url($post_url);
        if (!$parsed_url) {
            return new WP_Error('invalid_url', __('Invalid Bluesky post URL', 'social-bridge'));
        }
        
        // Get post data from Bluesky
        $post_data = $this->get_post_data($parsed_url['author'], $parsed_url['post_id']);
        if (is_wp_error($post_data)) {
            return $post_data;
        }
        
        // Get likes
        $likes_data = $this->get_post_likes_data($parsed_url['author'], $parsed_url['post_id']);
        if (is_wp_error($likes_data)) {
            return $likes_data;
        }
        
        // Get reposts
//        $reposts_data = $this->get_post_reposts_data($parsed_url['author'], $parsed_url['post_id']);
//        if (is_wp_error($reposts_data)) {
//            return $reposts_data;
//        }
        
        // Process comments, likes, and reposts
        $thread = isset($post_data['thread']) ? $post_data['thread'] : null;
        if (!$thread) {
            return new WP_Error('thread_error', __('Could not retrieve post thread', 'social-bridge'));
        }
        
        // Track stats
        $synced_count = 0;
        
        // Process replies
        if (isset($thread['replies']) && is_array($thread['replies'])) {
            $synced_count += $this->process_replies($post_id, $thread['replies']);
        }
        
        // Process likes
        if (isset($likes_data['likes']) && is_array($likes_data['likes'])) {
            $synced_count += $this->process_likes($post_id, $likes_data['likes']);
        }
        
        // Process reposts
        if (isset($reposts_data['repostedBy']) && is_array($reposts_data['repostedBy'])) {
            $synced_count += $this->process_reposts($post_id, $reposts_data['repostedBy']);
        }
        
        return $synced_count;
    }

    /**
     * Process replies from a Bluesky thread
     * 
     * @param int $post_id The WordPress post ID.
     * @param array $replies Array of reply data.
     * @return int Number of replies processed.
     */
    protected function process_replies($post_id, $replies) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'social_bridge_interactions';
        $count = 0;
        
        foreach ($replies as $reply) {
            if (!isset($reply['post'])) {
                continue;
            }
            
            $post = $reply['post'];
            $author = $reply['post']['author'];
            
            // Prepare interaction data
            $interaction_id = $post['uri'];
            $interaction_type = 'comment';
            $interaction_data = array(
                'author_name' => $author['displayName'] ?? $author['handle'],
                'author_url' => 'https://bsky.app/profile/' . $author['handle'],
                'author_avatar' => $author['avatar'] ?? '',
                'content' => $post['record']['text'] ?? '',
                'created_at' => $post['indexedAt'] ?? current_time('mysql'),
                'raw_data' => $post
            );
            
            // Check if interaction already exists
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE platform = %s AND interaction_id = %s",
                    $this->platform_id,
                    $interaction_id
                )
            );
            
            if ($existing) {
                // Update existing record
                $wpdb->update(
                    $table_name,
                    array(
                        'interaction_data' => json_encode($interaction_data),
                        'interaction_date' => $interaction_data['created_at']
                    ),
                    array(
                        'id' => $existing
                    )
                );
            } else {
                // Insert new record
                $wpdb->insert(
                    $table_name,
                    array(
                        'post_id' => $post_id,
                        'platform' => $this->platform_id,
                        'interaction_type' => $interaction_type,
                        'interaction_id' => $interaction_id,
                        'interaction_data' => json_encode($interaction_data),
                        'interaction_date' => $interaction_data['created_at'],
                        'comment_id' => 0
                    )
                );
                
                $count++;
            }
            
            // Process replies recursively
            if (isset($reply['replies']) && is_array($reply['replies'])) {
                $count += $this->process_replies($post_id, $reply['replies']);
            }
        }
        
        return $count;
    }
    
    /**
     * Process likes for a post
     * 
     * @param int $post_id The WordPress post ID.
     * @param array $likes Array of like data.
     * @return int Number of likes processed.
     */
    protected function process_likes($post_id, $likes) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'social_bridge_interactions';
        $count = 0;
        
        foreach ($likes as $like) {
            $actor = $like['actor'];
            
            // Prepare interaction data
            $interaction_id = $like['createdAt'] . '_' . $actor['handle'];
            $interaction_type = 'like';
            $interaction_data = array(
                'author_name' => $actor['displayName'] ?? $actor['handle'],
                'author_url' => 'https://bsky.app/profile/' . $actor['handle'],
                'author_avatar' => $actor['avatar'] ?? '',
                'created_at' => $like['createdAt'] ?? current_time('mysql'),
                'raw_data' => $like
            );
            
            // Check if interaction already exists
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE platform = %s AND interaction_id = %s",
                    $this->platform_id,
                    $interaction_id
                )
            );
            
            if (!$existing) {
                // Insert new record
                $wpdb->insert(
                    $table_name,
                    array(
                        'post_id' => $post_id,
                        'platform' => $this->platform_id,
                        'interaction_type' => $interaction_type,
                        'interaction_id' => $interaction_id,
                        'interaction_data' => json_encode($interaction_data),
                        'interaction_date' => $interaction_data['created_at'],
                        'comment_id' => 0
                    )
                );
                
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Process reposts for a post
     * 
     * @param int $post_id The WordPress post ID.
     * @param array $reposts Array of repost data.
     * @return int Number of reposts processed.
     */
    protected function process_reposts($post_id, $reposts) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'social_bridge_interactions';
        $count = 0;
        
        foreach ($reposts as $repost) {
            $actor = $repost;
            
            // Prepare interaction data
            $interaction_id = 'repost_' . $actor['handle'];
            $interaction_type = 'share';
            $interaction_data = array(
                'author_name' => $actor['displayName'] ?? $actor['handle'],
                'author_url' => 'https://bsky.app/profile/' . $actor['handle'],
                'author_avatar' => $actor['avatar'] ?? '',
                /* translators: %s is the platform name */
                'content' => sprintf(__('Liked this post on %s', 'social-bridge'), 'Bluesky'),
                'created_at' => current_time('mysql'),
                'raw_data' => $repost
            );
            
            // Check if interaction already exists
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE platform = %s AND interaction_id = %s",
                    $this->platform_id,
                    $interaction_id
                )
            );
            
            if (!$existing) {
                // Insert new record
                $wpdb->insert(
                    $table_name,
                    array(
                        'post_id' => $post_id,
                        'platform' => $this->platform_id,
                        'interaction_type' => $interaction_type,
                        'interaction_id' => $interaction_id,
                        'interaction_data' => json_encode($interaction_data),
                        'interaction_date' => $interaction_data['created_at'],
                        'comment_id' => 0
                    )
                );
                
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Sync comments for all posts
     */
    public function sync_comments() {
        // Get all posts with Bluesky URLs
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_social_bridge_bluesky_url',
                    'value' => '',
                    'compare' => '!='
                )
            )
        ));
        
        foreach ($posts as $post) {
            $this->sync_post_comments($post->ID);
        }
    }
    
    /**
     * Get all users who liked a post
     *
     * @param int $post_id The WordPress post ID.
     * @return array Array of user data.
     */
    public function get_post_likes($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'social_bridge_interactions';
        
        $likes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE post_id = %d AND platform = %s AND interaction_type = %s",
                $post_id,
                $this->platform_id,
                'like'
            )
        );
        
        $users = array();
        
        foreach ($likes as $like) {
            $data = json_decode($like->interaction_data, true);
            
            $users[] = array(
                'name' => $data['author_name'],
                'url' => $data['author_url'],
                'avatar' => $data['author_avatar'],
                'date' => $like->interaction_date
            );
        }
        
        return $users;
    }
}

if (!function_exists('social_bridge_bluesky_register_integration')) {
// Initialize the Bluesky integration
    add_filter('social_bridge_integrations', 'social_bridge_bluesky_register_integration');
    /**
     * Register Bluesky integration
     *
     * @param array $integrations Existing integrations
     * @return array Updated integrations
     */
    function social_bridge_bluesky_register_integration($integrations)
    {
        $integrations['bluesky'] = new Social_Bridge_Bluesky_Integration();
        return $integrations;
    }
}
