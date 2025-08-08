<?php

if (!current_user_can('manage_options')) {
   wp_die(__('You do not have sufficient permissions to access this page.'));
}

$nonce = wp_create_nonce('qrs_save_activation_form');
$roles = wp_roles()->roles;

require_once QUICK_REVIEW_PLUGIN_DIR . '/helper/settings.php';

$post_types = get_post_types([
   'public'   => true,
   '_builtin' => false
], 'names');

$config = get_plugin_settings();
$config_post_types = [];
if ($config && isset($config['post_types'])) {
   $config_post_types = explode(",", $config['post_types'][0]);
}
?>

<div class="wrap">
   <?php if (empty($post_types)) : ?>
      <div class="no-custom-posts">
         <p>
            <?php esc_html_e('No custom post types found. Please create a custom post type first.', 'quick-review'); ?>
         </p>
      </div>
   <?php else : ?>
      <h1>Quick Review Setup Wizard</h1>
      <div class="setup-wizard-container">
         <h2 class="setup-wizard-title">Configure Your Course Settings</h2>
         <form method="post">
            <div class="setup_inner">
               <div class="select_container">
                  <label for="qrs_post_type">Select Post Type/s:</label>
                  <select name="qrs_post_type" id="qrs_post_type" style="width: 100%;">
                     <?php foreach ($post_types as $post_type): ?>
                        <option value="<?php echo esc_attr($post_type); ?>" <?php disabled(in_array($post_type, $config_post_types)); ?>>
                           <?php echo esc_html($post_type); ?>
                        </option>
                     <?php endforeach; ?>
                  </select>
               </div>
               <div class="selected_posts_container">
                  <?php foreach ($config_post_types as $post_type): ?>
                     <?php if (!empty($post_type)): ?>
                        <div class="chip button-primary" data-value="<?php echo esc_attr($post_type); ?>">
                           <?php echo esc_html($post_type); ?><span>X</span>
                        </div>
                     <?php endif; ?>
                  <?php endforeach; ?>
               </div>
               <input type="hidden" name="selected_post_type" id="selected_post_types">
            </div>
            <input type="hidden" name="qrs_form_nonce" value="<?php echo esc_attr($nonce); ?>" />
            <p class="form-submit">
               <input type="submit" class="button-primary" value="Save Settings" />
            </p>
         </form>
      </div>
   <?php endif; ?>
</div>


<script src="<?= QUICK_REVIEW_PLUGIN_URL ?>assets/js/setup.js"></script>
