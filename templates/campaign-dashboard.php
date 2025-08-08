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

$per_page = get_user_option('campaign_items_per_page') ?: 50;

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
<div class="wrap review-wrap">
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
         <button id="populateReview" class="button button-primary button-large" data-post="<?= esc_attr($post_id) ?>">Create New URL</button>
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
            </tr>
         </thead>
         <tbody class="review-list" data-total="<?= get_total_count(false, QR_REVIEW_TABLE, $post_id) ?>">
            <?php render_table($page_data, QR_REVIEW_TABLE, $post_id); ?>
         </tbody>
      </table>

      <!-- Pagination controls -->
      <div class="pagination">
         <?php
         // Get total number of reviews
         $total_pages = ceil($total_items / $items_per_page);
         // $base_url = "?page=campaign-dashboard&post_id='.$post_id.'paged=";

         // Only show pagination if there are multiple pages
         if ($total_pages > 1) {
            // Previous page link
            if ($current_page > 1) {
               echo '<a href="?page=campaign-dashboard&post_id=' . $post_id . '&paged=' . ($current_page - 1) . '" class="button">&laquo; Previous</a>';
            }

            // Page numbers (limit to show max 5 pages around current page)
            $start_page = max(1, $current_page - 2);
            $end_page = min($total_pages, $current_page + 2);

            // Show first page if not in range
            if ($start_page > 1) {
               echo '<a href="?page=campaign-dashboard&post_id=' . $post_id . '&paged=1" class="button">1</a>';
               if ($start_page > 2) {
                  echo '<span class="pagination-dots">...</span>';
               }
            }

            // Show page numbers in range
            for ($i = $start_page; $i <= $end_page; $i++) {
               $active_class = ($i === $current_page) ? 'current-page' : '';
               echo '<a href="?page=campaign-dashboard&post_id=' . $post_id . '&paged=' . $i . '" class="button ' . $active_class . '">' . $i . '</a>';
            }

            // Show last page if not in range
            if ($end_page < $total_pages) {
               if ($end_page < $total_pages - 1) {
                  echo '<span class="pagination-dots">...</span>';
               }
               echo '<a href="?page=campaign-dashboard&post_id=' . $post_id . '&paged=' . $total_pages . '" class="button">' . $total_pages . '</a>';
            }

            // Next page link
            if ($current_page < $total_pages) {
               echo '<a href="?page=campaign-dashboard&post_id=' . $post_id . '&paged=' . ($current_page + 1) . '" class="button">Next &raquo;</a>';
            }

            // Show current page info
            echo '<div class="pagination-info">Page ' . $current_page . ' of ' . $total_pages . ' (Total: ' . $total_items . ' Urls)</div>';
         }
         ?>
      </div>
   </div>
</div>

<?php
require_once __DIR__ . '/partials/campaign-partial.php';
?>

<script src="<?= QUICK_REVIEW_PLUGIN_URL ?>/includes/ajax/script/review-service.js"></script>
