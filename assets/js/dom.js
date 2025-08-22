// ===== Configurationuration =====
const Configuration = {
   selectors: {
      campaignItemTable: '.review-table',
      reviewList: '.review-list',
      dateFilter: '.date-filter',
      modalLoader: '.loader.modalLoader',
      editButton: 'button[data-edit="campaign"]',
      campaignForm: '#campaignForm',
      confirmationOverlay: '.confirmation-overlay',
      confirmationClose: '.confirmation-overlay .close-button',
      campaignPage: '.campaign-dashboard',
      createCampaignTrigger: '#createCampaign',
      popupOverlay: '.review-overlay',
      popupCloseBtn: '.review-overlay .close-button'
   },
   classes: {
      loading: 'loading',
      hide: 'hide',
      removeRow: 'remove-row',
      editIcon: 'dashicons-edit',
      copyUrl: 'copy-url'
   }
};

// ===== Utility Functions =====
const DOMUtils = {
   $: (selector) => document.querySelector(selector),
   $$: (selector) => document.querySelectorAll(selector),

   createOrUpdateHiddenInput(form, name, value) {
      if (!form) return;

      let input = form.querySelector(`input[name="${name}"]`);
      if (!input) {
         input = document.createElement('input');
         input.type = 'hidden';
         input.name = name;
         form.appendChild(input);
      }
      input.value = value;
   },

   toggleLoader(form, loader, show = true) {
      if (!form || !loader) return;

      form.classList.toggle(Configuration.classes.loading, show);
      loader.style.display = show ? 'block' : 'none';
   },

   updateFormFields(form, data) {
      if (!form || !data) return;

      Object.entries(data).forEach(([key, value]) => {
         const input = form.querySelector(`[name="${key}"]`);
         if (input) input.value = value;
      });
   }
};

// ===== API Service =====
const APIService = {
   async makeRequest(action, additionalData = {}) {
      const formData = new FormData();
      formData.append('action', action);

      Object.entries(additionalData).forEach(([key, value]) => {
         formData.append(key, value);
      });

      const response = await fetch(window.ajaxurl, {
         method: 'POST',
         credentials: 'same-origin',
         body: formData,
      });

      return response.json();
   },

   async deleteRow(key, flag) {
      if (flag === 'reviewUrl') {
         return this.makeRequest('delete_campaign_item', { reference: key });
      } else {
         console.log("this ran");

         return this.makeRequest('delete_campaign', { id: key });
      }
   },

   async getCampaignData(campaignId) {
      return this.makeRequest('autofill_campaign', { campaign_id: campaignId });
   }

};

// ===== Campaign Management =====
class CampaignManager {
   constructor() {
      this.elements = this.initializeElements();
      this.popup = null;
      this.confirmationPopup = null;
   }

   initializeElements() {
      const elements = {};
      Object.entries(Configuration.selectors).forEach(([key, selector]) => {
         elements[key] = DOMUtils.$(selector);
      });
      return elements;
   }

   async handleDelete(key, flag, popupInstance, deleteButton) {
      deleteButton.textContent = 'Removing...';

      try {
         const response = await APIService.deleteRow(key, flag);

         if (response.success && response.data) {
            AlertService.show(response.data.message, 'success');
            deleteButton.textContent = 'Removed';
         } else {
            AlertService.show(response.data.message, 'error');
         }
      } catch (error) {
         console.error('Failed to delete campaign:', error);
         deleteButton.textContent = 'Failed';
      } finally {
         popupInstance.hide();
         location.reload();
      }
   }

   async loadCampaignData(campaignId) {
      if (!campaignId || !this.elements.campaignForm) return;

      DOMUtils.toggleLoader(this.elements.campaignForm, this.elements.modalLoader, true);

      try {
         const response = await APIService.getCampaignData(campaignId);

         if (response.success && response.data) {
            DOMUtils.updateFormFields(this.elements.campaignForm, response.data);
            DOMUtils.createOrUpdateHiddenInput(this.elements.campaignForm, 'campaign_id', campaignId);
         }
      } catch (error) {
         console.error('Failed to load campaign data:', error);
      } finally {
         DOMUtils.toggleLoader(this.elements.campaignForm, this.elements.modalLoader, false);
      }
   }

   setupTableUI() {
      const { campaignItemTable, reviewList, dateFilter } = this.elements;

      if (!campaignItemTable || !reviewList || !dateFilter) return;

      const total = parseInt(reviewList.dataset.total || '0', 10);
      const hasItems = reviewList.children.length > 0;

      campaignItemTable.classList.toggle(Configuration.classes.hide, !hasItems);
      dateFilter.classList.toggle(Configuration.classes.hide, total === 0);
   }

   setupConfirmationDialog() {
      if (!this.elements.confirmationOverlay) return;

      this.confirmationPopup = new PopupHandler(
         this.elements.confirmationOverlay,
         this.elements.confirmationClose
      );

      const cancelButton = this.elements.confirmationOverlay.querySelector('.cancel-delete');
      const deleteButton = document.getElementById('removeRow');

      if (cancelButton) {
         cancelButton.addEventListener('click', () => this.confirmationPopup.hide());
      }

      if (deleteButton) {
         deleteButton.addEventListener('click', async () => {
            const key = deleteButton.dataset.id;

            if (this.elements.campaignPage) {
               await this.handleDelete(key, 'reviewUrl', this.confirmationPopup, deleteButton);
            } else {
               await this.handleDelete(key, '', this.confirmationPopup, deleteButton);

            }
         });
      }
   }

   setupEventListeners() {
      this.setupEditButtonListener();
      this.setupTableClickListener();
      this.setupCreateCampaignListener();
   }

   setupEditButtonListener() {
      if (!this.elements.editButton) return;

      this.elements.editButton.addEventListener('click', async () => {
         const campaignId = this.elements.editButton.dataset.camp;
         if (campaignId) {
            this.showPopupAndLoadData(campaignId);
         }
      });
   }

   setupTableClickListener() {
      if (!this.elements.campaignItemTable) return;

      this.elements.campaignItemTable.addEventListener('click', async (e) => {
         // Handle edit button clicks
         const editBtn = e.target.closest(`.${Configuration.classes.editIcon}`);
         if (editBtn) {
            const campaignId = editBtn.dataset.camp;
            if (campaignId) {
               this.showPopupAndLoadData(campaignId);
            }
            return;
         }

         // Handle Campaign Delete button clicks

         if (!this.elements.campaignPage && e.target.classList.contains(Configuration.classes.removeRow)) {
            const campaignId = e.target.dataset.id;

            if (campaignId && this.confirmationPopup) {

               this.confirmationPopup.show();

               const deleteButton = document.getElementById('removeRow');
               if (deleteButton) {
                  deleteButton.dataset.id = campaignId;
               }
            }
         }

         // Handle delete button clicks (only on campaign page)
         if (this.elements.campaignPage && e.target.classList.contains(Configuration.classes.removeRow)) {
            const reference = e.target.dataset.reference;
            if (reference && this.confirmationPopup) {

               this.confirmationPopup.show();
               const deleteButton = document.getElementById('removeRow');
               if (deleteButton) {
                  deleteButton.dataset.id = reference;
               }
            }
         }
      });
   }

   setupCreateCampaignListener() {
      if (!this.elements.createCampaignTrigger) return;

      this.elements.createCampaignTrigger.addEventListener('click', () => {
         if (this.popup) {
            this.popup.show();
         }
      });
   }

   showPopupAndLoadData(campaignId) {
      if (this.popup) {
         this.popup.show();
         this.loadCampaignData(campaignId);
      }
   }

   initializePopup() {
      if (this.elements.popupOverlay && this.elements.popupCloseBtn) {
         this.popup = new PopupHandler(this.elements.popupOverlay, this.elements.popupCloseBtn);
      }
   }

   init() {
      this.initializePopup();
      this.setupTableUI();
      this.setupConfirmationDialog();
      this.setupEventListeners();
   }
}

// ===== Global Event Handlers =====
class GlobalEventHandlers {
   static setupCopyUrlHandler() {
      document.addEventListener('click', (e) => {
         const btn = e.target.closest(`button.${Configuration.classes.copyUrl}`);
         if (!btn) return;

         const url = btn.dataset.target;
         if (!url) {
            console.warn('Missing data-target attribute for copy URL button.');
            return;
         }

         ClipboardService.copy(url);
      });
   }

   static init() {
      this.setupCopyUrlHandler();
   }
}

// ===== Application Initialization =====
class App {
   constructor() {
      this.campaignManager = new CampaignManager();
   }

   init() {
      this.campaignManager.init();
      GlobalEventHandlers.init();
   }
}

// ===== DOM Ready =====
document.addEventListener('DOMContentLoaded', () => {
   const app = new App();
   app.init();
});
