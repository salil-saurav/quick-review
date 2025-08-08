<div class="review-overlay single-review-detail" id="single-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-title">
   <div class="modal">
      <!-- Modal Header -->
      <div class="modal-header">
         <h2 class="modal-title" id="modal-title">Review Details</h2>
         <button class="close-button" aria-label="Close Modal">X</button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">

         <!-- Loader -->
         <div class="loader details-loader"></div>

         <div class="user-details">
            <!-- Review URL -->
            <div class="review-url-wrap">
               <div>
                  <strong>Review URL:</strong>
                  <a class="url" id="review-url" target="_blank"> Click to open in new window </a>
               </div>
               <button class="copy-url" aria-label="Copy URL"> 📋 Copy Url</button>
            </div>

            <div class="review_current_status hide">
               <h3> <strong>Status: </strong> Review Pending</h3>
            </div>
            <!-- User Details -->
            <div class="total"> Total: <strong></strong></div>
            <div class="comment_details_wrap">

               <!-- Comment details will be injected here -->
            </div>
            <!-- All comments  -->
            <div class="user_review"> </div>
            <!-- Action Buttons -->
            <!-- <div class="modal-actions">
               <button class="button button-primary" id="save-details">Update <div class="loader"></div></button>
            </div> -->
         </div>
      </div>
   </div>
</div>