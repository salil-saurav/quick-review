<?php

/**
 * Service for managing campaign items and review links
 *
 * @package QuickReview
 */

namespace QuickReview;

use Exception;
use WP_Error;

class CampaignItemService
{
   private string $campaign_table;
   private string $campaign_item_table;
   private static bool $hooks_registered = false;

   // Constants for better maintainability
   private const MAX_UUID_ATTEMPTS = 5;
   private const STATUS_ACTIVE = 'active';
   private const STATUS_INACTIVE = 'inactive';
   private const CAPABILITY_REQUIRED = 'manage_options';

   /**
    * Constructor
    */
   public function __construct()
   {
      global $wpdb;

      $this->campaign_table = $wpdb->prefix . QR_REVIEW_CAMPAIGN;
      $this->campaign_item_table = $wpdb->prefix . QR_REVIEW_CAMPAIGN_ITEM;

      $this->register_hooks();
   }

   /**
    * Register WordPress hooks (singleton pattern)
    */
   private function register_hooks(): void
   {
      if (self::$hooks_registered) {
         return;
      }

      add_action('wp_ajax_create_campaign_item', [$this, 'handle_create_campaign_item']);
      add_action('wp_ajax_delete_campaign_item', [$this, 'handle_delete_campaign_item']);
      add_action('qr_create_campaign_item', [__CLASS__, 'handle_external_create_campaign_item'], 10, 1);

      self::$hooks_registered = true;
   }

   /**
    * Handle AJAX request for creating a campaign item
    */
   public function handle_create_campaign_item(): void
   {
      try {
         $this->verify_permissions();

         $post_id     = $this->validate_post_id($_POST['post_id'] ?? 0);
         $name        = sanitize_text_field($_POST['name'] ?? '');
         $campaign_id = $this->resolve_campaign_id($post_id, false);
         $result      = $this->create_campaign_item($post_id, $campaign_id, $name);

         wp_send_json_success([
            'message' => 'Campaign item created successfully',
            'data' => $result
         ]);
      } catch (Exception $e) {
         wp_send_json_error([
            'message' => $e->getMessage(),
            'code' => $e->getCode()
         ], $e->getCode() ?: 400);
      }
   }

   /**
    * Handle AJAX request for deleting a campaign item
    */
   public function handle_delete_campaign_item(): void
   {
      try {
         $this->verify_permissions();
         $reference = $this->validate_reference($_POST['reference'] ?? '');

         $this->delete_campaign_item($reference);

         wp_send_json_success([
            'message' => 'Campaign item deleted successfully'
         ]);
      } catch (Exception $e) {
         wp_send_json_error([
            'message' => $e->getMessage(),
            'code' => $e->getCode()
         ], $e->getCode() ?: 400);
      }
   }

   /**
    * External handler for developers to create campaign items programmatically
    *
    * @param array $args {
    *     @type int $post_id Required. The post ID to create review URL for
    *     @type int $campaign_id Optional. Specific campaign ID
    * }
    * @return string|WP_Error The review reference UUID or WP_Error on failure
    */
   public static function handle_external_create_campaign_item(array $args)
   {
      try {
         $instance    = new self();

         $post_id     = $instance->validate_post_id($args['post_id'] ?? 0);
         $campaign_id = (int) ($args['campaign_id'] ?? 0);
         $name        = sanitize_text_field($args['name'] ?? '');

         $result      = $instance->create_campaign_item($post_id, $campaign_id, $name);

         do_action('qr_after_campaign_item_created', $result, $args);

         return $result['reference'];
      } catch (Exception $e) {
         error_log("CampaignItemService Error: " . $e->getMessage());
         return new WP_Error(
            'campaign_item_creation_failed',
            $e->getMessage(),
            ['status' => $e->getCode()]
         );
      }
   }

   /**
    * Public static method for easy access by developers
    *
    * @param int $post_id The post ID
    * @param int $campaign_id Optional specific campaign ID
    * @return string|WP_Error The review reference UUID or WP_Error on failure
    */
   public static function create_for_post(int $post_id, int $campaign_id = 0, string $name = '')
   {
      return self::handle_external_create_campaign_item([
         'post_id'     => $post_id,
         'campaign_id' => $campaign_id,
         'name'        => $name
      ]);
   }

   /**
    * Create a new campaign item in the database
    *
    * @param int $post_id The post ID
    * @param int $campaign_id Optional specific campaign ID
    * @return array{reference: string, review_url: string, campaign_id: int}
    * @throws Exception
    */
   private function create_campaign_item(int $post_id, int $campaign_id = 0, string $name = ''): array
   {
      $campaign_id = $this->resolve_campaign_id($post_id, $campaign_id);
      $permalink   = $this->get_post_permalink($post_id);
      $uuid        = $this->generate_unique_reference();
      $review_url  = $this->build_review_url($permalink, $uuid, $post_id);

      $this->insert_campaign_item($uuid, $campaign_id, $name);

      return [
         'reference'   => $uuid,
         'review_url'  => $review_url,
         'campaign_id' => $campaign_id,
         'name'        => $name
      ];
   }

   /**
    * Delete a campaign item by reference
    *
    * @param string $reference The campaign item reference
    * @throws Exception
    */
   private function delete_campaign_item(string $reference): void
   {
      global $wpdb;

      $deleted = $wpdb->delete(
         $this->campaign_item_table,
         ['reference' => $reference],
         ['%s']
      );

      if ($deleted === false) {
         throw new Exception('Database error while deleting campaign item', 500);
      }

      if ($deleted === 0) {
         throw new Exception('Campaign item not found', 404);
      }
   }

   /**
    * Verify user permissions
    *
    * @throws Exception
    */
   private function verify_permissions(): void
   {
      if (!current_user_can(self::CAPABILITY_REQUIRED)) {
         throw new Exception('Insufficient permissions', 403);
      }
   }

   /**
    * Validate and sanitize post ID
    *
    * @param mixed $post_id The post ID to validate
    * @return int Valid post ID
    * @throws Exception
    */
   private function validate_post_id($post_id): int
   {
      $post_id = (int) $post_id;

      if (!$post_id || get_post_status($post_id) === false) {
         throw new Exception('Invalid or missing post ID', 404);
      }

      return $post_id;
   }

   /**
    * Validate and sanitize reference
    *
    * @param mixed $reference The reference to validate
    * @return string Valid reference
    * @throws Exception
    */
   private function validate_reference($reference): string
   {
      $reference = sanitize_text_field($reference);

      if (empty($reference)) {
         throw new Exception('Missing or invalid reference', 400);
      }

      return $reference;
   }

   /**
    * Resolve campaign ID for a given post
    *
    * @param int $post_id The post ID
    * @param int $campaign_id Optional specific campaign ID
    * @return int Valid campaign ID
    * @throws Exception
    */
   private function resolve_campaign_id(int $post_id, int $campaign_id): int
   {
      global $wpdb;

      if (!$campaign_id) {
         $campaign_id = $wpdb->get_var(
            $wpdb->prepare(
               "SELECT id FROM {$this->campaign_table}
                     WHERE post_id = %d AND status != %s
                     ORDER BY created_at DESC LIMIT 1",
               $post_id,
               self::STATUS_INACTIVE
            )
         );

         if (!$campaign_id) {
            throw new Exception('No active campaign found for this post', 404);
         }
      } else {
         $this->validate_campaign($campaign_id, $post_id);
      }

      return (int) $campaign_id;
   }

   /**
    * Validate that a campaign exists and belongs to the specified post
    *
    * @param int $campaign_id The campaign ID
    * @param int $post_id The post ID
    * @throws Exception
    */
   private function validate_campaign(int $campaign_id, int $post_id): void
   {
      global $wpdb;

      $campaign = $wpdb->get_row(
         $wpdb->prepare(
            "SELECT id, status FROM {$this->campaign_table}
                 WHERE id = %d AND post_id = %d",
            $campaign_id,
            $post_id
         )
      );

      if (!$campaign) {
         throw new Exception('Campaign not found or does not belong to this post', 404);
      }

      if ($campaign->status === self::STATUS_INACTIVE) {
         throw new Exception('Cannot create item for inactive campaign', 400);
      }
   }

   /**
    * Get permalink for a post
    *
    * @param int $post_id The post ID
    * @return string The permalink
    * @throws Exception
    */
   private function get_post_permalink(int $post_id): string
   {
      $permalink = get_permalink($post_id);

      if (!$permalink) {
         throw new Exception('Could not generate permalink for post', 500);
      }

      return $permalink;
   }

   /**
    * Generate a unique reference UUID
    *
    * @return string Unique UUID
    * @throws Exception
    */
   private function generate_unique_reference(): string
   {
      global $wpdb;

      for ($attempt = 1; $attempt <= self::MAX_UUID_ATTEMPTS; $attempt++) {
         $uuid = $this->generate_uuid_v4();

         $exists = $wpdb->get_var(
            $wpdb->prepare(
               "SELECT reference FROM {$this->campaign_item_table} WHERE reference = %s",
               $uuid
            )
         );

         if (!$exists) {
            return $uuid;
         }
      }

      throw new Exception('Could not generate unique reference after multiple attempts', 500);
   }

   /**
    * Build the review URL
    *
    * @param string $permalink The post permalink
    * @param string $uuid The UUID reference
    * @param int $post_id The post ID
    * @return string The review URL
    */
   private function build_review_url(string $permalink, string $uuid, int $post_id): string
   {
      $review_url = add_query_arg('review', $uuid, $permalink);

      return apply_filters('modify_campaign_item', $review_url, $uuid, $post_id);
   }

   /**
    * Insert campaign item into database
    *
    * @param string $uuid The UUID reference
    * @param int $campaign_id The campaign ID
    * @throws Exception
    */
   private function insert_campaign_item(string $uuid, int $campaign_id, string $name): void
   {
      global $wpdb;

      $inserted = $wpdb->insert(
         $this->campaign_item_table,
         [
            'reference'   => $uuid,
            'name'        => $name,
            'campaign_id' => $campaign_id,
            'created_at'  => current_time('mysql'),
            'status'      => self::STATUS_ACTIVE
         ],
         ['%s', '%s', '%d', '%s', '%s']
      );

      if ($inserted === false) {
         throw new Exception(
            'Failed to create campaign item in database: ' . $wpdb->last_error,
            500
         );
      }
   }

   /**
    * Generate a UUIDv4 string
    *
    * @return string UUIDv4 formatted string
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
    * Generate a unique UUIDv4 string with collision checking (static utility)
    *
    * @param int $max_attempts Maximum attempts to generate unique UUID
    * @return string|WP_Error Returns unique UUID string or WP_Error on failure
    */
   public static function generate_unique_uuid(int $max_attempts = self::MAX_UUID_ATTEMPTS)
   {
      global $wpdb;

      $table_name = $wpdb->prefix . QR_REVIEW_CAMPAIGN_ITEM;

      for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
         $uuid = self::generate_static_uuid_v4();

         $exists = $wpdb->get_var(
            $wpdb->prepare(
               "SELECT reference FROM {$table_name} WHERE reference = %s LIMIT 1",
               $uuid
            )
         );

         if (!$exists) {
            return $uuid;
         }
      }

      return new WP_Error(
         'uuid_generation_failed',
         sprintf('Could not generate unique UUID after %d attempts', $max_attempts),
         ['status' => 500]
      );
   }

   /**
    * Generate a UUIDv4 string (static version for utility use)
    *
    * @return string UUIDv4 formatted string
    */
   private static function generate_static_uuid_v4(): string
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
    * Retrieve all campaigns for specific post IDs
    *
    * @param array $post_ids Array of post IDs to filter campaigns
    * @param array $options {
    *     Optional. Array of options for filtering and pagination.
    *     @type int    $page         Page number for pagination (default: 1)
    *     @type int    $per_page     Number of items per page (default: 10)
    *     @type int    $offset       Offset for results (overrides page if set)
    *     @type string $start_date   Start date filter (Y-m-d format)
    *     @type string $end_date     End date filter (Y-m-d format)
    *     @type string $status       Campaign status filter ('active', 'inactive', 'published', etc.)
    *     @type string $order_by     Column to order by (default: 'created_at')
    *     @type string $order        Order direction 'ASC' or 'DESC' (default: 'DESC')
    *     @type bool   $count_only   Return only the total count (default: false)
    * }
    * @return array|int|WP_Error Array of campaigns, count if count_only is true, or WP_Error on failure
    */
   public function get_campaigns_by_post_ids(array $post_ids, array $options = [])
   {
      global $wpdb;

      try {
         // Validate post IDs
         $post_ids = array_filter(array_map('intval', $post_ids));
         if (empty($post_ids)) {
            throw new Exception('No valid post IDs provided', 400);
         }

         // Set default options
         $defaults = [
            'page' => 1,
            'per_page' => 10,
            'offset' => null,
            'start_date' => '',
            'end_date' => '',
            'status' => '',
            'order_by' => 'created_at',
            'order' => 'DESC',
            'count_only' => false
         ];

         $options = wp_parse_args($options, $defaults);

         // Build WHERE clause
         $where_conditions = [];
         $where_values = [];

         // Post IDs filter
         $post_ids_placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
         $where_conditions[] = "post_id IN ($post_ids_placeholders)";
         $where_values = array_merge($where_values, $post_ids);

         // Date filters
         if (!empty($options['start_date'])) {
            $where_conditions[] = "created_at >= %s";
            $where_values[] = $options['start_date'] . ' 00:00:00';
         }

         if (!empty($options['end_date'])) {
            $where_conditions[] = "created_at <= %s";
            $where_values[] = $options['end_date'] . ' 23:59:59';
         }

         // Status filter
         if (!empty($options['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = sanitize_text_field($options['status']);
         }

         $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

         // Count query if requested
         if ($options['count_only']) {
            $count_sql = "SELECT COUNT(*) FROM {$this->campaign_table} $where_clause";

            if (!empty($where_values)) {
               $count_sql = $wpdb->prepare($count_sql, $where_values);
            }

            return (int) $wpdb->get_var($count_sql);
         }

         // Build ORDER BY clause
         $allowed_order_by = ['id', 'campaign_name', 'post_id', 'status', 'created_at', 'updated_at', 'start_date', 'end_date'];
         $order_by = in_array($options['order_by'], $allowed_order_by) ? $options['order_by'] : 'created_at';
         $order = strtoupper($options['order']) === 'ASC' ? 'ASC' : 'DESC';

         // Build LIMIT clause
         $limit_clause = '';
         if ($options['offset'] !== null) {
            $limit_clause = $wpdb->prepare("LIMIT %d, %d", (int) $options['offset'], (int) $options['per_page']);
         } else {
            $offset = ((int) $options['page'] - 1) * (int) $options['per_page'];
            $limit_clause = $wpdb->prepare("LIMIT %d, %d", $offset, (int) $options['per_page']);
         }

         // Build final query
         $sql = "SELECT * FROM {$this->campaign_table}
                $where_clause
                ORDER BY $order_by $order
                $limit_clause";

         if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
         }

         $results = $wpdb->get_results($sql, ARRAY_A);

         if ($results === false) {
            throw new Exception('Database error while retrieving campaigns', 500);
         }

         return $results;
      } catch (Exception $e) {
         error_log("CampaignItemService Error: " . $e->getMessage());
         return new WP_Error(
            'campaigns_retrieval_failed',
            $e->getMessage(),
            ['status' => $e->getCode()]
         );
      }
   }

   /**
    *  Retrieve all campaign items for a specific campaign
    *
    * @param int $campaign_id Campaign ID to get items for
    * @param array $options {
    *     Optional. Array of options for filtering and pagination.
    *     @type int    $page         Page number for pagination (default: 1)
    *     @type int    $per_page     Number of items per page (default: 10)
    *     @type int    $offset       Offset for results (overrides page if set)
    *     @type string $status       Item status filter ('active', 'inactive')
    *     @type string $start_date   Start date filter for created_at (Y-m-d format)
    *     @type string $end_date     End date filter for created_at (Y-m-d format)
    *     @type string $order_by     Column to order by (default: 'created_at')
    *     @type string $order        Order direction 'ASC' or 'DESC' (default: 'DESC')
    *     @type bool   $count_only   Return only the total count (default: false)
    *     @type bool   $include_stats Include usage statistics (default: false)
    * }
    * @return array|int|WP_Error Array of campaign items, count if count_only is true, or WP_Error on failure
    */
   public function get_campaign_items_by_campaign_id(int $campaign_id, array $options = [])
   {
      global $wpdb;

      try {
         // Validate campaign ID
         if (!$campaign_id) {
            throw new Exception('Invalid campaign ID provided', 400);
         }

         // Verify campaign exists
         $campaign_exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->campaign_table} WHERE id = %d", $campaign_id)
         );

         if (!$campaign_exists) {
            throw new Exception('Campaign not found', 404);
         }

         // Set default options
         $defaults = [
            'page' => 1,
            'per_page' => 10,
            'offset' => null,
            'status' => '',
            'start_date' => '',
            'end_date' => '',
            'order_by' => 'created_at',
            'order' => 'DESC',
            'count_only' => false,
            'include_stats' => false
         ];

         $options = wp_parse_args($options, $defaults);

         // Build WHERE clause
         $where_conditions = ['campaign_id = %d'];
         $where_values = [$campaign_id];

         // Status filter
         if (!empty($options['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = sanitize_text_field($options['status']);
         }

         // Date filters
         if (!empty($options['start_date'])) {
            $where_conditions[] = "created_at >= %s";
            $where_values[] = $options['start_date'] . ' 00:00:00';
         }

         if (!empty($options['end_date'])) {
            $where_conditions[] = "created_at <= %s";
            $where_values[] = $options['end_date'] . ' 23:59:59';
         }

         $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

         // Count query if requested
         if ($options['count_only']) {
            $count_sql = "SELECT COUNT(*) FROM {$this->campaign_item_table} $where_clause";
            $count_sql = $wpdb->prepare($count_sql, $where_values);

            return (int) $wpdb->get_var($count_sql);
         }

         // Build SELECT clause
         $select_fields = '*';
         if ($options['include_stats']) {
            $select_fields = '*, COALESCE(`count`, 0) as total_count';
         }

         // Build ORDER BY clause
         $allowed_order_by = ['id', 'reference', 'campaign_id', 'status', 'created_at', 'count'];
         $order_by = in_array($options['order_by'], $allowed_order_by) ? $options['order_by'] : 'created_at';
         $order = strtoupper($options['order']) === 'ASC' ? 'ASC' : 'DESC';

         // Build LIMIT clause
         $limit_clause = '';
         if ($options['offset'] !== null) {
            $limit_clause = $wpdb->prepare("LIMIT %d, %d", (int) $options['offset'], (int) $options['per_page']);
         } else {
            $offset = ((int) $options['page'] - 1) * (int) $options['per_page'];
            $limit_clause = $wpdb->prepare("LIMIT %d, %d", $offset, (int) $options['per_page']);
         }

         // Build final query
         $sql = "SELECT $select_fields FROM {$this->campaign_item_table}
                $where_clause
                ORDER BY $order_by $order
                $limit_clause";

         $sql = $wpdb->prepare($sql, $where_values);

         $results = $wpdb->get_results($sql, ARRAY_A);

         if ($results === false) {
            throw new Exception('Database error while retrieving campaign items', 500);
         }

         return $results;
      } catch (Exception $e) {
         error_log("CampaignItemService Error: " . $e->getMessage());
         return new WP_Error(
            'campaign_items_retrieval_failed',
            $e->getMessage(),
            ['status' => $e->getCode()]
         );
      }
   }
}

// Initialize the service
if (defined('ABSPATH')) {
   new CampaignItemService();
}
