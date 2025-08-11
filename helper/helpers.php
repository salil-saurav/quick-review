<?php

/**
 * Returns the total count of reviews for a given campaign ID.
 *
 * @param int $campaign_id The ID of the campaign.
 * @return int The total count of reviews (returns 0 if none found).
 */
function review_url_count($campaign_id)
{
   global $wpdb;

   $review_table = $wpdb->prefix . QR_REVIEW_TABLE;

   $total_count = $wpdb->get_var(
      $wpdb->prepare(
         "SELECT SUM(`count`) FROM `$review_table` WHERE campaign_id = %d",
         $campaign_id
      )
   );

   return (int) $total_count; // Return 0 if null
}

/**
 * Renders a single table row of data based on the table type.
 *
 * @param object $column The data object for the row.
 * @param int $count The serial number for the row.
 * @param string $table_name The name of the table to determine rendering logic.
 * @return void
 */
function render_single_data($column, $count, $table_name)
{
   // Build table row
   if ($table_name === QR_CAMPAIGN_TABLE) {

      $row = [
         's_no'           => $count,
         'campaign_name'  => $column->campaign_name,
         'review_count'   => sprintf('<span class="review_count" data-post="%d"> %d </span>', esc_attr($column->post_id), review_url_count($column->id)),
         'start_date'     => $column->start_date,
         'end_date'       => $column->end_date,
         'status'         => build_status_column($column->status),
         'post_id'        => $column->post_id,
         'action'         => build_action_button($column->post_id),
         'date'           => date('Y-m-d H:i A', strtotime($column->created_at)),
      ];

      render_table_row($row);
   } else {
      $row = [
         's_no'         => $count,
         'reference'    => $column->reference,
         'count'        => $column->count,
         'review_url'   => $column->review_url,
         'copy'         => '<button class="copy-url" data-target="' . $column->review_url . '" >📋</button>',
         'date'         => date('Y-m-d H:i A', strtotime($column->created_at)),
         'remove'       => sprintf('<span data-reference="%s" class="remove-row dashicons dashicons-no"></span>', $column->reference)
      ];

      render_table_row($row);
   }
}


/**
 * Builds the HTML for the status column in the campaign table.
 *
 * @param string $status The status value.
 * @return string The HTML markup for the status column.
 */
function build_status_column($status)
{
   $status_class = 'status-badge status-' . strtolower($status);
   return sprintf(
      '<span class="%s">%s</span>',
      esc_attr($status_class),
      esc_html(ucfirst($status))
   );
}


/**
 * Builds the HTML for the action button in the campaign table.
 *
 * @param int $postId The post ID associated with the campaign.
 * @return string The HTML markup for the action button.
 */
function build_action_button($postId)
{
   $admin_url = admin_url('admin.php?page=campaign-dashboard');

   return sprintf(
      '<a href="%s" data-post="%d" class="view_details button-primary">%s</a>',
      esc_url($admin_url . "&post_id={$postId}"),
      esc_attr($postId),
      esc_html('View Details')
   );
}

/**
 * Renders a table row with the provided data.
 *
 * @param array $row An associative array of column values for the row.
 * @return void
 */
function render_table_row($row)
{
   echo "<tr>";
   foreach ($row as $value) {
      echo "<td>{$value}</td>";
   }
   echo "</tr>";
}



/**
 * Renders the table with paginated data and optional date/campaign filters.
 *
 * @param array $page_data Pagination and filter data (current_page, items_per_page).
 * @param string $table_name The name of the table to query.
 * @param int|bool $post_id Optional post ID to filter by campaign.
 * @return void
 */

function render_table($page_data, $table_name, $post_id = false)
{
   $current_page   = $page_data['current_page'];
   $items_per_page = $page_data['items_per_page'];

   $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
   $end_date   = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

   global $wpdb;
   $review_table = $wpdb->prefix . $table_name;
   $offset = ($current_page - 1) * $items_per_page;

   $query = "SELECT * FROM $review_table WHERE 1=1";
   $query_args = [];

   // Filter by campaign_id from post_id
   if ($post_id) {
      $campaign_table = $wpdb->prefix . QR_CAMPAIGN_TABLE;
      $campaign_id = $wpdb->get_var($wpdb->prepare(
         "SELECT id FROM $campaign_table WHERE post_id = %d",
         $post_id
      ));

      if ($campaign_id) {
         $query .= " AND campaign_id = %d";
         $query_args[] = $campaign_id;
      } else {
         echo '<p class="no-urls">' . __('Invalid Campaign ID.', 'quick-review') . '</p>';
         return;
      }
   }

   // Date filters
   if (!empty($start_date)) {
      $query .= " AND DATE(created_at) >= %s";
      $query_args[] = $start_date;
   }
   if (!empty($end_date)) {
      $query .= " AND DATE(created_at) <= %s";
      $query_args[] = $end_date;
   }

   $query .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
   $query_args[] = $items_per_page;
   $query_args[] = $offset;

   $data = $wpdb->get_results($wpdb->prepare($query, $query_args));

   // Render the filter form
   echo <<<HTML
   <div class="date-filter">
      <div></div>
      <div class="date-filter-grp">
         <form method="post" class="date-filter-form">
            <input type="hidden" name="page" value="quick-review">
            <input type="hidden" name="post_id" value="{$post_id}">
            <label for="start_date">Start Date:</label>
            <input type="date" id="start_date" name="start_date" value="{$start_date}">
            <label for="end_date">End Date:</label>
            <input type="date" id="end_date" name="end_date" value="{$end_date}">
            <button type="submit" class="button" style="margin-right: 5px;">Filter</button>
            <a href="?page=quick-review&post_id={$post_id}" class="button">Reset</a>
         </form>
      </div>
   </div>
   HTML;

   if (empty($data)) {
      echo '<p class="no-urls">' . __('No data found.', 'quick-review') . '</p>';
      return;
   }

   // Render rows
   $starting_count = $offset + 1;
   $count = $starting_count;
   foreach ($data as $column) {
      render_single_data($column, $count, $table_name);
      $count++;
   }
}


/**
 * Gets the total count of reviews, optionally filtered by date and campaign.
 *
 * @param bool $include_dates Whether to include date filters.
 * @param string $table_name The name of the table to query.
 * @param int|bool $post_id Optional post ID to filter by campaign.
 * @return int|null The total count of reviews, or null if invalid campaign.
 */

function get_total_count($include_dates, $table_name, $post_id = false)
{
   global $wpdb;
   $table = $wpdb->prefix . $table_name;

   $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
   $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

   $query = "SELECT COUNT(*) FROM $table WHERE 1=1";
   $query_args = [];

   if (!empty($start_date)) {
      $query .= " AND DATE(created_at) >= %s";
      $query_args[] = $start_date;
   }
   if (!empty($end_date)) {
      $query .= " AND DATE(created_at) <= %s";
      $query_args[] = $end_date;
   }

   if ($post_id) {
      $campaign_table = $wpdb->prefix . QR_CAMPAIGN_TABLE;

      $campaign_id = $wpdb->get_var($wpdb->prepare(
         "SELECT id FROM $campaign_table WHERE post_id = %d",
         $post_id
      ));

      if ($campaign_id) {
         $query .= " AND campaign_id = %d";
         $query_args[] = $campaign_id;
      } else {
         echo '<p class="no-urls">' . __('Invalid Campaign ID.', 'quick-review') . '</p>';
         return;
      }
   }

   if ($include_dates) {
      return !empty($query_args)
         ? $wpdb->get_var($wpdb->prepare($query, $query_args))
         : $wpdb->get_var($query);
   } else {

      $total =  $wpdb->get_var("SELECT COUNT(*) FROM $table");
      return $total;
   }
}
