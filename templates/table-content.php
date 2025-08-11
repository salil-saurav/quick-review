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
         </tr>
      </thead>
      <tbody class="review-list" data-total="<?= get_total_count(false, QR_CAMPAIGN_TABLE) ?>">
         <?php render_table($page_data, QR_CAMPAIGN_TABLE, false); ?>
      </tbody>
   </table>

   <?php require_once __DIR__ . '/partials/list-pagination.php'; ?>
</div>

<?php
require_once __DIR__ . '/partials/modal.php';
require_once __DIR__ . '/partials/single-detail.php';
?>

<script src="<?= QUICK_REVIEW_PLUGIN_URL ?>/includes/ajax/script/campaign-service.js"></script>
