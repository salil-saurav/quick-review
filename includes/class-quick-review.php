<?php

require_once plugin_dir_path(__FILE__) . 'class-database-manager.php';

/**
 * Class Quick_Review
 *
 * Main class for the Quick Review WordPress plugin.
 * Handles initialization, activation/deactivation hooks, admin menu registration,
 * script/style enqueuing, screen options, and rendering of admin pages.
 *
 * @package Quick_Review
 *
 * Properties:
 * @property string $base_url Base URL for plugin assets.
 *
 * Methods:
 * @method void init() Initializes plugin dependencies and hooks.
 * @method static void activate() Handles plugin activation logic.
 * @method static void deactivate() Handles plugin deactivation logic.
 * @method void enqueue_scripts() Enqueues admin scripts and styles for plugin pages.
 * @method bool is_plugin_page() Determines if current page is a plugin-related admin page.
 * @method void register_admin_menu() Registers admin menu and submenus for the plugin.
 * @method void conditionally_add_screen_options(WP_Screen $screen) Adds screen options for specific plugin pages.
 * @method void render_admin_page() Renders the main admin page template.
 * @method void render_campaign_item() Renders the campaign dashboard template.
 * @method void render_setup_wizard() Renders the setup wizard template.
 * @method void render_logs() Renders the logs page template.
 * @method void handle_screen_options() Handles screen options and redirects for admin pages.
 * @method mixed save_screen_option($status, $option, $value) Saves custom screen option values.
 */

class Quick_Review
{
   private $base_url = QUICK_REVIEW_PLUGIN_URL;

   public function init(): void
   {
      $this->load_dependencies();
      $this->init_hooks();
   }

   public static function activate(): void
   {
      if (is_admin()) {
         update_option('qrs_do_activation_redirect', true);
      }
      new ReviewList(); // Assuming this is required for DB setup or logic
   }

   public static function deactivate(): void
   {
      delete_transient('quick_review_activation_notice');
      global $wpdb;

      $setting_table = $wpdb->prefix . QR_SETTINGS_TABLE;
      $wpdb->query("DROP TABLE IF EXISTS {$setting_table}");
   }

   private function load_dependencies(): void
   {
      foreach (glob(QUICK_REVIEW_PLUGIN_DIR . 'includes/ajax/*.php') as $ajaxFile) {
         require_once $ajaxFile;
      }

      require_once QUICK_REVIEW_PLUGIN_DIR . 'includes/actions.php';
   }

   private function init_hooks(): void
   {
      add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
      add_action('admin_menu', [$this, 'register_admin_menu']);
      add_action('load-toplevel_page_quick-review', [$this, 'handle_screen_options']);
      add_action('current_screen', [$this, 'conditionally_add_screen_options']);

      add_filter('set-screen-option', [$this, 'save_screen_option'], 10, 3);
   }

   public function enqueue_scripts(): void
   {
      if (!$this->is_plugin_page()) {
         return;
      }

      $version = '1.0.0';

      wp_enqueue_style('qr-nice-select-style', $this->base_url . 'lib/nice-select2.css', [], $version);
      wp_enqueue_style('qr-quick-review-style', $this->base_url . 'assets/css/admin.css', [], $version);

      wp_enqueue_script('qr-nice-select-script', $this->base_url . 'lib/nice-select2.js', [], $version, true);
      wp_enqueue_script('qr-ajax-search-manager', $this->base_url . 'includes/ajax/script/search-manager.js', [], $version, true);
      wp_enqueue_script('qr-helper-class', $this->base_url . 'assets/js/helper-class.js', [], $version, false);
      wp_enqueue_script('qr-ajax-dom-script', $this->base_url . 'assets/js/dom.js', [], $version, true);

      wp_localize_script('qr-ajax-search-manager', 'qrData', [
         'nonce'         => wp_create_nonce('search_nonce'),
         'details_nonce' => wp_create_nonce('details'),
      ]);
   }

   private function is_plugin_page(): bool
   {
      if (is_admin() && function_exists('get_current_screen')) {
         $screen = get_current_screen();
         return isset($screen->id) && (
            strpos($screen->id, 'quick-review') !== false ||
            strpos($screen->id, 'campaign-dashboard') !== false
         );
      }

      global $post;
      return is_singular() && isset($post->post_content) && has_shortcode($post->post_content, 'quick_review');
   }

   public function register_admin_menu(): void
   {
      add_menu_page(
         __('Quick Review', 'quick-review'),
         __('Quick Review', 'quick-review'),
         'manage_options',
         'quick-review',
         [$this, 'render_admin_page'],
         'dashicons-star-filled',
         80
      );

      add_submenu_page(
         ' ',
         'Campaign Item',
         'Campaign Item',
         'manage_options',
         'campaign-dashboard',
         [$this, 'render_campaign_item']
      );

      add_submenu_page(
         'quick-review',
         __('Setup Wizard', 'quick-review'),
         __('Setup Wizard', 'quick-review'),
         'manage_options',
         'qrs-setup-wizard',
         [$this, 'render_setup_wizard']
      );
   }

   public function conditionally_add_screen_options(WP_Screen $screen): void
   {
      if (isset($_GET['page']) && $_GET['page'] === 'campaign-dashboard') {
         add_screen_option('per_page', [
            'label'   => __('Items per page', 'quick-review'),
            'default' => 15,
            'option'  => 'campaign_items_per_page',
         ]);
      }
   }

   public function render_admin_page(): void
   {
      include QUICK_REVIEW_PLUGIN_DIR . 'templates/admin-page.php';
   }

   public function render_campaign_item(): void
   {
      include QUICK_REVIEW_PLUGIN_DIR . 'templates/campaign-item-dashboard.php';
   }

   public function render_setup_wizard(): void
   {
      include QUICK_REVIEW_PLUGIN_DIR . 'templates/setup-wizard.php';
   }

   public function handle_screen_options(): void
   {
      // Optional redirect to ?paged=1 if not set
      if (!isset($_GET['paged'])) {
         wp_safe_redirect(add_query_arg([
            'page'  => 'quick-review',
            'paged' => 1,
         ], admin_url('admin.php')));
         exit;
      }

      // Add per-page screen option
      add_screen_option('per_page', [
         'label'   => __('Number of items per page:', 'quick-review'),
         'default' => 50,
         'option'  => 'quick_review_per_page',
      ]);
   }
   public function save_screen_option($status, $option, $value)
   {
      $supported_options = [
         'quick_review_per_page',
         'campaign_items_per_page',
      ];

      if (in_array($option, $supported_options, true)) {
         return (int) $value;
      }

      return $status;
   }
}
