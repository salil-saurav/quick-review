<?php

/**
 * AJAX handler for Creating Review Links
 *
 * @package QuickReview
 */

namespace QuickReview;

use Exception;
use WP_Error;

class CreateCampaigns
{
   private const NONCE_NAME = 'submit';
   private const NONCE_ACTION = 'submit_nonce';

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
    * Register WordPress hooks (only once)
    */
   private function register_hooks(): void
   {
      if (self::$hooks_registered) {
         return;
      }

      add_action('wp_ajax_create_campaign', [$this, 'handle_create_campaign']);
      add_action('wp_ajax_autofill_campaign', [$this, 'handle_autofill']);
      add_action('qr_create_campaign', [__CLASS__, 'handle_external_create_campaign_static'], 10, 1);

      self::$hooks_registered = true;
   }

   /**
    * Handle the AJAX request for creating a review link
    */
   public function handle_create_campaign(): void
   {
      try {
         $this->verify_request();
         $data = $this->get_sanitized_data();
         $this->validate_data($data);

         $result = $this->save_campaign($data);

         wp_send_json_success([
            'message'     => 'Campaign saved successfully',
            'campaign_id' => $result
         ]);
      } catch (Exception $e) {
         wp_send_json_error([
            'message' => $e->getMessage(),
            'code'    => $e->getCode()
         ], 400);
      }
   }

   public function handle_autofill()
   {
      try {

         $campaign_id = isset($_POST['campaign_id']) ? (int) $_POST['campaign_id'] : 0;

         global $wpdb;

         $campaign_details = $wpdb->get_row(
            $wpdb->prepare(
               "SELECT * FROM {$this->campaign_table} WHERE `id` = %d",
               $campaign_id
            ),
            ARRAY_A
         );

         if ($campaign_details) {

            $selected_post = get_the_title($campaign_details['post_id']);
            $campaign_details['selected_post'] = $selected_post;

            wp_send_json_success($campaign_details);
         } else {
            wp_send_json_error('Campaign not found');
         }
      } catch (Exception $e) {
         wp_send_json_error([
            'message' => $e->getMessage(),
            'code'    => $e->getCode()
         ], 400);
      }
   }

   /**
    * Verify the AJAX request security
    *
    * @throws Exception
    */
   private function verify_request(): void
   {
      if (!check_ajax_referer(self::NONCE_NAME, self::NONCE_ACTION, false)) {
         throw new Exception('Invalid security token', 403);
      }

      if (!current_user_can('manage_options')) {
         throw new Exception('Insufficient permissions', 403);
      }
   }

   /**
    * Get and sanitize POST data
    *
    * @return array<string, mixed>
    */
   private function get_sanitized_data(): array
   {
      $defaults = [
         'campaign_name' => '',
         'start_date'    => '',
         'end_date'      => '',
         'status'        => '',
         'post_id'       => 0,
         'campaign_id'   => ''
      ];

      $data = wp_parse_args($_POST, $defaults);

      return [
         'campaign_name' => sanitize_text_field($data['campaign_name']),
         'start_date'    => self::sanitize_date($data['start_date']),
         'end_date'      => self::sanitize_date($data['end_date']),
         'status'        => sanitize_text_field($data['status']),
         'post_id'       => absint($data['post_id']),
         'campaign_id'   => absint($data['campaign_id']),
      ];
   }

   /**
    * Sanitize date input
    *
    * @param string $date
    * @return string
    */
   private static function sanitize_date(string $date): string
   {
      if (empty($date)) {
         return '';
      }

      $timestamp = strtotime($date);
      return $timestamp ? date('Y-m-d', $timestamp) : '';
   }

   /**
    * Validate required data
    *
    * @param array<string, mixed> $data
    * @throws Exception
    */
   private function validate_data(array $data): void
   {
      $required_fields = ['campaign_name', 'start_date', 'status', 'post_id'];

      foreach ($required_fields as $field) {
         if (empty($data[$field])) {
            throw new Exception(sprintf('Missing required field: %s', $field), 400);
         }
      }

      // Validate date formats - check if sanitization failed
      if (!empty($_POST['start_date']) && empty($data['start_date'])) {
         throw new Exception('Invalid start date format', 400);
      }

      if (!empty($_POST['end_date']) && empty($data['end_date'])) {
         throw new Exception('Invalid end date format', 400);
      }

      // Ensure end date is after start date (if both are provided)
      if (
         !empty($data['end_date']) && !empty($data['start_date']) &&
         strtotime($data['end_date']) < strtotime($data['start_date'])
      ) {
         throw new Exception('End date must be after start date', 400);
      }
   }

   /**
    * Create a new campaign in the database
    *
    * @param array<string, mixed> $data
    * @return int
    * @throws Exception
    */
   private function save_campaign(array $data): int
   {
      global $wpdb;

      $is_update = !empty($data['campaign_id']);
      $table     = $this->campaign_table;

      $payload = [
         'campaign_name' => $data['campaign_name'],
         'start_date'    => $data['start_date'],
         'end_date'      => $data['end_date'],
         'status'        => $data['status'],
         'post_id'       => $data['post_id'],
      ];

      $formats = ['%s', '%s', '%s', '%s', '%d'];

      if ($is_update) {
         // Update existing campaign
         $where = ['id' => (int) $data['campaign_id']];
         $where_format = ['%d'];

         $result = $wpdb->update($table, $payload, $where, $formats, $where_format);

         if ($result === false) {
            throw new Exception('Failed to update campaign', 500);
         }

         return $data['campaign_id'];
      } else {
         // Insert new campaign
         $payload['created_at'] = current_time('mysql');
         $formats[] = '%s';

         $result = $wpdb->insert($table, $payload, $formats);

         if ($result === false) {
            throw new Exception('Failed to create campaign', 500);
         }

         return $wpdb->insert_id;
      }
   }

   /**
    * Static handler for external campaign creation (prevents multiple registrations)
    *
    * @param array{
    *   campaign_name: string,
    *   start_date: string,
    *   end_date: string,
    *   status?: string,
    *   post_id: int
    * } $args
    */
   public static function handle_external_create_campaign_static(array $args): void
   {
      try {
         $campaign_id = self::create($args);

         // Optional: Logging or notification
         do_action('qr_after_campaign_created', $campaign_id, $args);
      } catch (Exception $e) {
         error_log('Campaign creation failed: ' . $e->getMessage());
      }
   }

   public static function create(array $args): int
   {
      $defaults = [
         'campaign_name' => '',
         'start_date'    => '',
         'end_date'      => '',
         'status'        => 'draft',
         'post_id'       => 0,
      ];

      $data = wp_parse_args($args, $defaults);

      // Store original data for validation
      $original_data = $data;

      // Sanitize
      $data = [
         'campaign_name' => sanitize_text_field($data['campaign_name']),
         'start_date'    => self::sanitize_date($data['start_date']),
         'end_date'      => self::sanitize_date($data['end_date']),
         'status'        => sanitize_text_field($data['status']),
         'post_id'       => absint($data['post_id']),
         'campaign_id'   => 0,
      ];

      // Check if already exists
      $existing_id = self::campaign_exists($data);
      if ($existing_id) {
         return $existing_id;
      }

      // Validate and save
      self::validate_external_data($data, $args);
      return self::save_campaign_static($data);
   }

   private static function campaign_exists(array $data): ?int
   {
      global $wpdb;
      $campaign_table = $wpdb->prefix . QR_CAMPAIGN_TABLE;

      $existing = $wpdb->get_var($wpdb->prepare(
         "SELECT id FROM {$campaign_table}
         WHERE campaign_name = %s AND post_id = %d AND start_date = %s AND end_date = %s AND status = %s
         LIMIT 1",
         $data['campaign_name'],
         $data['post_id'],
         $data['start_date'],
         $data['end_date'],
         $data['status']
      ));

      return $existing ? (int) $existing : null;
   }


   /**
    * Static method to save campaign (avoids creating new instances)
    */
   private static function save_campaign_static(array $data): int
   {
      global $wpdb;

      $campaign_table = $wpdb->prefix . QR_CAMPAIGN_TABLE;
      $is_update = !empty($data['campaign_id']);

      $payload = [
         'campaign_name' => $data['campaign_name'],
         'start_date'    => $data['start_date'],
         'end_date'      => $data['end_date'],
         'status'        => $data['status'],
         'post_id'       => $data['post_id'],
      ];

      $formats = ['%s', '%s', '%s', '%s', '%d'];

      if ($is_update) {
         // Update existing campaign
         $where = ['id' => (int) $data['campaign_id']];
         $where_format = ['%d'];

         $result = $wpdb->update($campaign_table, $payload, $where, $formats, $where_format);

         if ($result === false) {
            throw new Exception('Failed to update campaign', 500);
         }

         return $data['campaign_id'];
      } else {
         // Insert new campaign
         $payload['created_at'] = current_time('mysql');
         $formats[] = '%s';

         $result = $wpdb->insert($campaign_table, $payload, $formats);

         if ($result === false) {
            throw new Exception('Failed to create campaign', 500);
         }

         return $wpdb->insert_id;
      }
   }

   /**
    * Validate data for external creation (static method)
    */
   private static function validate_external_data(array $data, array $original_data): void
   {
      $required_fields = ['campaign_name', 'start_date', 'status', 'post_id'];

      foreach ($required_fields as $field) {
         if (empty($data[$field])) {
            throw new Exception(sprintf('Missing required field: %s', $field), 400);
         }
      }

      // Validate date formats using original data
      if (!empty($original_data['start_date']) && empty($data['start_date'])) {
         throw new Exception('Invalid start date format', 400);
      }

      if (!empty($original_data['end_date']) && empty($data['end_date'])) {
         throw new Exception('Invalid end date format', 400);
      }

      // Ensure end date is after start date (if both are provided)
      if (
         !empty($data['end_date']) && !empty($data['start_date']) &&
         strtotime($data['end_date']) < strtotime($data['start_date'])
      ) {
         throw new Exception('End date must be after start date', 400);
      }
   }
}

if (defined('ABSPATH')) {
   new CreateCampaigns();
}
