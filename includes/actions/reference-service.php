<?php

namespace QuickReview;

use Exception;

class ReferenceService
{
   private string $campaign_table;
   private string $campaign_item_table;
   private static bool $hooks_registered = false;

   public function __construct()
   {
      global $wpdb;

      $this->campaign_table      = $wpdb->prefix . QR_CAMPAIGN;
      $this->campaign_item_table = $wpdb->prefix . QR_CAMPAIGN_ITEM;

      $this->register_hooks();
   }

   /**
    * Register WordPress hooks
    */
   private function register_hooks(): void
   {
      if (self::$hooks_registered) {
         return;
      }

      // Handle creation
      add_action('qr_create_reference', [$this, 'qr_handle_create_reference'], 10, 2);

      // Track comment status transitions
      add_action('wp_set_comment_status', [$this, 'qr_handle_comment_status_change'], 10, 2);

      // Track comment deletion
      add_action('delete_comment', [$this, 'qr_handle_comment_deletion'], 10, 1);

      self::$hooks_registered = true;
   }

   /**
    * Handle qr_create_reference
    */
   public function qr_handle_create_reference($comment_id, $reference): void
   {
      try {
         $comment_id = (int) $comment_id;
         $reference  = sanitize_text_field($reference);

         if (empty($comment_id) || empty($reference)) {
            throw new Exception('Invalid comment ID or reference provided');
         }

         $comment = get_comment($comment_id);
         if (!$comment) {
            throw new Exception('Comment not found');
         }

         // Validate reference
         $campaign_data = $this->validate_reference_and_campaign($reference);

         if ($campaign_data) {
            // Save meta
            $this->save_reference_to_comment($comment_id, $reference, $campaign_data);

            // If already approved → increment immediately
            if ($comment->comment_approved === '1') {
               $this->increment_reference_count($reference);
            }

            do_action('qr_after_reference_created', $comment_id, $reference, $campaign_data);
         }
      } catch (Exception $e) {
         error_log('QuickReview Reference Creation Error: ' . $e->getMessage());
         do_action('qr_reference_creation_failed', $comment_id, $reference, $e->getMessage());
      }
   }

   /**
    * Save reference + campaign data in comment meta
    */
   private function save_reference_to_comment(int $comment_id, string $reference, array $campaign_data): void
   {
      update_comment_meta($comment_id, 'reference', $reference);
      update_comment_meta($comment_id, 'qr_campaign_id', $campaign_data['campaign_id']);
      update_comment_meta($comment_id, 'qr_campaign_name', $campaign_data['campaign_name']);
      update_comment_meta($comment_id, 'qr_reference_created_at', current_time('mysql'));
   }

   /**
    * Handle comment status transitions
    */
   public function qr_handle_comment_status_change(int $comment_id, string $new_status): void
   {
      $reference = get_comment_meta($comment_id, 'reference', true);
      if (empty($reference)) {
         return;
      }

      $comment = get_comment($comment_id);
      if (!$comment) {
         return;
      }

      $old_status = $comment->comment_approved;

      // Only act if something actually changed
      if ($old_status === '1' && $new_status !== 'approve') {
         $this->decrement_reference_count($reference);
      } elseif ($old_status !== '1' && $new_status === 'approve') {
         $this->increment_reference_count($reference);
      }
   }

   /**
    * Handle comment deletion (only if approved)
    */
   public function qr_handle_comment_deletion(int $comment_id): void
   {
      $reference = get_comment_meta($comment_id, 'reference', true);
      if (empty($reference)) {
         return;
      }

      $comment = get_comment($comment_id);
      if ($comment && $comment->comment_approved === '1') {
         $this->decrement_reference_count($reference);
      }
   }

   /**
    * Increment reference usage
    */
   private function increment_reference_count(string $reference): void
   {
      global $wpdb;

      $wpdb->query(
         $wpdb->prepare(
            "UPDATE {$this->campaign_item_table}
             SET `count` = COALESCE(`count`, 0) + 1,
                  usage_count = COALESCE(usage_count, 0) + 1,
                  used_at = %s
             WHERE reference = %s",
            current_time('mysql'),
            $reference
         )
      );
   }

   /**
    * Decrement reference usage
    */
   private function decrement_reference_count(string $reference): void
   {
      global $wpdb;

      $wpdb->query(
         $wpdb->prepare(
            "UPDATE {$this->campaign_item_table}
               SET `count` = GREATEST(COALESCE(`count`, 0) - 1, 0)
               WHERE reference = %s",
            $reference
         )
      );
   }

   /**
    * Validate reference and campaign
    */
   private function validate_reference_and_campaign(string $reference)
   {
      global $wpdb;

      $query = $wpdb->prepare("
         SELECT
            ci.reference,
            ci.campaign_id,
            ci.status as item_status,
            c.campaign_name,
            c.start_date,
            c.end_date,
            c.status as campaign_status,
            c.post_id
         FROM {$this->campaign_item_table} ci
         INNER JOIN {$this->campaign_table} c ON ci.campaign_id = c.id
         WHERE ci.reference = %s
      ", $reference);

      $campaign_data = $wpdb->get_row($query, ARRAY_A);

      if (!$campaign_data) {
         throw new Exception('Reference not found in any campaign item');
      }

      if ($campaign_data['item_status'] !== 'active') {
         throw new Exception('Campaign item not active');
      }

      if ($campaign_data['campaign_status'] !== 'published') {
         throw new Exception('Campaign not published');
      }

      $current_date = current_time('Y-m-d');
      if (!empty($campaign_data['start_date']) && $current_date < $campaign_data['start_date']) {
         throw new Exception('Campaign has not started yet');
      }
      if (!empty($campaign_data['end_date']) && $current_date > $campaign_data['end_date']) {
         throw new Exception('Campaign has already ended');
      }

      return $campaign_data;
   }

   /**
    * Public static factory
    */
   public static function create_reference(int $comment_id, string $reference): bool
   {
      try {
         do_action('qr_create_reference', $comment_id, $reference);
         return true;
      } catch (Exception $e) {
         error_log('QuickReview Reference Creation Failed: ' . $e->getMessage());
         return false;
      }
   }

   public static function get_comment_reference_data(int $comment_id)
   {
      $reference = get_comment_meta($comment_id, 'reference', true);
      if (empty($reference)) {
         return false;
      }

      return [
         'reference'      => $reference,
         'campaign_id'    => get_comment_meta($comment_id, 'qr_campaign_id', true),
         'campaign_name'  => get_comment_meta($comment_id, 'qr_campaign_name', true),
         'created_at'     => get_comment_meta($comment_id, 'qr_reference_created_at', true),
      ];
   }

   public static function is_reference_valid(string $reference): bool
   {
      try {
         $instance = new self();
         $campaign_data = $instance->validate_reference_and_campaign($reference);
         return !empty($campaign_data);
      } catch (Exception $e) {
         return false;
      }
   }
}

// Bootstrap
if (defined('ABSPATH')) {
   new ReferenceService();
}

function qr_create_reference(int $comment_id, string $reference): bool
{
   return \QuickReview\ReferenceService::create_reference($comment_id, $reference);
}
