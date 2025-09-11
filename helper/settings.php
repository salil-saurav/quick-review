<?php

function qr_get_plugin_settings()
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

function qr_get_post_count()
{
   global $wpdb;

   $table = $wpdb->prefix . QR_SETTINGS_TABLE;

   $row = $wpdb->get_row(
      $wpdb->prepare("SELECT option_value FROM {$table} WHERE option_name = %s", 'post_types'),
      ARRAY_A
   );


   if (!$row || empty($row['option_value'])) {
      return 0;
   }

   $option_value = maybe_unserialize($row['option_value']);

   // return $post_types;
   if (empty($option_value) || !is_array($option_value)) {
      return 0;
   }

   $post_types = explode(",", $option_value['post_types'][0]);

   return $post_types;
}
