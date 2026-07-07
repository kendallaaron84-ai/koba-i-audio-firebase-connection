<?php
/**
 * Jubilee Works: Universal Agnostic Shortcode Injector
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// 🚀 DYNAMIC ENVIRONMENT ROUTER
if (!function_exists('koba_get_command_center_url')) {
    function koba_get_command_center_url() {
        return get_option('koba_api_url', 'https://bug-free-robot-khaki.vercel.app');
    }
}

// Global flags for secure footer injection
global $koba_needs_catalog;
global $koba_needs_player;
$koba_needs_catalog = false;
$koba_needs_player = false;

// ==========================================
// 1. THE STOREFRONT CATALOG [jubilee_catalog]
// ==========================================
add_shortcode('jubilee_catalog', 'koba_universal_catalog_shortcode');
function koba_universal_catalog_shortcode($atts) {
    if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return '<div style="padding: 15px; border: 2px dashed #3b82f6; background: #eff6ff; color: #1d4ed8; text-align: center; border-radius: 8px; font-weight: bold;">📚 KOBA-I Catalog Mount Point<br><small>[jubilee_catalog]</small></div>';
    }

    $studio_key = get_option('koba_license_key', '');
    
    if (empty($studio_key)) {
        return '<p style="color:red; font-weight: bold;">KOBA-I Error: Studio Key not configured in plugin settings.</p>';
    }

    $args = shortcode_atts(array('scope' => 'tenant'), $atts);
    
    // 🚀 RAISE THE FLAG: Tells the wp_footer to safely inject the React engine
    global $koba_needs_catalog;
    $koba_needs_catalog = true;

    // Build the visual DOM node (No scripts inline!)
    $html = sprintf(
        '<div id="jubilee-catalog-root" class="koba-agnostic-embed" data-studio-key="%s" data-scope="%s" style="min-height: 400px; width: 100%%; position: relative;">
            <div style="color: #64748b; text-align:center; padding:80px 20px; font-family: system-ui;">
                <div class="jubilee-spinner" style="width:30px; height:30px; border:3px solid #e2e8f0; border-top-color:#3b82f6; border-radius:50%%; display:inline-block; animation: spin 1s linear infinite;"></div>
                <div style="margin-top: 15px; font-weight: 500;">Loading Book Catalog...</div>
                <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
            </div>
        </div>',
        esc_attr($studio_key),
        esc_attr($args['scope'])
    );

    return $html;
}

// ==========================================
// 2. THE MEDIA PLAYER [koba_media_player asset="abk_..."]
// ==========================================
add_shortcode('koba_media_player', 'koba_universal_player_shortcode');
function koba_universal_player_shortcode($atts) {
    if (is_admin()) return '<div style="padding:15px; border:2px dashed #f97316;">🎧 KOBA-I Player Mount Point</div>';

    $args = shortcode_atts( array('asset' => ''), $atts );
    
    if (empty($args['asset'])) {
        return '<p style="color:red;">KOBA-I Error: Missing asset ID.</p>';
    }

    $studio_key = get_option('koba_license_key', '');
    
    // 🚀 RAISE THE FLAG: Tells the wp_footer to safely inject the React engine
    global $koba_needs_player;
    $koba_needs_player = true;

    $html = sprintf(
        '<div id="jubilee-bloom-root" class="koba-agnostic-embed" data-asset="%s" data-studio-key="%s" style="min-height: 400px; width: 100%%; position: relative;">
            <div style="color: #64748b; text-align:center; padding:80px 20px; font-family: system-ui;">
                <div class="jubilee-spinner" style="width:30px; height:30px; border:3px solid #e2e8f0; border-top-color:#f97316; border-radius:50%%; display:inline-block; animation: spin 1s linear infinite;"></div>
                <div style="margin-top: 15px; font-weight: 500;">Connecting to Secure Cloud Engine...</div>
                <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
            </div>
        </div>',
        esc_attr($args['asset']),
        esc_attr($studio_key)
    );

    return $html;
}

// ==========================================
// 3. THE BULLETPROOF FOOTER INJECTOR
// ==========================================
add_action('wp_footer', 'koba_inject_agnostic_scripts', 9999);
function koba_inject_agnostic_scripts() {
    global $koba_needs_catalog, $koba_needs_player;
    
    // Only run if the shortcode was actually used on this page
    if ($koba_needs_catalog || $koba_needs_player) {
        $dashboard_url = rtrim(koba_get_command_center_url(), '/');
        
        $config = array(
            'commandCenterUrl' => $dashboard_url,
            'endpoints' => array(
                'publicProduct' => $dashboard_url . '/api/products/public',
                'checkout'      => $dashboard_url . '/api/checkout',
                'verifyAccess'  => $dashboard_url . '/api/verify-entitlement',
                'authSmsSend'   => $dashboard_url . '/api/auth/sms-send',
                'authSmsVerify' => $dashboard_url . '/api/auth/sms-verify'
            )
        );
        
        // Print raw HTML directly into the footer, completely avoiding Divi filters
        ?>
        <script type="text/javascript">
            window.JubileeConfig = <?php echo wp_json_encode($config); ?>;
        </script>
        <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
        <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
        <script src="<?php echo esc_url($dashboard_url . '/assets/jubilee-core.js?v=' . time()); ?>"></script>
        <?php
    }
}