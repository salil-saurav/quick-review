<?php

function get_plugin_settings()
{
   global $wpdb;

   $table = $wpdb->prefix . QR_SETTINGS_TABLE;

   $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;

   if (!$table_exists) {
      return false;
   }

   $row = $wpdb->get_row(
      $wpdb->prepare("SELECT option_value FROM {$table} WHERE option_name = %s", 'post_types'),
      ARRAY_A
   );

   if (!$row || empty($row['option_value'])) {
      return false;
   }

   return maybe_unserialize($row['option_value']);
}
