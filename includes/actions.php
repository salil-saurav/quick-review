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


/**
 * Handle comment status changes for reference-based reviews.
 */
add_action('wp_set_comment_status', 'qr_handle_comment_status_change', 10, 2);
function qr_handle_comment_status_change($comment_id, $comment_status)
{
   global $wpdb;

   error_log("qr_handle_comment_status_change: Called for comment_id={$comment_id}, status={$comment_status}");

   $reference = get_comment_meta($comment_id, 'reference', true);
   error_log("qr_handle_comment_status_change: Reference meta = " . print_r($reference, true));
   if (empty($reference)) {
      error_log("qr_handle_comment_status_change: No reference found, aborting.");
      return;
   }

   $campaign_item_table = $wpdb->prefix . QR_REVIEW_CAMPAIGN_ITEM;
   $campaign_table      = $wpdb->prefix . QR_REVIEW_CAMPAIGN;
   $comment             = get_comment($comment_id);
   $comment_post_id     = $comment ? $comment->comment_post_ID : 0;

   error_log("qr_handle_comment_status_change: campaign_item_table={$campaign_item_table}, campaign_table={$campaign_table}, comment_post_id={$comment_post_id}");

   // Validate campaign
   $campaign_item = $wpdb->get_row(
      $wpdb->prepare(
         "SELECT ci.reference, ci.count, ci.campaign_id, c.start_date, c.end_date, c.post_id
       FROM $campaign_item_table ci
       INNER JOIN $campaign_table c ON ci.campaign_id = c.id
       WHERE ci.reference = %s
         AND c.post_id = %d
         AND (c.start_date IS NULL OR c.start_date <= NOW())
         AND (c.end_date IS NULL OR c.end_date >= NOW())",
         $reference,
         $comment_post_id
      ),
      ARRAY_A
   );


   error_log("qr_handle_comment_status_change: campaign_item = " . print_r($campaign_item, true));

   if (!$campaign_item) {
      error_log("qr_handle_comment_status_change: No valid campaign item found, aborting.");
      return;
   }

   // If just approved → increment
   if ($comment_status === 'approve') {
      $result = $wpdb->query(
         $wpdb->prepare(
            "UPDATE $campaign_item_table
                 SET `count` = COALESCE(`count`, 0) + 1
                 WHERE reference = %s",
            $reference
         )
      );
      error_log("qr_handle_comment_status_change: Incremented count for reference={$reference}, result={$result}");
   }
   // If changed from approved → something else → decrement
   else {
      $result = $wpdb->query(
         $wpdb->prepare(
            "UPDATE $campaign_item_table
                 SET `count` = GREATEST(COALESCE(`count`, 0) - 1, 0)
                 WHERE reference = %s",
            $reference
         )
      );
      error_log("qr_handle_comment_status_change: Decremented count for reference={$reference}, result={$result}");
   }
}

/**
 * Handle comment deletion → decrement if approved.
 */
add_action('delete_comment', 'qr_handle_comment_deletion');
function qr_handle_comment_deletion($comment_id)
{
   global $wpdb;

   $comment = get_comment($comment_id);
   if (!$comment || $comment->comment_approved !== '1') {
      return; // only decrement if it was approved
   }

   $reference = get_comment_meta($comment_id, 'reference', true);
   if (empty($reference)) {
      return;
   }

   $campaign_item_table = $wpdb->prefix . QR_REVIEW_CAMPAIGN_ITEM;

   $wpdb->query(
      $wpdb->prepare(
         "UPDATE $campaign_item_table
             SET `count` = GREATEST(COALESCE(`count`, 0) - 1, 0)
             WHERE reference = %s",
         $reference
      )
   );
}

require_once QR_PLUGIN_DIR . 'includes/actions/reference-service.php';
