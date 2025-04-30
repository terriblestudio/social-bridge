<?php
/**
 * API Endpoints
 * 
 * REST API endpoints for Social Bridge.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register REST API endpoints
 */
function social_bridge_register_rest_routes() {
    register_rest_route('social-bridge/v1', '/sync/(?P<post_id>\d+)', array(
        'methods' => 'POST',
        'callback' => 'social_bridge_rest_sync_post',
        'permission_callback' => 'social_bridge_rest_permissions_check',
        'args' => array(
            'post_id' => array(
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
            'platform' => array(
                'validate_callback' => function($param) {
                    return empty($param) || in_array($param, array_keys(social_bridge_get_integrations()));
                }
            )
        )
    ));
    
    register_rest_route('social-bridge/v1', '/likes/(?P<post_id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'social_bridge_rest_get_likes',
        'permission_callback' => 'social_bridge_rest_view_permissions_check',
        'args' => array(
            'post_id' => array(
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
            'platform' => array(
                'validate_callback' => function($param) {
                    return empty($param) || in_array($param, array_keys(social_bridge_get_integrations()));
                }
            )
        )
    ));
    
    register_rest_route('social-bridge/v1', '/comments/(?P<post_id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'social_bridge_rest_get_comments',
        'permission_callback' => 'social_bridge_rest_view_permissions_check',
        'args' => array(
            'post_id' => array(
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
            'platform' => array(
                'validate_callback' => function($param) {
                    return empty($param) || in_array($param, array_keys(social_bridge_get_integrations()));
                }
            )
        )
    ));
}
add_action('rest_api_init', 'social_bridge_register_rest_routes');

/**
 * Check permissions for private REST API endpoints
 * 
 * @param WP_REST_Request $request Request object.
 * @return bool|WP_Error True if user can edit posts, error otherwise.
 */
function social_bridge_rest_permissions_check($request) {
    if (!current_user_can('edit_posts')) {
        return new WP_Error(
            'rest_forbidden',
            __('Sorry, you are not allowed to access this endpoint.', 'social-bridge'),
            array('status' => 403)
        );
    }
    
    return true;
}

/**
 * Check permissions for public REST API endpoints
 *
 * @param WP_REST_Request $request Request object.
 * @return bool|WP_Error True if user can read the post, error otherwise.
 */
function social_bridge_rest_view_permissions_check($request) {
    $post_id = $request->get_param('post_id');
    if ((get_post_status($post_id) !== 'publish') || post_password_required($post_id)) {
        if (!current_user_can('read_post', $post_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('Sorry, you are not allowed to access this endpoint.', 'social-bridge'),
                array('status' => 403)
            );
        }
    }

    return true;
}

/**
 * Handle REST API sync request
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function social_bridge_rest_sync_post($request) {
    $post_id = $request->get_param('post_id');
    $platform = $request->get_param('platform');
    
    $post = get_post($post_id);
    
    if (!$post) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => __('Invalid post ID.', 'social-bridge')
        ), 404);
    }
    
    // Check if sync is already running
    if (social_bridge_is_sync_running()) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => __('A sync operation is already in progress. Please try again later.', 'social-bridge')
        ), 409);
    }
    
    // Run the sync
    $result = social_bridge_manual_sync($post_id, $platform);
    
    if (is_wp_error($result)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => $result->get_error_message()
        ), 500);
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => $result
    ), 200);
}

/**
 * Handle REST API get likes request
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function social_bridge_rest_get_likes($request) {
    $post_id = $request->get_param('post_id');
    $platform = $request->get_param('platform');
    
    $post = get_post($post_id);
    
    if (!$post) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => __('Invalid post ID.', 'social-bridge')
        ), 404);
    }
    
    $likes = social_bridge_get_post_likes($post_id, $platform);
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => $likes
    ), 200);
}

/**
 * Handle REST API get comments request
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function social_bridge_rest_get_comments($request) {
    $post_id = $request->get_param('post_id');
    $platform = $request->get_param('platform');
    
    $post = get_post($post_id);
    
    if (!$post) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => __('Invalid post ID.', 'social-bridge')
        ), 404);
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'social_bridge_interactions';
    
    $where = $wpdb->prepare("post_id = %d AND interaction_type = %s", $post_id, 'comment');
    
    if ($platform) {
        $where .= $wpdb->prepare(" AND platform = %s", $platform);
    }
    
    $interactions = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE $where ORDER BY interaction_date DESC"
    );
    
    $comments = array();
    
    foreach ($interactions as $interaction) {
        $data = json_decode($interaction->interaction_data, true);
        
        $comments[] = array(
            'id' => $interaction->id,
            'platform' => $interaction->platform,
            'platform_name' => social_bridge_get_platform_name($interaction->platform),
            'platform_icon' => social_bridge_get_platform_icon($interaction->platform),
            'author' => array(
                'name' => $data['author_name'],
                'url' => $data['author_url'],
                'avatar' => $data['author_avatar']
            ),
            'content' => $data['content'],
            'date' => $interaction->interaction_date,
            'date_formatted' => social_bridge_format_date($interaction->interaction_date),
            'date_relative' => social_bridge_time_diff($interaction->interaction_date)
        );
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => $comments
    ), 200);
} 