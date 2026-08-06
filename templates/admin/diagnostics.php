<?php
declare(strict_types=1);

$diagnosticId = is_string($diagnosticId ?? null) ? $diagnosticId : '';
$lookupAttempted = (bool) ($lookupAttempted ?? false);
$selectedDiagnostic = is_array($selectedDiagnostic ?? null) ? $selectedDiagnostic : null;
$selectedDiagnosticBytes = is_string($selectedDiagnosticBytes ?? null) ? $selectedDiagnosticBytes : null;
$recentDiagnostics = is_array($recentDiagnostics ?? null) ? $recentDiagnostics : [];
$downloadUrl = is_string($downloadUrl ?? null) ? $downloadUrl : null;
?>
<div class="wrap edis-admin" data-edis-page="diagnostics">
<h1><?php echo esc_html__('Diagnostics', 'edis-evidence-exporter'); ?></h1>
<p class="edis-lead"><?php echo esc_html__('Resolve one incident-specific, privacy-safe canonical JSON record for direct language-model analysis. The human summary below is rendered only from that same record.', 'edis-evidence-exporter'); ?></p>

<section class="edis-panel">
<h2><?php echo esc_html__('Find a diagnostic artifact', 'edis-evidence-exporter'); ?></h2>
<form method="get">
<input type="hidden" name="page" value="edis-evidence-diagnostics">
<label for="edis-diagnostic-id"><strong><?php echo esc_html__('Diagnostic ID', 'edis-evidence-exporter'); ?></strong></label>
<input id="edis-diagnostic-id" class="regular-text code" type="text" name="diagnostic_id" value="<?php echo esc_attr($diagnosticId); ?>" pattern="edis-diag-[a-f0-9]{32}" maxlength="42" autocomplete="off">
<?php submit_button(__('Resolve diagnostic', 'edis-evidence-exporter'), 'secondary', '', false); ?>
</form>
<?php if ($lookupAttempted && $selectedDiagnostic === null): ?>
<div class="notice notice-error inline"><p><?php echo esc_html__('The diagnostic artifact is unavailable, expired, or not authorized for the current user and site.', 'edis-evidence-exporter'); ?></p></div>
<?php endif; ?>
<?php if ($recentDiagnostics !== []): ?>
<h3><?php echo esc_html__('Recent authorized incidents', 'edis-evidence-exporter'); ?></h3>
<ul>
<?php foreach ($recentDiagnostics as $recent):
    $recentId = (string) ($recent['diagnostic_id'] ?? '');
    if (preg_match('/\Aedis-diag-[a-f0-9]{32}\z/D', $recentId) !== 1) { continue; }
    $recentUrl = add_query_arg(['page' => 'edis-evidence-diagnostics', 'diagnostic_id' => $recentId], admin_url('admin.php'));
?>
<li><a href="<?php echo esc_url($recentUrl); ?>"><code><?php echo esc_html($recentId); ?></code></a> — <?php echo esc_html((string) ($recent['operation_type'] ?? 'UNKNOWN')); ?> — <?php echo esc_html((string) ($recent['terminal_state'] ?? 'UNKNOWN')); ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</section>

<?php if ($selectedDiagnostic !== null && $selectedDiagnosticBytes !== null):
    $identity = is_array($selectedDiagnostic['diagnostic_identity'] ?? null) ? $selectedDiagnostic['diagnostic_identity'] : [];
    $operation = is_array($selectedDiagnostic['operation_identity'] ?? null) ? $selectedDiagnostic['operation_identity'] : [];
    $location = is_array($selectedDiagnostic['failure_location'] ?? null) ? $selectedDiagnostic['failure_location'] : [];
    $classification = is_array($selectedDiagnostic['failure_classification'] ?? null) ? $selectedDiagnostic['failure_classification'] : [];
    $facts = is_array($selectedDiagnostic['recorded_facts'] ?? null) ? $selectedDiagnostic['recorded_facts'] : [];
    $questions = is_array($selectedDiagnostic['unresolved_questions'] ?? null) ? $selectedDiagnostic['unresolved_questions'] : [];
    $recovery = is_array($selectedDiagnostic['recovery_guidance'] ?? null) ? $selectedDiagnostic['recovery_guidance'] : [];
?>
<section class="edis-panel">
<h2><?php echo esc_html__('Incident summary', 'edis-evidence-exporter'); ?></h2>
<dl class="edis-definition-list">
<dt><?php echo esc_html__('Diagnostic ID', 'edis-evidence-exporter'); ?></dt><dd><code><?php echo esc_html((string) ($identity['diagnostic_id'] ?? '')); ?></code></dd>
<dt><?php echo esc_html__('Record type', 'edis-evidence-exporter'); ?></dt><dd><?php echo esc_html((string) ($identity['record_type'] ?? 'UNKNOWN')); ?></dd>
<dt><?php echo esc_html__('Created', 'edis-evidence-exporter'); ?></dt><dd><?php echo esc_html((string) ($identity['created_at'] ?? '')); ?></dd>
<dt><?php echo esc_html__('Expires', 'edis-evidence-exporter'); ?></dt><dd><?php echo esc_html((string) ($identity['expires_at'] ?? '')); ?></dd>
<dt><?php echo esc_html__('Operation', 'edis-evidence-exporter'); ?></dt><dd><?php echo esc_html((string) ($operation['operation_type'] ?? 'UNKNOWN')); ?></dd>
<dt><?php echo esc_html__('Job ID', 'edis-evidence-exporter'); ?></dt><dd><?php echo esc_html(is_string($operation['job_id'] ?? null) ? $operation['job_id'] : __('Not available', 'edis-evidence-exporter')); ?></dd>
<dt><?php echo esc_html__('Failure stage', 'edis-evidence-exporter'); ?></dt><dd><?php echo esc_html(is_string($location['lifecycle_stage'] ?? null) ? $location['lifecycle_stage'] : __('Not proven', 'edis-evidence-exporter')); ?></dd>
<dt><?php echo esc_html__('Subsystem', 'edis-evidence-exporter'); ?></dt><dd><?php echo esc_html(is_string($location['subsystem'] ?? null) ? $location['subsystem'] : __('Not proven', 'edis-evidence-exporter')); ?></dd>
<dt><?php echo esc_html__('Last confirmed state', 'edis-evidence-exporter'); ?></dt><dd><?php echo esc_html(is_string($location['last_successful_state'] ?? null) ? $location['last_successful_state'] : __('Not proven', 'edis-evidence-exporter')); ?></dd>
<dt><?php echo esc_html__('Internal code', 'edis-evidence-exporter'); ?></dt><dd><code><?php echo esc_html((string) ($classification['internal_code'] ?? '')); ?></code></dd>
<dt><?php echo esc_html__('Scope', 'edis-evidence-exporter'); ?></dt><dd><?php echo esc_html((string) ($classification['scope'] ?? 'UNKNOWN')); ?></dd>
<dt><?php echo esc_html__('Retryability', 'edis-evidence-exporter'); ?></dt><dd><?php echo esc_html((string) ($classification['retryability'] ?? 'NOT_PROVEN')); ?></dd>
<dt><?php echo esc_html__('Terminal state', 'edis-evidence-exporter'); ?></dt><dd><?php echo esc_html((string) ($classification['terminal_state'] ?? 'UNKNOWN')); ?></dd>
</dl>
<h3><?php echo esc_html__('Confirmed facts', 'edis-evidence-exporter'); ?></h3>
<ul><?php foreach ($facts as $fact): if (!is_array($fact)) { continue; } ?><li><?php echo esc_html((string) ($fact['statement'] ?? '')); ?> <code><?php echo esc_html((string) ($fact['status'] ?? '')); ?></code></li><?php endforeach; ?></ul>
<h3><?php echo esc_html__('Unresolved evidence', 'edis-evidence-exporter'); ?></h3>
<ul><?php foreach ($questions as $question): if (!is_array($question)) { continue; } ?><li><strong><?php echo esc_html((string) ($question['question'] ?? '')); ?></strong> — <?php echo esc_html((string) ($question['smallest_collection_action'] ?? '')); ?> <code><?php echo esc_html((string) ($question['status'] ?? '')); ?></code></li><?php endforeach; ?></ul>
<h3><?php echo esc_html__('Safe next action', 'edis-evidence-exporter'); ?></h3>
<p><?php echo esc_html((string) ($recovery['safe_immediate_action'] ?? '')); ?></p>
<div class="edis-actions">
<button type="button" class="button button-primary" data-edis-action="copy-canonical-diagnostic"><?php echo esc_html__('Copy diagnostic JSON', 'edis-evidence-exporter'); ?></button>
<?php if ($downloadUrl !== null): ?><a class="button" href="<?php echo esc_url($downloadUrl); ?>"><?php echo esc_html__('Download diagnostic JSON', 'edis-evidence-exporter'); ?></a><?php endif; ?>
</div>
<h3><?php echo esc_html__('Canonical LLM diagnostic JSON', 'edis-evidence-exporter'); ?></h3>
<pre id="edis-canonical-diagnostic-json" class="edis-json-output"><?php echo esc_html($selectedDiagnosticBytes); ?></pre>
</section>
<?php endif; ?>

<section class="edis-panel">
<h2><?php echo esc_html__('Current environment health', 'edis-evidence-exporter'); ?></h2>
<p><?php echo esc_html__('These current checks are separate from the immutable incident artifact and must not be treated as incident-time causal evidence.', 'edis-evidence-exporter'); ?></p>
<div class="edis-actions"><button type="button" class="button button-primary" data-edis-action="worker-test"><?php echo esc_html__('Run safe worker test', 'edis-evidence-exporter'); ?></button><button type="button" class="button" data-edis-action="copy-diagnostics"><?php echo esc_html__('Copy health report', 'edis-evidence-exporter'); ?></button></div>
<div id="edis-worker-test-result" aria-live="polite"></div>
<div class="edis-table-wrap"><table class="widefat striped"><thead><tr><th><?php echo esc_html__('Check', 'edis-evidence-exporter'); ?></th><th><?php echo esc_html__('State', 'edis-evidence-exporter'); ?></th><th><?php echo esc_html__('Detail', 'edis-evidence-exporter'); ?></th></tr></thead><tbody><?php foreach($report['checks'] as $check): ?><tr><td><?php echo esc_html($check['label']); ?></td><td><span class="edis-badge" data-state="<?php echo esc_attr(strtoupper($check['state'])); ?>"><?php echo esc_html(strtoupper($check['state'])); ?></span></td><td><?php echo esc_html((string)$check['detail']); ?></td></tr><?php endforeach; ?></tbody></table></div>
<h3><?php echo esc_html__('Privacy-safe current health JSON', 'edis-evidence-exporter'); ?></h3><pre id="edis-diagnostics-json" class="edis-json-output"><?php echo esc_html(wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
</section>
</div>
