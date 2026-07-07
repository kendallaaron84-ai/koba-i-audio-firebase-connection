<?php
/**
 * KOBA-I Audio Content Sync
 * Pulls completed AI blog blueprints from the Next.js Command Center and inserts them as native WP Posts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KOBA_Content_Sync {

    // The endpoint we just built in Next.js
    private $api_endpoint = 'https://dashboard.koba-i.com/api/content/public'; 

    public function __construct() {
        // Hook into WP-Cron to run automatically (e.g., hourly)
        add_action('koba_pull_content_cron', [$this, 'pull_and_publish_content']);
        
        // Allow manual trigger via an Admin AJAX button
        add_action('wp_ajax_koba_manual_content_sync', [$this, 'manual_sync_handler']);
    }

    /**
     * The core sync engine
     */
    public function pull_and_publish_content() {
        // 1. Get the securely stored Tenant Key
        $studio_key = get_option('koba_studio_key');
        if (empty($studio_key)) {
            return false;
        }

        // 2. Safely fetch the data from Next.js, bypassing inbound WAF firewalls
        $response = wp_remote_get($this->api_endpoint . '?limit=5', [
            'headers' => [
                'X-Studio-Key' => $studio_key,
                'Accept'       => 'application/json'
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            error_log('KOBA Sync Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['success']) || empty($data['content'])) {
            return false;
        }

        $imported_count = 0;

        // 3. Iterate through the AI-generated posts
        foreach ($data['content'] as $blueprint) {
            
            // 4. Deduplication Check: Does this post already exist?
            $existing_post = get_posts([
                'post_type'  => 'post',
                'meta_key'   => '_koba_blueprint_id',
                'meta_value' => sanitize_text_field($blueprint['id']),
                'fields'     => 'ids' // Only get the ID for speed
            ]);

            if (!empty($existing_post)) {
                continue; // Skip, we already published this one
            }

            // 5. Inject as a Native WordPress Post
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
        
        wp_send_json_success(['message' => "Sync complete. Imported {$count} new posts."]);
    }
}

new KOBA_Content_Sync();