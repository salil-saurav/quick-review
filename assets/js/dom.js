// Utility: Create or Update Hidden Input
const createOrUpdateInput = (name, value) => {
   const form = document.getElementById("campaignForm");
   if (!form) return;

   let input = form.querySelector(`input[name="${name}"]`);
   if (input) {
      input.value = value;
   } else {
      input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.value = value;
      form.appendChild(input);
   }
};

// DOM Ready Logic
document.addEventListener("DOMContentLoaded", () => {
   const get = (selector) => document.querySelector(selector);
   const getAll = (selector) => document.querySelectorAll(selector);

   const reviewTable = get(".review-table");
   const reviewList = get(".review-list");
   const dateFilter = get(".date-filter");
   const statusSelect = get("select#status");

   const popupElements = {
      overlay: get(".review-overlay"),
      closeBtn: get(".review-overlay .close-button"),
   };

   // Bind Nice Select
   let selectInstance;
   if (statusSelect) {
      selectInstance = NiceSelect.bind(statusSelect);
   }

   // Init Table UI
   if (reviewTable && reviewList && dateFilter) {
      const total = parseInt(reviewList.dataset.total || "0", 10);
      const isEmpty = reviewList.children.length === 0;

      reviewTable.classList.toggle("hide", isEmpty);
      dateFilter.classList.toggle("hide", total === 0);

      reviewTable.addEventListener("click", async (e) => {
         const editBtn = e.target.closest(".dashicons-edit");
         if (!editBtn) return;

         const campaignID = editBtn.dataset.camp;
         if (!campaignID) return;

         const popup = new PopupHandler(popupElements.overlay, popupElements.closeBtn);
         popup.show();

         const formData = new FormData();
         formData.append("action", "autofill_campaign");
         formData.append("campaign_id", campaignID);

         try {
            const res = await fetch(window.ajaxurl, {
               method: "POST",
               credentials: "same-origin",
               body: formData,
            });

            const json = await res.json();

            if (json.success && json.data) {
               for (const [key, value] of Object.entries(json.data)) {
                  const input = get(`#campaignForm [name="${key}"]`);
                  if (input) input.value = value;
               }

               createOrUpdateInput('campaign_id', campaignID);

               if (selectInstance) {
                  selectInstance.update();
               }
            }
         } catch (err) {
            console.error("Failed to autofill campaign:", err);
         }
      });
   }

   // Campaign Popup Trigger
   const campaignTrigger = get("#createCampaign");
   if (campaignTrigger && popupElements.overlay && popupElements.closeBtn) {
      const popup = new PopupHandler(popupElements.overlay, popupElements.closeBtn);
      campaignTrigger.addEventListener("click", () => popup.show());
   }
});

// Global: Copy URL Button Handler
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
