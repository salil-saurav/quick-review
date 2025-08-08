// ======= Campaign Service ====== //

class CampaignService {
   constructor(form) {
      this.form = form;
      this.submitBtn = form.querySelector('button[type="submit"]');
   }

   async create(formData) {
      formData.append('action', 'create_campaign');
      const submitNonce = this.form.querySelector('button[type="submit"]').dataset.security;
      formData.append("submit_nonce", submitNonce);

      const response = await fetch(window.ajaxurl, {
         method: 'POST',
         credentials: 'same-origin',
         body: formData
      });

      return response.json();
   }

   init() {
      this.form.addEventListener('submit', async (e) => {
         e.preventDefault();

         this.setLoading(true);

         const formData = new FormData(this.form);
         const result = await this.create(formData);

         if (result.success) {
            AlertService.show(result.data.message, 'success');
            setTimeout(() => location.reload(), 500);
         } else {
            AlertService.show(result.data.message, 'error');
         }

         this.setLoading(false);
      });
   }

   setLoading(isLoading) {
      this.submitBtn.classList.toggle('loading', isLoading);
      const svg = this.submitBtn.querySelector('svg');
      if (svg) svg.style.visibility = isLoading ? 'hidden' : 'visible';
   }
}

document.addEventListener("DOMContentLoaded", () => {
   const campaignForm = document.getElementById("campaignForm");

   const campaign = new CampaignService(campaignForm);
   campaign.init();

})
