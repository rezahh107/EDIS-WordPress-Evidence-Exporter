<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class WordPressIntegrationContractTest extends TestCase
{
    public function testLifecycleIsNetworkAwareAndUsesSeparateUninstallSemantics(): void
    {
        $lifecycle = $this->read('src/WordPress/LifecycleManager.php');
        $bootstrap = $this->read('edis-evidence-exporter.php');
        $uninstall = $this->read('uninstall.php');

        self::assertStringContainsString('register_activation_hook', $bootstrap);
        self::assertStringContainsString('register_deactivation_hook', $bootstrap);
        self::assertStringContainsString("add_action( 'wp_initialize_site'", $lifecycle);
        self::assertStringContainsString('activateNetwork', $lifecycle);
        self::assertStringContainsString('array_reverse( array_keys( $activation_states ) )', $lifecycle);
        self::assertStringContainsString("defined( 'WP_UNINSTALL_PLUGIN' )", $uninstall);
        self::assertStringContainsString('edis_evidence_retain_data_on_uninstall', $uninstall);
        self::assertStringContainsString('is_multisite()', $uninstall);
    }

    public function testSiteHealthPrivacyCliAndConditionalLoadingAreRegistered(): void
    {
        $bootstrap = $this->read('src/Bootstrap.php');
        $siteHealth = $this->read('src/WordPress/SiteHealthIntegration.php');
        $privacy = $this->read('src/WordPress/PrivacyIntegration.php');
        $cli = $this->read('src/WordPress/CliCommands.php');
        $runtime = $this->read('src/WordPress/RuntimeContext.php');
        $recovery = $this->read('src/WordPress/WorkerRecovery.php');

        self::assertStringContainsString('requiresApplicationRuntime', $bootstrap);
        self::assertStringContainsString('site_status_tests', $siteHealth);
        self::assertStringContainsString('async_direct_test', $siteHealth);
        self::assertStringContainsString('wp_privacy_personal_data_exporters', $privacy);
        self::assertStringContainsString('wp_privacy_personal_data_erasers', $privacy);
        self::assertStringContainsString("WP_CLI::add_command( 'edis storage self-test'", $cli);
        self::assertStringContainsString("WP_CLI::add_command( 'edis jobs repair'", $cli);
        self::assertStringContainsString('str_contains( $uri, $prefix )', $runtime);
        self::assertStringContainsString('recoveryBatch', $recovery);
        self::assertStringContainsString('wp_schedule_single_event', $recovery);
    }

    public function testMultisiteStorageIsNamespacedAndWorkerUsesLeases(): void
    {
        $storage = $this->read('src/Infrastructure/Support/PrivateStorage.php');
        $worker = $this->read('src/Application/ExportJobService.php');

        self::assertStringContainsString("'/site-'", $storage);
        self::assertStringContainsString("'lease_owner'", $worker);
        self::assertStringContainsString("'lease_expires_at'", $worker);
        self::assertStringContainsString("'job_format_version' => '2.1.0'", $worker);
    }

    public function testProductionCodeDoesNotUsePhpErrorSuppressionOperator(): void
    {
        foreach (['edis-evidence-exporter.php', 'uninstall.php', 'src', 'templates'] as $target) {
            foreach ($this->phpFiles($target) as $path) {
                $contents = file_get_contents($path);
                self::assertIsString($contents);
                foreach (token_get_all($contents) as $token) {
                    self::assertFalse($token === '@', 'Unexpected error suppression in ' . $path);
                }
            }
        }
    }

    private function read(string $relative): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
        self::assertIsString($contents);
        return $contents;
    }

    /** @return list<string> */
    private function phpFiles(string $relative): array
    {
        $path = dirname(__DIR__, 2) . '/' . $relative;
        if (is_file($path)) {
            return [$path];
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getExtension() === 'php') {
                $files[] = $item->getPathname();
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    public function testFilesystemPreflightDoesNotRequestInteractiveCredentials(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/WordPress/WordPressFilesystemPreflightAdapter.php');
        self::assertIsString($source);
        self::assertStringContainsString('get_filesystem_method', $source);
        self::assertStringContainsString('ALLOW_ONLY_AFTER_EDIS_STORAGE_SELF_TEST', $source);
        self::assertStringNotContainsString('request_filesystem_credentials', $source);
    }

    public function testPrivacyCleanupIsSiteScopedAndHandlesDeletedDocuments(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/WordPress/PrivacyIntegration.php');
        self::assertIsString($source);
        self::assertStringContainsString('before_delete_post', $source);
        self::assertStringContainsString('jobsForDocument', $source);
        self::assertStringContainsString('get_current_blog_id', $source);
    }


    public function testRecoverySchedulingAvoidsDuplicatesAndClearsTerminalHooks(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/Application/ExportJobService.php');
        self::assertIsString($source);
        self::assertStringContainsString("wp_next_scheduled('edis_process_export_job'", $source);
        self::assertStringContainsString("wp_clear_scheduled_hook('edis_process_export_job'", $source);
        self::assertStringContainsString('clearRecoverySchedule', $source);
        $recovery = file_get_contents(dirname(__DIR__, 2) . '/src/WordPress/WorkerRecovery.php');
        self::assertIsString($recovery);
        self::assertStringContainsString('recoveryBatch', $recovery);
        self::assertStringNotContainsString('repairStaleJobs( true )', $recovery);
    }


    public function testStorageFailureEntersDegradedModeInsteadOfThrowingFromBootstrap(): void
    {
        $bootstrap = $this->read('src/Bootstrap.php');
        $degraded = $this->read('src/WordPress/DegradedModeIntegration.php');
        $plugin = $this->read('edis-evidence-exporter.php');

        self::assertStringContainsString('DegradedModeIntegration', $bootstrap);
        self::assertStringContainsString("'EDIS_PRIVATE_STORAGE_UNAVAILABLE'", $bootstrap);
        self::assertStringNotContainsString('EDIS requires protected storage outside the public WordPress web root', $bootstrap);
        self::assertStringContainsString('Exports are disabled, but WordPress remains available', $degraded);
        self::assertStringContainsString('catch ( \Throwable )', $plugin);
    }

    public function testAtomicCommitsSynchronizeParentDirectoriesOnPosix(): void
    {
        $root = dirname(__DIR__, 2);
        $filesystem = (string) file_get_contents($root . '/src/Infrastructure/Support/DeterministicFilesystem.php');
        $snapshots = (string) file_get_contents($root . '/src/Infrastructure/Support/InputSnapshotStore.php');
        self::assertStringContainsString('function synchronizeDirectory', $filesystem);
        self::assertStringContainsString('$this->synchronizeDirectory($directory);', $filesystem);
        self::assertStringContainsString('$this->filesystem->synchronizeDirectory(dirname($finalDirectory));', $snapshots);
        self::assertStringContainsString("DIRECTORY_SEPARATOR === '\\\\'", $filesystem);
    }

}
