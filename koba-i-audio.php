<?php
/**
 * Plugin Name: KOBA-I Audio - Jubilee Edition
 * Version: 5.0.0 (Agnostic SaaS Release)
 * Description: Tier-1 Audiobook & Video Player with E-Reader Cloud Studio. Universal Agnostic Embed.
 * Author: Kendall Aaron
 * Text Domain: Jubilee Works 
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1. CONSTANTS
define( 'KOBA_IA_PATH', plugin_dir_path( __FILE__ ) );
define( 'KOBA_IA_URL', plugin_dir_url( __FILE__ ) );

// 2. GITHUB AUTO-UPDATER
require_once KOBA_IA_PATH . 'includes/updater.php';
if ( class_exists( 'KobaAudioUpdater' ) ) {
    $updater = new KobaAudioUpdater( __FILE__ );
    $updater->set_username( 'kendallaaron84-ai' );
    $updater->set_repository( 'https://raw.githubusercontent.com/kendallaaron84-ai/koba-i-audio-firebase-connection/main/info.json' );
    $updater->initialize();
}

// 3. LOAD THE UNIVERSAL SHORTCODE INJECTOR
// (Strictly bypassing legacy PHP controllers, focusing solely on the React Cloud Engine)
require_once KOBA_IA_PATH . 'includes/shortcodes-v2.php';


// ==========================================
// 4. ADMIN GATEWAY MENU
// ==========================================
add_action('admin_menu', 'koba_register_gateway_menu');
function koba_register_gateway_menu() {
    add_menu_page(
        'Jubilee Gateway',
        'Jubilee Gateway',
        'manage_options',
        'koba-license',
        'koba_render_license_page',
        'dashicons-cloud',
        30
    );
}

function koba_render_license_page() {
    $license_key = get_option('koba_license_key', '');
    // Defaulting to the new Vercel Command Center URL
    $api_url = get_option('koba_api_url', 'https://bug-free-robot-khaki.vercel.app');
    $status = get_option('koba_license_status', 'inactive');
    
    ?>
    <div class="wrap" style="max-width: 600px; margin-top: 40px; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h1 style="border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px;">KOBA-I Studio Gateway</h1>
        
        <?php if (isset($_GET['success'])): ?>
            <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px;">Settings saved securely. Connection to Command Center established.</div>
        <?php endif; ?>

        <?php if (isset($_GET['disconnected'])): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">Gateway connection severed.</div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="koba_activate_license">
            <?php wp_nonce_field('koba_license_nonce', 'koba_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="koba_key">Studio Key</label></th>
                    <td>
                        <input name="koba_key" type="text" id="koba_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" placeholder="KOBA-AUDIO-XXXX" required>
                        <p class="description">Your unique KOBA-I Studio authorization token.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="koba_api_url">Command Center URL</label></th>
                    <td>
                        <input name="koba_api_url" type="url" id="koba_api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" required>
                        <p class="description">The secure URL of your Next.js Engine (e.g. https://bug-free-robot-khaki.vercel.app).</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Connect to Studio Cloud">
            </p>
        </form>

        <?php if ($status === 'active'): ?>
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #e2e8f0;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="koba_deactivate_license">
                <?php wp_nonce_field('koba_license_nonce', 'koba_nonce'); ?>
                <p>Status: <strong style="color: #10b981;">Connected</strong></p>
                <input type="submit" name="submit" class="button button-secondary" value="Disconnect Site" style="color: #ef4444; border-color: #ef4444;">
            </form>
        <?php endif; ?>
    </div>
    <?php
}


// ==========================================
// 5. SETTINGS FORM HANDLERS (With Nonce Security)
// ==========================================
add_action('admin_post_koba_activate_license', 'koba_process_license_activation');
function koba_process_license_activation() {
    if (!current_user_can('manage_options') || !isset($_POST['koba_nonce']) || !wp_verify_nonce($_POST['koba_nonce'], 'koba_license_nonce')) {
        wp_die('Unauthorized attempt.');
    }
    
    if (!empty($_POST['koba_key'])) {
        update_option('koba_license_key', sanitize_text_field($_POST['koba_key']));
        update_option('koba_license_status', 'active');
    }
    
    if (!empty($_POST['koba_api_url'])) {
        update_option('koba_api_url', sanitize_text_field(rtrim($_POST['koba_api_url'], '/')));
    }
    
    wp_redirect(admin_url('admin.php?page=koba-license&success=true'));
    exit;
}

add_action('admin_post_koba_deactivate_license', 'koba_process_license_deactivation');
function koba_process_license_deactivation() {
    if (!current_user_can('manage_options') || !isset($_POST['koba_nonce']) || !wp_verify_nonce($_POST['koba_nonce'], 'koba_license_nonce')) {
        wp_die('Unauthorized attempt.');
    }
    
    delete_option('koba_license_key');
    update_option('koba_license_status', 'inactive');
    
    wp_redirect(admin_url('admin.php?page=koba-license&disconnected=true'));
    exit;
}