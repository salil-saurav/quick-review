<?php

require_once QR_PLUGIN_DIR . '/helper/settings.php';


if (!current_user_can('manage_options')) {
   wp_die(__('You do not have sufficient permissions to access this page.'));
}
/**
 * Class QuickReviewAdminPage
 * Handles the admin page functionality for Quick Review plugin
 */
class CampaignDashboard
{
   /**
    * Initialize the admin page
    */
   public function __construct()
   {
      $this->render_page();
   }

   /**
    * Render the admin page content
    */
   public function render_page()
   {
?>
      <div class="wrap review-wrap">
         <?php

         $posts = get_post_count();

         if (strlen($posts[0]) > 0 && count($posts) >= 1) {
            $this->render_content();
         } else {
            $this->render_error_page();
         }
         ?>
      </div>
<?php
   }

   /**
    * Render the main content of the admin page
    */
   private function render_content()
   {
      require_once __DIR__ . '/campaign-dashboard.php';
   }

   private function render_error_page()
   {
      require_once __DIR__ . '/error-page.php';
   }
}

// Initialize the admin page
new CampaignDashboard();
