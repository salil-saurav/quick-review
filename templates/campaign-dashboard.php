<?php
if (!current_user_can('manage_options')) {
   wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Check if 'post_id' is passed and valid
if (!isset($_GET['post_id']) || empty($_GET['post_id'])) {
   wp_die(__('Missing required parameter: post_id.'));
}

// Sanitize the post_id
$post_id = sanitize_text_field($_GET['post_id']);

require_once QUICK_REVIEW_PLUGIN_DIR . '/helper/helpers.php';

$page_data = [];

$per_page = (int) get_user_option('campaign_items_per_page') ?: 15;

// Pagination settings
$items_per_page = $per_page; // Number of items per page
$current_page   = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$start_date     = isset($_POST['start_date']) ? max(1, intval($_POST['start_date'])) : 1;
$end_date       = isset($_POST['end_date']) ? max(1, intval($_POST['end_date'])) : 1;
$total_items    = get_total_count(true, QR_REVIEW_TABLE, $post_id);


$page_data['items_per_page'] = $items_per_page;
$page_data['current_page']   =  $current_page;

global $wpdb;

$campaign_table = $wpdb->prefix . QR_CAMPAIGN_TABLE;

$campaign_details = $wpdb->get_row(
   $wpdb->prepare(
      "SELECT * FROM $campaign_table WHERE post_id = %d",
      $post_id
   )
);

?>
<div class="wrap review-wrap campaign-dashboard">
   <div class="review-container">
      <div class="table-header">
         <div class="header-cta">
            <a href="<?= admin_url('admin.php?page=quick-review') ?>"> <span> &#10157;</span> campaigns </a>
            <h2>
               Review URLs:
               <span class="campaign-name"><?= esc_html($campaign_details->campaign_name) ?></span>
               <span class="status-badge status-<?= esc_attr($campaign_details->status) ?>">
                  <?= ucfirst(esc_html($campaign_details->status)) ?>
               </span>
            </h2>
         </div>
         <div class="cta-container">
            <button class="button button-secondry button-large" data-edit="campaign" data-camp="<?= $campaign_details->id ?>"> <span class="dashicons dashicons-edit"></span> Edit Details </button>
            <button id="populateReview" class="button button-primary button-large" data-post="<?= esc_attr($post_id) ?>">Create New URL</button>
         </div>
      </div>
      <table class="review-table">
         <thead>
            <tr>
               <th>S/No</th>
               <th>Reference ID</th>
               <th>Count</th>
               <th>Review Url</th>
               <th>Copy</th>
               <th>Created At</th>
               <th> Remove </th>
            </tr>
         </thead>
         <tbody class="review-list" data-total="<?= get_total_count(false, QR_REVIEW_TABLE, $post_id) ?>">
            <?php render_table($page_data, QR_REVIEW_TABLE, $post_id); ?>
         </tbody>
      </table>

      <?php
      require_once __DIR__ . '/partials/camp-pagination.php';
      require_once __DIR__ . '/partials/modal.php';
      require_once __DIR__ . '/partials/confirmation.php';
      ?>

   </div>
</div>

<?php
require_once __DIR__ . '/partials/campaign-partial.php';
?>

<script src="<?= QUICK_REVIEW_PLUGIN_URL ?>/includes/ajax/script/review-service.js"></script>
<script src="<?= QUICK_REVIEW_PLUGIN_URL ?>/includes/ajax/script/campaign-service.js"></script>
