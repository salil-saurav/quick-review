// ======= Utility Functions =======

const AlertService = {
   fadeAlert() {
      setTimeout(() => {
         const alert = document.querySelector('.custom-alert');
         if (alert) {
            alert.classList.add('slideup');
            setTimeout(() => alert.remove(), 500);
         }
      }, 5000);
   },

   show(message, type = 'info') {
      const wrapper = document.createElement('div');
      wrapper.className = `custom-alert show ${type}`;
      wrapper.setAttribute('role', 'alert');
      wrapper.innerHTML = `<div class="alert-message">${message}</div>`;
      document.body.appendChild(wrapper);
      this.fadeAlert();
   }
};

const ClipboardService = {
   async copy(text) {
      if (!navigator.clipboard) {
         AlertService.show("Clipboard API not supported in this browser.", "error");
         return;
      }

      try {
         await navigator.clipboard.writeText(text);
         AlertService.show("URL copied to clipboard", "success");
      } catch (err) {
         console.error("Clipboard write failed:", err);
         AlertService.show("Failed to copy URL", "error");
      }
   }
};

// ======= Modal Handler =======

class PopupHandler {
   constructor(overlay, closeBtn) {
      this.overlay = overlay;
      this.modal = overlay.querySelector('.modal');
      this.closeBtn = closeBtn;
      this.isAnimating = false;

      this.initEvents();
   }

   show() {
      if (this.isAnimating) return;

      this.isAnimating = true;
      this.modal.classList.remove('exit', 'pop');
      this.overlay.style.display = 'flex';

      requestAnimationFrame(() => {
         this.overlay.classList.add('show');
         setTimeout(() => this.isAnimating = false, 400);
      });
   }

   hide() {
      if (this.isAnimating) return;

      this.isAnimating = true;
      this.modal.classList.add('exit');

      this.modal.querySelectorAll('form input').forEach(element => {

         element.value = '';
      });

      this.overlay.classList.remove('show');

      setTimeout(() => {
         this.overlay.style.display = 'none';
         this.modal.classList.remove('exit');
         this.isAnimating = false;
      }, 300);
   }

   shake() {
      if (this.modal.classList.contains('pop')) return;

      this.modal.classList.add('pop');
      setTimeout(() => this.modal.classList.remove('pop'), 500);
   }

   initEvents() {
      this.closeBtn.addEventListener('click', (e) => {
         e.stopPropagation();
         this.hide();
      });

      this.overlay.addEventListener('click', (e) => {
         if (e.target === this.overlay) this.shake();
      });

      document.addEventListener('keydown', (e) => {
         if (e.key === 'Escape' && this.overlay.classList.contains('show')) {
            this.hide();
         }
      });
   }
}
