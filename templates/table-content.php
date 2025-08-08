<?php
require_once QUICK_REVIEW_PLUGIN_DIR . '/helper/helpers.php';

$page_data = [];

$per_page = (int) get_user_option('quick_review_per_page') ?: 15;

// Pagination settings
$items_per_page = $per_page; // Number of items per page
$current_page   = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$start_date     = isset($_POST['start_date']) ? max(1, intval($_POST['start_date'])) : 1;
$end_date       = isset($_POST['end_date']) ? max(1, intval($_POST['end_date'])) : 1;
$total_items    = get_total_count(true, QR_CAMPAIGN_TABLE);

$page_data['items_per_page'] = $items_per_page;
$page_data['current_page']   =  $current_page;

?>

<div class="review-container">
   <div class="table-header">
      <h2>Campaigns</h2>
      <button id="createCampaign" class="button button-primary button-large">Create New Campaign</button>
   </div>
   <table class="review-table">
      <thead>
         <tr>
            <th>S/No</th>
            <th>Campaign Name</th>
            <th>Total Reviews</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Status</th>
            <th>Post Id</th>
            <th>View Details</th>
            <th>Created At</th>
            <th>Edit</th>
         </tr>
      </thead>
      <tbody class="review-list" data-total="<?= get_total_count(false, QR_CAMPAIGN_TABLE) ?>">
         <?php render_table($page_data, QR_CAMPAIGN_TABLE, false); ?>
      </tbody>
   </table>

   <!-- Pagination controls -->
   <div class="pagination">
      <?php
      // Get total number of reviews
      $total_pages = ceil($total_items / $items_per_page);
      $base_url = "?page=quick-review&paged=";

      // Only show pagination if there are multiple pages
      if ($total_pages > 1) {
         // Previous page link
         if ($current_page > 1) {
            echo '<a href="?page=quick-review&paged=' . ($current_page - 1) . '" class="button">&laquo; Previous</a>';
         }

         // Page numbers (limit to show max 5 pages around current page)
         $start_page = max(1, $current_page - 2);
         $end_page = min($total_pages, $current_page + 2);

         // Show first page if not in range
         if ($start_page > 1) {
            echo '<a href="?page=quick-review&paged=1" class="button">1</a>';
            if ($start_page > 2) {
               echo '<span class="pagination-dots">...</span>';
            }
         }

         // Show page numbers in range
         for ($i = $start_page; $i <= $end_page; $i++) {
            $active_class = ($i === $current_page) ? 'current-page' : '';
            echo '<a href="?page=quick-review&paged=' . $i . '" class="button ' . $active_class . '">' . $i . '</a>';
         }

         // Show last page if not in range
         if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
               echo '<span class="pagination-dots">...</span>';
            }
            echo '<a href="?page=quick-review&paged=' . $total_pages . '" class="button">' . $total_pages . '</a>';
         }

         // Next page link
         if ($current_page < $total_pages) {
            echo '<a href="?page=quick-review&paged=' . ($current_page + 1) . '" class="button">Next &raquo;</a>';
         }

         // Show current page info
         echo '<div class="pagination-info">Page ' . $current_page . ' of ' . $total_pages . ' (Total: ' . $total_items . ' Urls)</div>';
      }
      ?>
   </div>
</div>

<?php
require_once __DIR__ . '/partials/modal.php';
require_once __DIR__ . '/partials/single-detail.php';
?>

<script src="<?= QUICK_REVIEW_PLUGIN_URL ?>/includes/ajax/script/campaign-service.js"></script>
