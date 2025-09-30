<div class="review-overlay" id="popup-overlay">

   <div class="modal">
      <div class="modal-header">
         <button class="close-button">X</button>
         <h2 class="modal-title"> <?= isset($_GET['post_id']) ? 'Edit Campaign' : 'Create New Campaign'  ?> </h2>
      </div>

      <div class="modal-body">

         <form id="campaignForm">

            <div class="qrs-row">
               <div class="form-group">
                  <label> Campaign Name
                     <input type="text" name="campaign_name" required>
                  </label>
               </div>

               <div class="form-group status-select-wrap">
                  <label style="display: flex; flex-direction:column"> Status
                     <select name="status" id="status" required>
                        <option value="draft">Draft</option>
                        <option value="pending">Pending</option>
                        <option value="published">Published</option>
                     </select>
                  </label>
               </div>
            </div>

            <?php $today = date('Y-m-d'); ?>

            <div class="qrs-row">
               <div class="form-group">
                  <label> Start Date
                     <input type="date" name="start_date" required min="<?php echo $today; ?>">
                  </label>
               </div>

               <div class="form-group">
                  <label> End Date
                     <input type="date" name="end_date" min="<?php echo $today; ?>">
                  </label>
               </div>
            </div>

            <div class="form-group search-wrapper" style="position: relative;">
               <div class="search_input">
                  <input type="text" name="selected_post" id="selected_post" placeholder="Select post ( Search with title or ID )" required>

                  <div class="course_search_results search-result"></div>
                  <div class="loader courseLoader"></div>
               </div>
            </div>

            <input type="hidden" name="post_id">

            <button type="submit" class="button button-primary button-large" id="submitBtn" data-security="<?= wp_create_nonce('submit') ?>">
               <svg class="icon link-icon" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd" />
               </svg>
               Save
            </button>
            <div class="loader modalLoader"></div>
         </form>
      </div>
   </div>
</div>
