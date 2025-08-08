// DOM Utilities
const Helpers = {
   insertChip(text = "", value = "", container) {
      const chip = document.createElement("div");
      chip.className = "chip button-primary";
      chip.textContent = text;
      chip.dataset.value = value;

      const closeBtn = document.createElement("span");
      closeBtn.textContent = "X";
      chip.appendChild(closeBtn);

      container.appendChild(chip);
   },
   qs: (selector, scope = document) => scope.querySelector(selector),
   qsa: (selector, scope = document) => Array.from(scope.querySelectorAll(selector)),
   bindSelect(id) {
      const el = document.getElementById(id);
      return el ? NiceSelect.bind(el) : null;
   }
};

// QR Setup wizard configuration
document.addEventListener("DOMContentLoaded", () => {

   // Post Type Chip Select Logic
   const postTypeElements = {
      select: document.getElementById("qrs_post_type"),
      container: Helpers.qs(".selected_posts_container"),
      input: document.getElementById("selected_post_types")
   };

   if (Object.values(postTypeElements).some(el => !el)) return;

   const { select, container, input } = postTypeElements;
   const selectedValues = [];

   // Restore chips on reload
   Helpers.qsa(".chip", container).forEach(chip => {
      selectedValues.push(chip.dataset.value);
   });
   input.value = selectedValues;

   const selectInstance = NiceSelect.bind(select);

   select.addEventListener("change", () => {
      const selectedOption = select.options[select.selectedIndex];

      Helpers.insertChip(selectedOption.textContent, selectedOption.value, container);
      selectedOption.disabled = true;

      selectedValues.push(selectedOption.value);
      input.value = selectedValues;

      selectInstance.update();
   });

   container.addEventListener("click", (e) => {
      if (e.target.tagName.toLowerCase() !== 'span') return;

      const chip = e.target.closest(".chip");
      if (!chip) return;

      const value = chip.dataset.value;
      const option = select.querySelector(`option[value="${value}"]`);
      const index = selectedValues.indexOf(value);

      if (index !== -1) selectedValues.splice(index, 1);

      input.value = selectedValues.join(",");
      chip.remove();
      if (option) option.disabled = false;

      selectInstance.update();
   });
});
