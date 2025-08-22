<?php

require_once QUICK_REVIEW_PLUGIN_DIR . '/helper/settings.php';
/**
 * AJAX handler for post search functionality
 *
 * @package QuickReview
 */

class QR_Search_Handler
{
   private $config;
   private $post_types;

   public function __construct()
   {
      $this->init();
      $this->register_hooks();
   }

   private function init()
   {
      $this->config = get_plugin_settings();

      if ($this->config && !empty($this->config)) {

         $this->post_types = $this->config['post_types'];
      }
   }

   private function register_hooks()
   {
      add_action('wp_ajax_search_posts', [$this, 'handle_post_search']);
   }

   public function handle_post_search()
   {
      check_ajax_referer('search_nonce', 'nonce');

      $search_term = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';

      // If post_types is a comma-separated string, convert it to array
      $post_types = explode(',', $this->post_types[0]);

      if (empty($post_types)) {
         wp_send_json_error(['message' => 'No post types configured.']);
      }

      // Apply filter to allow customization of posts per page
      $posts_per_page = apply_filters('qr_search_posts_per_page', 10);

      $post_args = [
         'post_type'      => $post_types,
         'posts_per_page' => $posts_per_page,
         'post_status'    => 'publish',
      ];

      if (!empty($search_term)) {
         $post_args['s'] = $search_term;
      }

      $post_data = get_posts($post_args);

      if (empty($post_data)) {
         wp_send_json_error(['message' => 'No results found.']);
      }

      // Format the post data for response
      $formatted_posts = $this->format_post_data($post_data);
      wp_send_json_success(['posts' => $formatted_posts]);

      wp_die();
   }

   private function format_post_data($post_data)
   {
      $result = [];
      foreach ($post_data as $post) {
         $result[] = [
            'post_id'   => $post->ID,
            'title'     => $post->post_title,
            'slug'      => get_permalink($post->ID),
            'post_date' => $post->post_date
         ];
      }
      return $result;
   }

   private function post_matches_search($post, $search_term)
   {
      $search_term_lower = strtolower($search_term);
      return ($post->ID == $search_term)
         || (strpos(strtolower($post->post_title), $search_term_lower) !== false)
         || (strpos(strtolower($post->post_name), $search_term_lower) !== false);
   }

   private function get_post_data($post_data, $search_term)
   {
      if (empty($search_term) || empty($post_data)) {
         return [];
      }

      $result = [];
      foreach ($post_data as $post) {
         if ($this->post_matches_search($post, $search_term)) {
            $result[] = [
               'post_id'   => $post->ID,
               'title'     => $post->post_title,
               'slug'      => get_permalink($post->ID),
               'post_date' => $post->post_date
            ];
         }
      }
      return $result;
   }
}

add_action('init', function () {
   new QR_Search_Handler();
});
