// ===== Utility Functions =====
const createOrUpdateHiddenInput = (form, name, value) => {
   if (!form) return;
   let input = form.querySelector(`input[name="${name}"]`);
   if (!input) {
      input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      form.appendChild(input);
   }
   input.value = value;
};

const toggleLoader = (form, loader, show = true) => {
   if (!form || !loader) return;
   form.classList.toggle("loading", show);
   loader.style.display = show ? "block" : "none";
};

const updateFormFields = (form, data) => {
   if (!form || !data) return;
   Object.entries(data).forEach(([key, value]) => {
      const input = form.querySelector(`[name="${key}"]`);
      if (input) input.value = value;
   });
};


const handleDelete = async (reference, popupInstance) => {
   try {
      const formData = new FormData();
      formData.append("action", "delete_review_url");
      formData.append("reference", reference);

      const response = await fetch(window.ajaxurl, {
         method: "POST",
         credentials: "same-origin",
         body: formData,
      });

      const json = await response.json();
      if (json.success && json.data) {

         AlertService.show(json.data.message, 'success');
      }
   } catch (err) {
      console.error("Failed to delete url:", err);
   } finally {
      popupInstance.hide();
      location.reload();
   }
}

// ===== DOM Ready =====
document.addEventListener("DOMContentLoaded", () => {
   const $ = (selector) => document.querySelector(selector);

   // Cached elements
   const reviewTable = $(".review-table");
   const reviewList = $(".review-list");
   const dateFilter = $(".date-filter");
   const modalLoader = $(".loader.modalLoader");
   const editButton = $('button[data-edit="campaign"]');
   const campaignForm = $("#campaignForm");
   const confirmationOverlay = $('.confirmation-overlay');
   const confirmationClose = $('.confirmation-overlay .close-button');
   const iscampaignPage = $('.campaign-dashboard');

   const popupElements = {
      overlay: $(".review-overlay"),
      closeBtn: $(".review-overlay .close-button"),
   };

   // ===== Table & Filter UI =====
   if (reviewTable && reviewList && dateFilter) {
      const total = parseInt(reviewList.dataset.total || "0", 10);

      reviewTable.classList.toggle("hide", reviewList.children.length === 0);
      dateFilter.classList.toggle("hide", total === 0);


      if (iscampaignPage) {
         const confirmation = new PopupHandler(confirmationOverlay, confirmationClose);
         const cancel = confirmationOverlay.querySelector('.cancel-delete');

         const deleteRow = document.getElementById('removeRow');

         reviewTable.addEventListener("click", (e) => {
            if (!(e.target.classList.contains('remove-row'))) {
               return
            }

            const reference = e.target.dataset.reference;
            confirmation.show();

            deleteRow.dataset.id = reference;
         });

         cancel.addEventListener('click', () => confirmation.hide());

         deleteRow.addEventListener('click', async () => {
            const reference = deleteRow.dataset.id;
            handleDelete(reference, confirmation)
         })
      }

   }

   // ===== Edit Campaign Button =====
   if (editButton && campaignForm) {
      editButton.addEventListener("click", async () => {
         const campaignID = editButton.dataset.camp;
         if (!campaignID) return;

         const popup = new PopupHandler(popupElements.overlay, popupElements.closeBtn);
         popup.show();

         toggleLoader(campaignForm, modalLoader, true);

         try {
            const formData = new FormData();
            formData.append("action", "autofill_campaign");
            formData.append("campaign_id", campaignID);

            const response = await fetch(window.ajaxurl, {
               method: "POST",
               credentials: "same-origin",
               body: formData,
            });

            const json = await response.json();
            if (json.success && json.data) {
               updateFormFields(campaignForm, json.data);
               createOrUpdateHiddenInput(campaignForm, "campaign_id", campaignID);
            }
         } catch (err) {
            console.error("Failed to autofill campaign:", err);
         } finally {
            toggleLoader(campaignForm, modalLoader, false);
         }
      });
   }

   // ===== Create Campaign Popup =====
   const campaignTrigger = $("#createCampaign");
   if (campaignTrigger && popupElements.overlay && popupElements.closeBtn) {
      const popup = new PopupHandler(popupElements.overlay, popupElements.closeBtn);
      campaignTrigger.addEventListener("click", () => popup.show());
   }
});

// ===== Global: Copy URL Button =====
document.addEventListener("click", (e) => {
   const btn = e.target.closest("button.copy-url");
   if (!btn) return;

   const url = btn.dataset.target;
   if (!url) {
      console.warn("Missing data-target attribute.");
      return;
   }
   ClipboardService.copy(url);
});
