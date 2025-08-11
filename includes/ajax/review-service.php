<?php

/**
 * AJAX handler for Creating Review Links
 *
 * @package QuickReview
 */

namespace QuickReview;

use Exception;
use WP_Error;

class ReviewService
{
   private string $campaign_table;
   private string $review_table;
   private static bool $hooks_registered = false;

   /**
    * Constructor
    */
   public function __construct()
   {
      global $wpdb;

      $this->campaign_table = $wpdb->prefix . QR_CAMPAIGN_TABLE;
      $this->review_table = $wpdb->prefix . QR_REVIEW_TABLE;

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

      add_action('wp_ajax_create_review_url', [$this, 'handle_create_review_url']);
      add_action('wp_ajax_delete_review_url', [$this, 'handle_delete_review_url']);

      // Action for developers to create review URLs programmatically
      add_action('qr_create_review_url', [__CLASS__, 'handle_external_create_review_url'], 10, 1);

      self::$hooks_registered = true;
   }

   /**
    * Handle AJAX request for creating a review link
    */
   public function handle_create_review_url(): void
   {
      try {
         // Add security checks
         if (!current_user_can('manage_options')) {
            throw new Exception('Insufficient permissions', 403);
         }

         $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

         if (!$post_id || get_post_status($post_id) === false) {
            throw new Exception('Invalid or missing post ID', 404);
         }

         $result = $this->create_review_url($post_id);

         wp_send_json_success([
            'message'     => 'URL created successfully',
            'review_id'   => $result['reference'],
            'review_url'  => $result['review_url']
         ]);
      } catch (Exception $e) {
         wp_send_json_error([
            'message' => $e->getMessage(),
            'code'    => $e->getCode()
         ], $e->getCode() ?: 400);
      }
   }

   /**
    * External handler for developers to create review URLs
    *
    * @param array $args {
    *     @type int $post_id Required. The post ID to create review URL for
    *     @type int $campaign_id Optional. Specific campaign ID (if not provided, finds by post_id)
    * }
    * @return string|WP_Error The review reference UUID or WP_Error on failure
    */
   public static function handle_external_create_review_url($args)
   {


      try {
         $post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
         $campaign_id = isset($args['campaign_id']) ? (int) $args['campaign_id'] : 0;

         if (!$post_id || get_post_status($post_id) === false) {

            error_log('invalid_post', 'Invalid or missing post ID');
            return new WP_Error('invalid_post', 'Invalid or missing post ID', ['status' => 404]);
         }



         $instance = new self();
         $result = $instance->create_review_url($post_id, $campaign_id);

         // Fire action after successful creation
         do_action('qr_after_review_url_created', $result, $args);

         return $result['reference'];
      } catch (Exception $e) {
         return new WP_Error('review_creation_failed', $e->getMessage(), ['status' => $e->getCode()]);
      }
   }

   /**
    * Generate a UUIDv4 string
    */
   private function generate_uuid_v4(): string
   {
      return sprintf(
         '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
         mt_rand(0, 0xffff),
         mt_rand(0, 0xffff),
         mt_rand(0, 0xffff),
         mt_rand(0, 0x0fff) | 0x4000,
         mt_rand(0, 0x3fff) | 0x8000,
         mt_rand(0, 0xffff),
         mt_rand(0, 0xffff),
         mt_rand(0, 0xffff)
      );
   }

   /**
    * Create a new review URL entry in the database
    *
    * @param int $post_id The post ID
    * @param int $campaign_id Optional specific campaign ID
    * @return array{reference: string, review_url: string, campaign_id: int}
    * @throws Exception
    */
   private function create_review_url(int $post_id, int $campaign_id = 0): array
   {
      global $wpdb;

      // Find campaign if not provided
      if (!$campaign_id) {
         $campaign_id = $wpdb->get_var(
            $wpdb->prepare(
               "SELECT id FROM {$this->campaign_table} WHERE post_id = %d AND status != 'inactive' ORDER BY created_at DESC LIMIT 1",
               $post_id
            )
         );

         if (!$campaign_id) {
            throw new Exception('No active campaign found for this post', 404);
         }
      } else {
         // Verify the provided campaign exists and belongs to this post
         $campaign = $wpdb->get_row(
            $wpdb->prepare(
               "SELECT id, status FROM {$this->campaign_table} WHERE id = %d AND post_id = %d",
               $campaign_id,
               $post_id
            )
         );

         if (!$campaign) {
            throw new Exception('Campaign not found or does not belong to this post', 404);
         }

         if ($campaign->status === 'inactive') {
            throw new Exception('Cannot create review URL for inactive campaign', 400);
         }
      }

      $permalink = get_permalink($post_id);
      if (!$permalink) {
         throw new Exception('Could not generate permalink for post', 500);
      }

      // Generate unique UUID with collision check
      $max_attempts = 5;
      $attempts     = 0;

      do {
         $uuid = $this->generate_uuid_v4();
         $exists = $wpdb->get_var(
            $wpdb->prepare(
               "SELECT reference FROM {$this->review_table} WHERE reference = %s",
               $uuid
            )
         );
         $attempts++;
      } while ($exists && $attempts < $max_attempts);

      if ($exists) {
         throw new Exception('Could not generate unique reference', 500);
      }

      $review_url = trailingslashit($permalink) . 'reviews/new?' . http_build_query(['reference' => $uuid]);

      // Apply filter to allow modification of the review URL
      $review_url = apply_filters('qr_review_url', $review_url, $uuid, $post_id);

      $inserted = $wpdb->insert(
         $this->review_table,
         [
            'reference'   => $uuid,
            'campaign_id' => $campaign_id,
            'review_url'  => $review_url,
            'created_at'  => current_time('mysql'),
            'status'      => 'active'
         ],
         ['%s', '%d', '%s', '%s', '%s']
      );

      if ($inserted === false) {
         throw new Exception('Failed to create review URL in database: ' . $wpdb->last_error, 500);
      }

      return [
         'reference'   => $uuid,
         'review_url'  => $review_url,
         'campaign_id' => $campaign_id
      ];
   }

   /**
    * Public static method for easy access by developers
    *
    * @param int $post_id The post ID
    * @param int $campaign_id Optional specific campaign ID
    * @return string|WP_Error The review reference UUID or WP_Error on failure
    */
   public static function create_for_post(int $post_id, int $campaign_id = 0)
   {
      return self::handle_external_create_review_url([
         'post_id'     => $post_id,
         'campaign_id' => $campaign_id
      ]);
   }

   // Handle delete url

   public function handle_delete_review_url()
   {
      global $wpdb;

      $reference   = isset($_POST['reference']) ? sanitize_text_field($_POST['reference']) : false;

      if (! $reference) {
         wp_send_json_error(['message' => 'Missing reference.']);
      }

      // Perform deletion
      $deleted = $wpdb->delete(
         $this->review_table,
         ['reference' => $reference],
         ['%s']
      );

      if (false === $deleted) {
         wp_send_json_error(['message' => 'Database error while deleting.']);
      }

      if (0 === $deleted) {
         wp_send_json_error(['message' => 'No record found with this reference.']);
      }

      wp_send_json_success(['message' => 'Review deleted successfully.']);
   }
}

if (defined('ABSPATH')) {
   new ReviewService();
}
