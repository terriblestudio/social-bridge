<?php
/**
 * Mastodon Integration Class
 * 
 * Integrates WordPress with Mastodon social platform.
 */

if (!class_exists('Social_Bridge_Integration')) {
    return;
}

class Social_Bridge_Mastodon_Integration extends Social_Bridge_Integration {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->platform_id = 'mastodon';
        $this->platform_name = 'Mastodon';
        $this->platform_icon = 'dashicons-share';
        
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
            'social-bridge-mastodon',
            SOCIAL_BRIDGE_PLUGIN_URL . 'assets/css/mastodon.css',
            array(),
            SOCIAL_BRIDGE_VERSION
        );
    }
    
    /**
     * Register admin assets
     */
    public function register_admin_assets() {
        wp_register_style(
            'social-bridge-mastodon-admin',
            SOCIAL_BRIDGE_PLUGIN_URL . 'assets/css/mastodon-admin.css',
            array(),
            SOCIAL_BRIDGE_VERSION
        );
        
        wp_register_script(
            'social-bridge-mastodon-admin',
            SOCIAL_BRIDGE_PLUGIN_URL . 'assets/js/mastodon-admin.js',
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
        $instance_url = get_option('social_bridge_mastodon_instance_url');
        $access_token = get_option('social_bridge_mastodon_access_token');
        
        return !empty($instance_url) && !empty($access_token);
    }
    
    /**
     * Register integration settings
     */
    public function register_settings() {
        // Register settings section
        add_settings_section(
            'social_bridge_mastodon_settings',
            __('Mastodon Settings', 'social-bridge'),
            array($this, 'render_settings_section'),
            'social-bridge'
        );
        
        // Register settings fields
        // phpcs:disable PluginCheck.CodeAnalysis.SettingSanitization
        // Dynamic arguments are appropriately sanitized with sanitize_callback functions.
        register_setting(
            'social-bridge',
            'social_bridge_mastodon_instance_url',
            array(
                'type' => 'string',
                'description' => 'Mastodon instance URL',
                'sanitize_callback' => 'esc_url_raw'
            )
        );
        register_setting(
            'social-bridge',
            'social_bridge_mastodon_access_token',
            array(
                'type' => 'string',
                'description' => 'Mastodon API access token',
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
        // phpcs:enable
        
        // Add settings fields
        add_settings_field(
            'social_bridge_mastodon_instance_url',
            __('Instance URL', 'social-bridge'),
            array($this, 'render_instance_url_field'),
            'social-bridge',
            'social_bridge_mastodon_settings'
        );
        
        add_settings_field(
            'social_bridge_mastodon_access_token',
            __('Access Token', 'social-bridge'),
            array($this, 'render_access_token_field'),
            'social-bridge',
            'social_bridge_mastodon_settings'
        );
    }
    
    /**
     * Render settings section
     */
    public function render_settings_section() {
        echo '<p>' . esc_html(__('Connect your Mastodon account by providing your instance URL and access token below.', 'social-bridge')) . '</p>';
    }
    
    /**
     * Render instance URL field
     */
    public function render_instance_url_field() {
        $instance_url = get_option('social_bridge_mastodon_instance_url', '');
        ?>
        <input type="url" name="social_bridge_mastodon_instance_url" value="<?php echo esc_attr($instance_url); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e('Enter your Mastodon instance URL, e.g. https://mastodon.social', 'social-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * Render access token field
     */
    public function render_access_token_field() {
        $access_token = get_option('social_bridge_mastodon_access_token', '');
        ?>
        <input type="password" name="social_bridge_mastodon_access_token" value="<?php echo esc_attr($access_token); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e('Enter your Mastodon access token. You can generate one in Mastodon under Preferences > Development > New Application.', 'social-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * Parse Mastodon post ID from URL
     * 
     * @param string $url The Mastodon post URL.
     * @return array|false Post data or false if invalid URL.
     */
    public function parse_post_url($url) {
        // Example URL: https://mastodon.social/@username/109501347585025040
        $pattern = '#(https?://[^/]+)/@?([^/]+)/([0-9]+)#';
        
        if (preg_match($pattern, $url, $matches)) {
            return array(
                'instance_url' => $matches[1],
                'username' => $matches[2],
                'post_id' => $matches[3]
            );
        }
        
        return false;
    }
    
    /**
     * Make authenticated request to Mastodon API
     * 
     * @param string $endpoint API endpoint.
     * @param array $params Request parameters.
     * @param string $method HTTP method (GET or POST).
     * @param string $instance_url Optional instance URL override.
     * @return array|WP_Error Response data or error.
     */
    protected function api_request($endpoint, $params = array(), $method = 'GET', $instance_url = null) {
        // Get instance URL and access token
        $instance_url = $instance_url ?: get_option('social_bridge_mastodon_instance_url');
        $access_token = get_option('social_bridge_mastodon_access_token');
        
        if (empty($instance_url) || empty($access_token)) {
            return new WP_Error('authentication_error', __('Mastodon API credentials not configured', 'social-bridge'));
        }
        
        // Ensure instance URL has no trailing slash
        $instance_url = rtrim($instance_url, '/');
        
        // Build request URL
        $url = $instance_url . '/api/v1/' . ltrim($endpoint, '/');
        
        // Prepare request arguments
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
            'timeout' => 15,
        );
        
        // Add parameters based on method
        if ($method === 'POST') {
            $args['method'] = 'POST';
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = json_encode($params);
        } else {
            // Add query params for GET requests
            if (!empty($params)) {
                $url = add_query_arg($params, $url);
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
        if ($response_code < 200 || $response_code >= 300) {
            $error_message = wp_remote_retrieve_response_message($response);
            /* translators: %1$s is the error message, %2$d is the response code */
            return new WP_Error('api_error', sprintf(__('Mastodon API error: %1$s (%2$d)', 'social-bridge'), $error_message, $response_code));
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
     * Get status data from Mastodon
     * 
     * @param string $post_id Post ID.
     * @param string $instance_url Instance URL.
     * @return array|WP_Error Post data or error.
     */
    public function get_status_data($post_id, $instance_url = null) {
        return $this->api_request('statuses/' . $post_id, array(), 'GET', $instance_url);
    }
    
    /**
     * Get context (replies) for a status
     * 
     * @param string $post_id Post ID.
     * @param string $instance_url Instance URL.
     * @return array|WP_Error Context data or error.
     */
    public function get_status_context($post_id, $instance_url = null) {
        return $this->api_request('statuses/' . $post_id . '/context', array(), 'GET', $instance_url);
    }
    
    /**
     * Get favourites for a status
     * 
     * @param string $post_id Post ID.
     * @param string $instance_url Instance URL.
     * @return array|WP_Error List of favourites or error.
     */
    public function get_status_favourites($post_id, $instance_url = null) {
        return $this->api_request('statuses/' . $post_id . '/favourited_by', array('limit' => 100), 'GET', $instance_url);
    }
    
    /**
     * Get reblogs for a status
     * 
     * @param string $post_id Post ID.
     * @param string $instance_url Instance URL.
     * @return array|WP_Error List of reblogs or error.
     */
    public function get_status_reblogs($post_id, $instance_url = null) {
        return $this->api_request('statuses/' . $post_id . '/reblogged_by', array('limit' => 100), 'GET', $instance_url);
    }
    
    /**
     * Sync comments for a specific post
     *
     * @param int $post_id The WordPress post ID.
     * @return int|WP_Error Number of comments synced or error.
     */
    public function sync_post_comments($post_id) {
        // Get Mastodon post URL from post meta
        $post_url = get_post_meta($post_id, '_social_bridge_mastodon_url', true);
        if (empty($post_url)) {
            return new WP_Error('no_url', __('No Mastodon post URL found for this post', 'social-bridge'));
        }
        
        // Parse post URL
        $parsed_url = $this->parse_post_url($post_url);
        if (!$parsed_url) {
            return new WP_Error('invalid_url', __('Invalid Mastodon post URL', 'social-bridge'));
        }
        
        // Get status data from Mastodon
        $status_data = $this->get_status_data($parsed_url['post_id'], $parsed_url['instance_url']);
        if (is_wp_error($status_data)) {
            return $status_data;
        }
        
        // Get context data (replies)
        $context_data = $this->get_status_context($parsed_url['post_id'], $parsed_url['instance_url']);
        if (is_wp_error($context_data)) {
            return $context_data;
        }
        
        // Get favourites
        $favourites_data = $this->get_status_favourites($parsed_url['post_id'], $parsed_url['instance_url']);
        if (is_wp_error($favourites_data)) {
            return $favourites_data;
        }
        
        // Get reblogs
        $reblogs_data = $this->get_status_reblogs($parsed_url['post_id'], $parsed_url['instance_url']);
        if (is_wp_error($reblogs_data)) {
            return $reblogs_data;
        }
        
        // Track stats
        $synced_count = 0;
        
        // Process replies
        if (isset($context_data['descendants']) && is_array($context_data['descendants'])) {
            $synced_count += $this->process_replies($post_id, $context_data['descendants']);
        }
        
        // Process favourites
        if (is_array($favourites_data)) {
            $synced_count += $this->process_favourites($post_id, $favourites_data);
        }
        
        // Process reblogs
        if (is_array($reblogs_data)) {
            $synced_count += $this->process_reblogs($post_id, $reblogs_data);
        }
        
        return $synced_count;
    }
    
    /**
     * Process replies from a Mastodon context
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
            // Skip replies that aren't direct replies to the post or its replies
            // if (!in_array($post_uri, $reply['in_reply_to_id'], true)) {
            //     continue;
            // }
            
            // Prepare interaction data
            $interaction_id = $reply['id'];
            $interaction_type = 'comment';
            $interaction_data = array(
                'author_name' => $reply['account']['display_name'] ?: $reply['account']['username'],
                'author_url' => $reply['account']['url'],
                'author_avatar' => $reply['account']['avatar'],
                'content' => wp_strip_all_tags($reply['content']),
                'created_at' => $reply['created_at'],
                'raw_data' => $reply
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
        }
        
        return $count;
    }
    
    /**
     * Process favourites for a post
     * 
     * @param int $post_id The WordPress post ID.
     * @param array $favourites Array of favourite data.
     * @return int Number of favourites processed.
     */
    protected function process_favourites($post_id, $favourites) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'social_bridge_interactions';
        $count = 0;
        
        foreach ($favourites as $favourite) {
            // Prepare interaction data
            $interaction_id = $favourite['id'];
            $interaction_type = 'like';
            $interaction_data = array(
                'author_name' => $favourite['display_name'] ?: $favourite['username'],
                'author_url' => $favourite['url'],
                'author_avatar' => $favourite['avatar'],
                /* translators: %s is the platform name */
                'content' => sprintf(__('Liked this post on %s', 'social-bridge'), 'Mastodon'),
                'created_at' => gmdate('Y-m-d H:i:s'), // We don't get a timestamp for likes
                'raw_data' => $favourite
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
     * Process reblogs for a post
     * 
     * @param int $post_id The WordPress post ID.
     * @param array $reblogs Array of reblog data.
     * @return int Number of reblogs processed.
     */
    protected function process_reblogs($post_id, $reblogs) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'social_bridge_interactions';
        $count = 0;
        
        foreach ($reblogs as $account) {
            // Prepare interaction data
            $interaction_id = 'reblog_' . $account['id'];
            $interaction_type = 'share';
            $interaction_data = array(
                'author_name' => $account['display_name'] ?: $account['username'],
                'author_url' => $account['url'],
                'author_avatar' => $account['avatar'],
                /* translators: %s is the display name of the account which boosted the post */
                'content' => sprintf(__('%s boosted this post on Mastodon', 'social-bridge'), $account['display_name'] ?: $account['username']),
                'created_at' => current_time('mysql'),
                'raw_data' => $account
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
        // Get all posts with Mastodon URLs
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_social_bridge_mastodon_url',
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

if (!function_exists('social_bridge_mastodon_register_integration')) {
// Initialize the Mastodon integration
    add_filter('social_bridge_integrations', 'social_bridge_mastodon_register_integration');
    /**
     * Register Mastodon integration
     *
     * @param array $integrations Existing integrations
     * @return array Updated integrations
     */
    function social_bridge_mastodon_register_integration($integrations)
    {
        $integrations['mastodon'] = new Social_Bridge_Mastodon_Integration();
        return $integrations;
    }
}
