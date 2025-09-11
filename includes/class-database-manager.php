<?php

/**
 * Class DatabaseManager
 *
 * Handles the initialization and management of review-related database tables for the Quick Review plugin.
 *
 * Responsibilities:
 * - Automatically creates required database tables (`campaign` and `review`) if they do not exist.
 * - Provides methods to check for table existence and to create tables with appropriate schema.
 * - Ensures referential integrity between campaign and review tables via foreign key constraints.
 *
 * Methods:
 * - __construct(): Initializes the class and triggers table creation if necessary.
 * - maybe_create_tables(): Checks for table existence and creates them if missing.
 * - tables_exist(array $table_names): Verifies the existence of specified tables.
 * - create_campaign_table(string $table_name): Creates the campaign table with required columns and constraints.
 * - create_campaign_item_table(string $table_name): Creates the review table with a foreign key to the campaign table.
 *
 * Usage:
 * Instantiate this class to ensure the necessary tables for campaign and review management are present in the database.
 */

class DatabaseManager
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
      $campaign_item_table   = $wpdb->prefix . QR_CAMPAIGN_ITEM;

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
