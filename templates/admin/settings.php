<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
$settingsReset = isset($_GET['settings-reset']) && sanitize_text_field(wp_unslash($_GET['settings-reset'])) === '1';
?>
<div class="wrap edis-admin">
    <h1><?php echo esc_html__('EDIS Settings', 'edis-evidence-exporter'); ?></h1>
    <p class="edis-lead"><?php echo esc_html__('Settings define safe defaults. Every export remains reviewable before execution.', 'edis-evidence-exporter'); ?></p>
    <?php settings_errors(); ?>
    <?php if ($settingsReset) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Settings were reset to documented defaults.', 'edis-evidence-exporter'); ?></p></div><?php endif; ?>
    <form method="post" action="options.php" class="edis-panel">
        <?php settings_fields('edis_evidence_settings'); do_settings_sections('edis-evidence-settings'); submit_button(__('Save Settings', 'edis-evidence-exporter')); ?>
    </form>
    <section class="edis-panel edis-danger-zone"><h2><?php echo esc_html__('Reset defaults', 'edis-evidence-exporter'); ?></h2><p><?php echo esc_html__('This removes only EDIS preference options. It does not delete completed bundles immediately.', 'edis-evidence-exporter'); ?></p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="edis_reset_settings"><?php wp_nonce_field('edis_evidence_reset_settings'); ?><button type="submit" class="button"><?php echo esc_html__('Reset to defaults', 'edis-evidence-exporter'); ?></button></form></section>
</div>
