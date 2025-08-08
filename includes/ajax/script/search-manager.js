// Constants
const CONFIG = {

   MINIMUM_SEARCH_LENGTH: 2,
   DEFAULT_DEBOUNCE_DELAY: 500,

   SELECTORS: {
      POST: {
         search: '#selected_post',
         loader: '.courseLoader',
         results: '.course_search_results',
         hiddenFields: {
            post_id: '#campaignForm input[name="post_id"]',
         }
      }
   }
};

// Utils
const Utils = {
   debounce(func, delay) {
      let timeoutId;
      return function (...args) {
         clearTimeout(timeoutId);
         timeoutId = setTimeout(() => func.apply(this, args), delay);
      };
   },

   highlightText(text, searchTerm) {
      const regex = new RegExp(`(${searchTerm})`, "gi");
      return text.replace(regex, '<strong>$1</strong>');
   }
};


// const ajaxUrl = qrData.ajax_url;

class DOMManager {
   static getElements(selectors) {
      const elements = {};
      Object.entries(selectors).forEach(([key, selector]) => {
         if (typeof selector === 'object') {
            elements[key] = this.getElements(selector);
         } else {
            elements[key] = document.querySelector(selector);
         }
      });
      return elements;
   }
}

/**
 * Handles search functionality with debouncing, AJAX requests, and result display
 * @class
 * @classdesc A class that manages search operations including user input handling, AJAX requests, and search result display
 * @param {Object} config - Configuration object for the search handler
 * @param {Object} config.selectors - DOM selectors for various elements
 * @param {HTMLElement} config.selectors.search - Search input element
 * @param {HTMLElement} config.selectors.loader - Loading indicator element
 * @param {HTMLElement} config.selectors.results - Results container element
 * @param {Object} config.selectors.hiddenFields - Hidden form fields
 * @param {string} config.action - AJAX action name
 * @param {('user'|'post')} config.searchType - Type of search ('user' or 'post')
 * @param {number} [config.delay] - Debounce delay in milliseconds
 * @throws {Error} Throws an error if AJAX request fails
 * @example
 * const searchHandler = new SearchHandler({
 *   selectors: {
 *     search: document.querySelector('#search'),
 *     loader: document.querySelector('#loader'),
 *     results: document.querySelector('#results'),
 *     hiddenFields: document.querySelectorAll('[type="hidden"]')
 *   },
 *   action: 'search_action',
 *   searchType: 'user',
 *   delay: 300
 * });
 */
class SearchHandler {
   constructor(config) {
      this.elements = DOMManager.getElements({
         search: config.selectors.search,
         loader: config.selectors.loader,
         results: config.selectors.results,
         hiddenFields: config.selectors.hiddenFields
      });

      // Check if required elements exist
      if (!this.elements.search || !this.elements.loader || !this.elements.results) {
         return;
      }

      this.config = {
         action: config.action,
         searchType: config.searchType,
         delay: config.delay || CONFIG.DEFAULT_DEBOUNCE_DELAY
      };

      this.handleSearch = Utils.debounce(this.performSearch.bind(this), this.config.delay);
      this.initializeEventListeners();
   }

   initializeEventListeners() {
      this.elements.search.addEventListener('input', () => {
         this.elements.loader.style.display = 'block';
         this.handleSearch();
      });

      this.elements.search.addEventListener('click', () => {
         if (!this.elements.search.value.length) return;
         this.elements.loader.style.display = 'block';
         this.handleSearch();
      });
   }

   updateHiddenFields(dataset) {
      Object.entries(this.elements.hiddenFields).forEach(([key, element]) => {
         element.value = dataset[key];
      });
   }

   createResultItem(data, searchTerm) {
      const item = document.createElement('li');
      Object.entries(data).forEach(([key, value]) => {
         item.dataset[key.toLowerCase()] = value;
      });

      const primaryText = this.config.searchType === 'user' ? data.display_name : data.title;
      const secondaryText = this.config.searchType === 'user' ? data.email : data.post_id.toString();


      item.innerHTML = `
         <span class="primary-text">${Utils.highlightText(primaryText, searchTerm)}</span>
         <span class="secondary-text">(${Utils.highlightText(secondaryText, searchTerm)})</span>
      `;

      return item;
   }

   async performSearch() {
      const searchTerm = this.elements.search.value.trim();
      this.elements.results.innerHTML = '';

      if (searchTerm.length < CONFIG.MINIMUM_SEARCH_LENGTH) {
         this.elements.loader.style.display = 'none';
         return;
      }

      try {
         const response = await this.fetchResults(searchTerm);
         this.handleSearchResponse(response, searchTerm);
      } catch (error) {
         console.error('Search error:', error);
         this.elements.results.innerHTML = '<p>An error occurred while searching.</p>';
      } finally {
         this.elements.loader.style.display = 'none';
      }
   }

   async fetchResults(searchTerm) {
      const formData = new FormData();
      formData.append('action', this.config.action);
      formData.append('search_term', searchTerm);
      formData.append('nonce', qrData.nonce);

      const response = await fetch(window.ajaxurl, {
         method: 'POST',
         credentials: 'same-origin',
         body: formData
      });

      return response.json();
   }

   handleSearchResponse(response, searchTerm) {
      if (!response.success) {
         this.elements.results.innerHTML = `<p>${response.data.message}</p>`;
         return;
      }

      const resultsList = this.createResultsList(response.data, searchTerm);
      this.elements.results.appendChild(resultsList);
      this.bindResultEvents(resultsList);
   }

   createResultsList(data, searchTerm) {
      const list = document.createElement('ul');
      list.className = 'results-list';

      const items = this.config.searchType === 'user' ? data.users : data.posts;
      items.forEach(item => {
         list.appendChild(this.createResultItem(item, searchTerm));
      });

      return list;
   }

   bindResultEvents(resultsList) {
      resultsList.querySelectorAll('li').forEach(item => {
         item.addEventListener('click', () => {
            this.updateHiddenFields(item.dataset);
            this.elements.search.value = this.config.searchType === 'user'
               ? item.dataset.display_name
               : item.dataset.title;
            this.elements.results.innerHTML = '';
         });
      });
   }
}

new SearchHandler({
   selectors: CONFIG.SELECTORS.POST,
   action: 'search_posts',
   searchType: 'post',
});

