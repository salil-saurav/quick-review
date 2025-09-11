<?php

/**
 * Plugin Name: Quick Review
 * Description: A simple plugin to generate Campaign Item.
 * Version: 1.0
 * Author: DWS
 */

if (!defined('ABSPATH')) exit;

define('QUICK_REVIEW_VERSION', '1.0');
define('QR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QR_PLUGIN_URL', plugin_dir_url(__FILE__));

define('QR_CAMPAIGN', 'qr_campaign');
define('QR_CAMPAIGN_ITEM', 'qr_campaign_item');
define('QR_SETTINGS_TABLE', 'qr_settings');

require_once QR_PLUGIN_DIR . 'includes/class-quick-review.php';

register_activation_hook(__FILE__, ['Quick_Review', 'activate']);
register_deactivation_hook(__FILE__, ['Quick_Review', 'deactivate']);

add_action('plugins_loaded', function () {
   (new Quick_Review())->init();
});
