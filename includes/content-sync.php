<?php
/**
 * KOBA-I Audio Content Sync
 * Pulls completed AI blog blueprints from the Next.js Command Center and inserts them as native WP Posts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KOBA_Content_Sync {

    public function __construct() {
        // Hook into WP-Cron to run automatically (e.g., hourly)
        add_action('koba_pull_content_cron', [$this, 'pull_and_publish_content']);
        
        // Allow manual trigger via an Admin AJAX button
        add_action('wp_ajax_koba_manual_content_sync', [$this, 'manual_sync_handler']);
    }

    /**
     * Retrieves the dynamic API endpoint
     */
    private function get_api_endpoint() {
        $base_url = function_exists('koba_get_command_center_url') 
            ? koba_get_command_center_url() 
            : get_option('koba_api_url', 'https://bug-free-robot-khaki.vercel.app');
            
        return rtrim($base_url, '/') . '/api/content/public';
    }

    /**
     * The core sync engine
     */
    public function pull_and_publish_content() {
        // 1. 🚀 FIX: Grab the securely stored License Key (was 'koba_studio_key')
        $studio_key = get_option('koba_license_key');
        if (empty($studio_key)) {
            return false;
        }

        // 2. 🚀 FIX: Safely fetch the data from Next.js using dynamic routing, bypassing WAF firewalls
        $api_endpoint = $this->get_api_endpoint();
        $response = wp_remote_get($api_endpoint . '?limit=5', [
            'headers' => [
                'X-Studio-Key' => $studio_key
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $blueprints = json_decode($body, true);

        if (empty($blueprints) || !is_array($blueprints)) {
            return 0; // Nothing new to sync
        }

        $imported_count = 0;

        foreach ($blueprints as $blueprint) {
            // 3. Prevent Duplicates
            $existing_post = get_posts([
                'meta_key'   => '_koba_blueprint_id',
                'meta_value' => sanitize_text_field($blueprint['id']),
                'post_type'  => 'post',
                'post_status'=> 'any',
                'fields'     => 'ids' // Only fetch the ID for speed
            ]);

            if (!empty($existing_post)) {
                continue; // Skip, we already published this one
            }

            // 4. Inject as a Native WordPress Post
            $post_data = [
                'post_title'   => sanitize_text_field($blueprint['title']),
                'post_content' => wp_kses_post($blueprint['body']), // Keeps safe HTML, strips malicious tags
                'post_excerpt' => sanitize_textarea_field($blueprint['excerpt']),
                'post_status'  => 'publish',
                'post_type'    => 'post',
                'post_author'  => 1 // You can dynamically map this to a specific WP user ID later
            ];

            $post_id = wp_insert_post($post_data);

            if (!is_wp_error($post_id)) {
                // Lock the blueprint ID to the post so it never duplicates
                update_post_meta($post_id, '_koba_blueprint_id', sanitize_text_field($blueprint['id']));
                $imported_count++;
            }
        }

        return $imported_count;
    }

    /**
     * AJAX handler for a manual "Sync Now" button in the WP Admin
     */
    public function manual_sync_handler() {
        check_ajax_referer('koba_admin_nonce', 'security');
        
        $count = $this->pull_and_publish_content();
        
        if ($count !== false) {
            wp_send_json_success(['imported' => $count]);
        } else {
            wp_send_json_error(['message' => 'Sync failed. Check connection.']);
        }
    }
}