<?php

/**
 * Quick Review Plugin Actions
 *
 * This file contains core action hooks and functions for the Quick Review plugin.
 *
 * Features:
 * - Redirects admin to setup wizard after plugin activation.
 * - Handles activation form submission, saves selected post types to custom settings table.
 * - Creates or updates plugin settings in a custom database table.
 * - Listens for comment approval events and increments review counts for campaign items.
 *
 * Functions:
 * - create_or_update_qr_settings(array $data): Creates or updates plugin settings in a custom table.
 * - on_comment_approved(int $comment_id, string $comment_status): Increments review count for campaign items when a comment is approved.
 *
 * Action Hooks:
 * - admin_init: Handles activation redirect and form submission.
 * - wp_set_comment_status: Triggers review count increment on comment approval.
 *
 * Database Tables:
 * - QR_SETTINGS_TABLE: Stores plugin settings (option_name, option_value).
 * - QR_REVIEW_CAMPAIGN_ITEM: Stores campaign items (reference, count, campaign_id).
 * - QR_REVIEW_CAMPAIGN: Stores campaigns (id, post_id, end_date, status).
 *
 * Security:
 * - Uses nonce verification for form submissions.
 * - Sanitizes user input before saving to database.
 *
 * @package QuickReview
 */

// Redirect after activation
add_action('admin_init', function () {
   if (get_option('qrs_do_activation_redirect', false)) {
      delete_option('qrs_do_activation_redirect');
      if (!isset($_GET['activate-multi'])) {
         wp_redirect(admin_url('admin.php?page=qrs-setup-wizard'));
         exit;
      }
   }
});

// Handle activation form submission
add_action('admin_init', function () {
   if (
      isset($_POST['selected_post_type'], $_POST['qrs_form_nonce']) &&
      wp_verify_nonce($_POST['qrs_form_nonce'], 'qrs_save_activation_form')
   ) {
      $selected_post_types = array_map('sanitize_text_field', (array) $_POST['selected_post_type']);

      $saved = create_or_update_qr_settings([
         'post_types'    => $selected_post_types,
      ]);

      if ($saved) {
         add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>Quick Review settings saved.</p></div>';
         });
      }
      wp_redirect(admin_url('admin.php?page=quick-review'));
      exit;
   }
});

// Create or update plugin settings
function create_or_update_qr_settings($data = [])
{
   global $wpdb;
   $table_name = $wpdb->prefix . QR_SETTINGS_TABLE;

   if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
      $charset_collate = $wpdb->get_charset_collate();
      $sql = "CREATE TABLE $table_name (
         id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
         option_name VARCHAR(100) NOT NULL UNIQUE,
         option_value LONGTEXT NOT NULL,
         PRIMARY KEY (id)
      ) $charset_collate;";
      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      dbDelta($sql);
   }

   return $wpdb->replace($table_name, [
      'option_name'  => 'post_types',
      'option_value' => maybe_serialize($data),
   ]) !== false;
}


add_action('wp_set_comment_status', 'on_comment_approved', 10, 2);

function on_comment_approved($comment_id, $comment_status)
{
   if ($comment_status !== 'approve') {
      return;
   }

   $reference = get_comment_meta($comment_id, 'reference', true);
   if (empty($reference)) {
      return;
   }

   global $wpdb;

   $campaign_item_table = $wpdb->prefix . QR_REVIEW_CAMPAIGN_ITEM;
   $campaign_table      = $wpdb->prefix . QR_REVIEW_CAMPAIGN;
   $comment_post_id     = get_comment($comment_id)->comment_post_ID;

   // Get campaign item by reference
   $campaign_item = $wpdb->get_row(
      $wpdb->prepare(
         "SELECT * FROM $campaign_item_table WHERE `reference` = %s",
         $reference
      ),
      ARRAY_A
   );
   if (!$campaign_item || empty($campaign_item['campaign_id'])) {
      return;
   }

   $campaign_id = (int) $campaign_item['campaign_id'];

   // Get campaign by ID and validate it
   $campaign = $wpdb->get_row(
      $wpdb->prepare(
         "SELECT * FROM $campaign_table WHERE `id` = %d AND `post_id` = %d AND `status` = %s AND (`end_date` IS NULL OR `end_date` > NOW())",
         $campaign_id,
         $comment_post_id,
         'published'
      ),
      ARRAY_A
   );

   if (!$campaign) {
      return;
   }

   // All conditions satisfied: increment the count
   $current_count = isset($campaign_item['count']) && is_numeric($campaign_item['count'])
      ? (int) $campaign_item['count']
      : 0;

   $wpdb->update(
      $campaign_item_table,
      ['count' => $current_count + 1],
      ['reference' => $reference],
      ['%d'],
      ['%s']
   );
}
