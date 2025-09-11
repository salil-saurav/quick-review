class ReviewService {
   constructor(showModal, campaingItemName, createButton) {
      this.showModal = showModal;
      this.campaingItemName = campaingItemName;
      this.createButton = createButton
   }

   async create(formData) {
      formData.append('action', 'create_campaign_item');
      formData.append('post_id', this.showModal.dataset.post);
      formData.append('name', this.campaingItemName.value);

      const response = await fetch(window.ajaxurl, {
         method: 'POST',
         credentials: 'same-origin',
         body: formData
      });

      return response.json();
   }

   init() {
      if (!this.showModal) return;

      this.showModal.addEventListener('click', (e) => {
         e.preventDefault();

         const campaignOverlay = document.getElementById('campaign-item-overlay');
         const close = campaignOverlay.querySelector('.close-button');

         const popupInstance = new PopupHandler(campaignOverlay, close);
         popupInstance.show();

      });

      this.createButton.addEventListener('click', async () => {

         if (this.campaingItemName.value) {
            this.setLoading(true, this.createButton);

            try {

               const formData = new FormData();
               const result = await this.create(formData);

               if (result.success) {
                  AlertService.show(result.data.message, 'success');

                  setTimeout(() => location.reload(), 500);
               } else {
                  AlertService.show(result.data.message || 'Error occurred', 'error');
               }

            } catch (error) {
               console.error(error);
               AlertService.show("Something went wrong", "error");
            }

            this.setLoading(false);
         } else {
            this.campaingItemName.focus();
         }
      })
   }



   setLoading(isLoading) {

      this.createButton.textContent = isLoading ? 'Creating...' : 'Create';
      this.createButton.disabled = isLoading;
   }
}

document.addEventListener("DOMContentLoaded", () => {
   const showModal = document.getElementById("populateReview"),
      campaingItemName = document.querySelector('input[name="campaign_item_name"]'),
      createButton = document.getElementById('createCampaignItem');

   const reviewService = new ReviewService(showModal, campaingItemName, createButton);
   reviewService.init();
});
