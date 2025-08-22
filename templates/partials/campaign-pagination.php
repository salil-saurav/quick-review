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
