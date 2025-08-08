<div class="qr-error-overlay">
   <div class="qr-error-message">
      <p><?php esc_html_e('In order to create review link please select a custom post type from setup page.', 'quick-review'); ?></p>
      <a href="<?php echo esc_url(admin_url('admin.php?page=qrs-setup-wizard')); ?>" class="qr-setup-button button button-secondry">
         <?php esc_html_e('Go to setup page', 'quick-review'); ?>
      </a>
   </div>
</div>