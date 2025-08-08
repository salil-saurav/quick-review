class ReviewService {
   constructor(createBtn) {
      this.createBtn = createBtn;
   }

   async create(formData) {
      formData.append('action', 'create_review_url');
      formData.append('post_id', this.createBtn.dataset.post);

      const response = await fetch(window.ajaxurl, {
         method: 'POST',
         credentials: 'same-origin',
         body: formData
      });

      return response.json();
   }

   init() {
      if (!this.createBtn) return;

      this.createBtn.addEventListener('click', async (e) => {
         e.preventDefault();

         this.setLoading(true);

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
      });
   }

   setLoading(isLoading) {
      this.createBtn.textContent = isLoading ? 'Creating URL...' : 'Create New URL';
      this.createBtn.disabled = isLoading;
   }
}

document.addEventListener("DOMContentLoaded", () => {
   const createBtn = document.getElementById("populateReview");
   const reviewService = new ReviewService(createBtn);
   reviewService.init();
});
