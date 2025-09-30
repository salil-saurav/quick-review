<?php

/**
 * Database Manager for Quick Review Plugin
 *
 * This class manages the database operations for the Quick Review plugin, handling
 * the creation and maintenance of campaign and review-related tables.
 *
 * @package QuickReview
 * @since 1.0.0
 *
 * Database Schema:
 * - Campaign Table:
 *   - id (INT): Auto-incrementing primary key starting from 256737
 *   - campaign_name (VARCHAR): Name of the campaign
 *   - start_date (DATE): Campaign start date
 *   - end_date (DATE): Campaign end date
 *   - status (ENUM): Campaign status ('draft', 'pending', 'published')
 *   - post_id (INT): Associated WordPress post ID
 *   - created_at (DATETIME): Timestamp of creation
 *
 * - Campaign Item Table:
 *   - reference (VARCHAR): Primary key
 *   - name (VARCHAR): Item name
 *   - campaign_id (INT): Foreign key to campaign table
 *   - count (INT): Counter field defaulting to 0
 *   - status (ENUM): Item status ('active', 'inactive')
 *   - created_at (DATETIME): Timestamp of creation
 *
 * Features:
 * - Automatic table creation on plugin initialization
 * - Foreign key constraints for data integrity
 * - Custom charset and collation support
 * - Table existence verification
 */

class QRDatabaseManager
{
   /**
    * Initialize the class and set up the database
    */
   public function __construct()
   {
      $this->maybe_create_tables();
   }

   /**
    * Create database tables if they don't exist
    *
    * @return bool True if tables were created or already exist, false on failure
    */
   private function maybe_create_tables(): bool
   {
      global $wpdb;

      $campaign_table_name = $wpdb->prefix . QR_CAMPAIGN;
      $campaign_item_table = $wpdb->prefix . QR_CAMPAIGN_ITEM;

      // Check if both tables exist
      if ($this->tables_exist([$campaign_table_name, $campaign_item_table])) {
         return true;
      }

      // Create tables and return true only if both are created successfully
      return $this->create_campaign_table($campaign_table_name) &&
         $this->create_campaign_item_table($campaign_item_table);
   }

   /**
    * Check if all specified tables exist
    *
    * @param array $table_names Array of full table names with prefix
    * @return bool True if all tables exist, false otherwise
    */
   private function tables_exist(array $table_names): bool
   {
      global $wpdb;

      foreach ($table_names as $table) {
         if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return false;
         }
      }
      return true;
   }

   /**
    * Create the campaign table
    *
    * @param string $table_name Full table name with prefix
    * @return bool True on success, false on failure
    */
   private function create_campaign_table(string $table_name): bool
   {
      global $wpdb;

      $charset_collate = $wpdb->get_charset_collate();

      $sql = "CREATE TABLE $table_name (
         id INT UNSIGNED NOT NULL AUTO_INCREMENT,
         campaign_name VARCHAR(255) NOT NULL,
         start_date DATE NOT NULL,
         end_date DATE NOT NULL,
         status ENUM('draft', 'pending', 'published') DEFAULT 'draft',
         post_id INT NOT NULL,
         created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
         PRIMARY KEY (id)
      ) $charset_collate;";

      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      dbDelta($sql);

      $wpdb->query("ALTER TABLE $table_name AUTO_INCREMENT = 256737");

      // Verify table creation
      return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
   }


   /**
    * Create the review table with foreign key to campaign table
    *
    * @param string $table_name Full table name with prefix
    * @return bool True on success, false on failure
    */
   private function create_campaign_item_table(string $table_name): bool
   {
      global $wpdb;

      $charset_collate = $wpdb->get_charset_collate();
      $campaign_table_name = $wpdb->prefix . QR_CAMPAIGN;

      $sql = "CREATE TABLE `$table_name` (
         reference VARCHAR(36) NOT NULL,
         name VARCHAR(255) NOT NULL,
         campaign_id INT UNSIGNED NOT NULL,
         `count` INT DEFAULT 0,
         status ENUM('active', 'inactive') DEFAULT 'inactive',
         created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
         PRIMARY KEY (reference)
      ) $charset_collate;";

      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      dbDelta($sql);

      $wpdb->query("ALTER TABLE `$table_name`
         ADD CONSTRAINT fk_campaign FOREIGN KEY (campaign_id)
         REFERENCES `$campaign_table_name`(id) ON DELETE CASCADE");

      return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
   }
}
