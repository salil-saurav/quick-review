<?php

/**
 * Plugin Name: Quick Review
 * Description: A simple plugin to generate review URLs.
 * Version: 1.0
 * Author: DWS
 */

if (!defined('ABSPATH')) exit;

define('QUICK_REVIEW_VERSION', '1.0');
define('QUICK_REVIEW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('QUICK_REVIEW_PLUGIN_URL', plugin_dir_url(__FILE__));

define('QR_REVIEW_CAMPAIGN', 'review_campaign');
define('QR_REVIEW_CAMPAIGN_ITEM', 'review_campaign_item');
define('QR_SETTINGS_TABLE', 'quick_review_settings');

require_once QUICK_REVIEW_PLUGIN_DIR . 'includes/class-quick-review.php';

register_activation_hook(__FILE__, ['Quick_Review', 'activate']);
register_deactivation_hook(__FILE__, ['Quick_Review', 'deactivate']);

add_action('plugins_loaded', function () {
   (new Quick_Review())->init();
});
