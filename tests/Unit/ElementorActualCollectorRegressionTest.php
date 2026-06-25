<?php
declare(strict_types=1);

namespace {
    if (!function_exists('get_option')) {
        function get_option(string $name, mixed $default = false): mixed
        {
            return $name === 'elementor_active_kit' ? 77 : $default;
        }
    }

    if (!function_exists('get_post_meta')) {
        function get_post_meta(int $postId, string $key, bool $single = false): mixed
        {
            if ($postId === 77 && $key === '_elementor_page_settings' && $single) {
                return $GLOBALS['edis_test_kit_settings'] ?? [];
            }
            return $single ? '' : [];
        }
    }
}

namespace EDIS\EvidenceExporter\Tests\Unit {
    use EDIS\EvidenceExporter\Application\ExportService;
    use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
    use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
    use EDIS\EvidenceExporter\Infrastructure\Elementor\Collectors\KitSettingsCollector;
    use EDIS\EvidenceExporter\Infrastructure\Elementor\Indexes\SiteSettingsIndexBuilder;
    use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
    use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
    use EDIS\EvidenceExporter\Infrastructure\Support\JsonSchemaValidator;
    use PHPUnit\Framework\TestCase;

    final class ElementorActualCollectorRegressionTest extends TestCase
    {
        private ?string $artifactRoot = null;

        protected function tearDown(): void
        {
            unset($GLOBALS['edis_test_kit_settings']);
            if ($this->artifactRoot !== null) {
                $this->remove($this->artifactRoot);
            }
        }

        public function testActualCollectorsPreservePayloadTypesThroughFinalEnvelope(): void
        {
            $GLOBALS['edis_test_kit_settings'] = [
                'system_colors' => [],
                'container_width' => ['size' => 1140, 'unit' => 'px'],
            ];
            $root = dirname(__DIR__, 2) . '/';
            $context = new CollectionContext(
                [42],
                false,
                'analysis-actual-regression',
                'bundle-actual-regression',
                'Standard',
                ['export_scope' => 'SINGLE_DOCUMENT', 'dependency_scope' => 'REQUIRED_DEPENDENCIES'],
                '2026-01-01T00:00:00Z'
            );

            $kitResult = (new KitSettingsCollector())->collect($context);
            $kitArtifact = $kitResult->jsonSerialize();
            self::assertSame([], $kitArtifact['source_references']);
            self::assertTrue(is_object($kitArtifact['data']['settings']));

            $this->artifactRoot = sys_get_temp_dir() . '/edis-elementor-payload-' . bin2hex(random_bytes(6));
            $store = new ArtifactStore($this->artifactRoot);
            $store->put('job-regression', 'elementor_kit_settings', $kitArtifact);
            $rehydratedKit = $store->get('job-regression', 'elementor_kit_settings');
            self::assertIsArray($rehydratedKit);

            $siteResult = (new SiteSettingsIndexBuilder())->collect(
                $context,
                ['elementor_kit_settings' => $rehydratedKit]
            );
            $siteArtifact = $siteResult->jsonSerialize();
            self::assertTrue(is_object($siteArtifact['data']['groups']));
            self::assertSame('active_kit_settings', $siteArtifact['data']['source']);
            $store->put('job-regression', 'elementor_site_settings_index', $siteArtifact);
            $rehydratedSite = $store->get('job-regression', 'elementor_site_settings_index');
            self::assertIsArray($rehydratedSite);

            $registry = CollectorRegistry::fromDefinitions(require $root . 'config/collectors.php');
            $service = new ExportService($registry, $root);
            $envelopeMethod = (new \ReflectionClass($service))->getMethod('envelope');
            $envelopeMethod->setAccessible(true);
            $schemaIndex = json_decode((string) file_get_contents($root . 'schemas/schema-index.json'), true, 512, JSON_THROW_ON_ERROR);
            $validator = new JsonSchemaValidator($root);

            foreach ([$rehydratedKit, $rehydratedSite] as $artifact) {
                $componentId = $artifact['component_id'];
                $definition = $registry->definition($componentId);
                $envelope = $envelopeMethod->invoke(
                    $service,
                    $definition->schemaId,
                    $definition->schemaVersion,
                    $componentId,
                    $artifact,
                    $context
                );
                $decoded = json_decode(CanonicalJson::encode($envelope), false, 512, JSON_THROW_ON_ERROR);
                $route = $schemaIndex['entries'][$definition->schemaId . '@' . $definition->schemaVersion];
                self::assertSame([], $validator->validate($decoded->data, $route['payload_schema']));
            }
        }

        private function remove(string $path): void
        {
            if (is_file($path) || is_link($path)) {
                unlink($path);
                return;
            }
            if (!is_dir($path)) {
                return;
            }
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $this->remove($path . '/' . $entry);
            }
            rmdir($path);
        }
    }
}
