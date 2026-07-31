<?php
declare(strict_types=1);

namespace Elementor;

if (!class_exists(Plugin::class, false)) {
    final class Plugin
    {
        public static mixed $instance = null;
    }
}

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Application\ExportService;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Infrastructure\Collector\CollectorRegistry;
use EDIS\EvidenceExporter\Infrastructure\Elementor\Collectors\BreakpointsCollector;
use EDIS\EvidenceExporter\Infrastructure\Elementor\Collectors\KitSettingsCollector;
use EDIS\EvidenceExporter\Infrastructure\Elementor\Collectors\RegisteredDocumentTypesCollector;
use EDIS\EvidenceExporter\Infrastructure\Elementor\Collectors\RegisteredWidgetsCollector;
use EDIS\EvidenceExporter\Infrastructure\Support\PrivacyProjection;
use PHPUnit\Framework\TestCase;

final class CorrectnessClosure314Test extends TestCase
{
    protected function tearDown(): void
    {
        \Elementor\Plugin::$instance = null;
    }

    /** T01 + T02 + T03 */
    public function testPrivacyProjectionIsRecursiveModeIndependentForCredentialsAndStrictForContent(): void
    {
        $projection = new PrivacyProjection();
        $source = [
            'api_token' => 'api-secret',
            'nested' => [
                'nonce' => 'nonce-secret',
                'layout' => [
                    'direction' => 'row',
                    'color_token' => 'global-color-primary',
                ],
                'content' => 'private saved copy',
                'form' => [
                    'field' => [
                        'default_value' => 'private@example.test',
                        'placeholder' => 'Email address',
                        'type' => 'email',
                    ],
                ],
            ],
        ];

        $standard = $projection->project($source, 'Standard');
        self::assertIsArray($standard);
        self::assertArrayNotHasKey('api_token', $standard);
        self::assertArrayNotHasKey('nonce', $standard['nested']);
        self::assertSame('private saved copy', $standard['nested']['content']);
        self::assertSame('global-color-primary', $standard['nested']['layout']['color_token']);

        $strict = $projection->projectWithSummary($source, 'Strict');
        self::assertIsArray($strict['value']);
        self::assertArrayNotHasKey('api_token', $strict['value']);
        self::assertArrayNotHasKey('nonce', $strict['value']['nested']);
        self::assertArrayNotHasKey('content', $strict['value']['nested']);
        self::assertArrayNotHasKey('default_value', $strict['value']['nested']['form']['field']);
        self::assertArrayNotHasKey('placeholder', $strict['value']['nested']['form']['field']);
        self::assertSame('email', $strict['value']['nested']['form']['field']['type']);
        self::assertSame('row', $strict['value']['nested']['layout']['direction']);
        self::assertSame('global-color-primary', $strict['value']['nested']['layout']['color_token']);
        self::assertGreaterThanOrEqual(5, $strict['summary']['suppressed_count']);
    }

    /** T05 */
    public function testRegisteredWidgetsExceptionCannotBecomeObservedEmpty(): void
    {
        \Elementor\Plugin::$instance = (object) [
            'widgets_manager' => new class {
                public function get_widget_types(): array
                {
                    throw new \RuntimeException('synthetic widget registry failure');
                }
            },
        ];

        $this->expectException(\RuntimeException::class);
        (new RegisteredWidgetsCollector())->collect($this->context());
    }

    /** T06 */
    public function testBreakpointsExceptionCannotBecomeObservedEmpty(): void
    {
        \Elementor\Plugin::$instance = (object) [
            'breakpoints' => new class {
                public function get_active_breakpoints(): array
                {
                    throw new \RuntimeException('synthetic breakpoint failure');
                }
            },
        ];

        $this->expectException(\RuntimeException::class);
        (new BreakpointsCollector())->collect($this->context());
    }

    /** T07 */
    public function testDocumentTypesUseRealFallbackButAllFailuresRemainFailures(): void
    {
        \Elementor\Plugin::$instance = (object) [
            'documents' => new class {
                public function get_document_types(): array
                {
                    throw new \RuntimeException('primary failure');
                }

                public function get_document_type_classes(): array
                {
                    return ['page' => 'SyntheticPageDocument'];
                }
            },
        ];

        $fallback = (new RegisteredDocumentTypesCollector())->collect($this->context());
        self::assertSame(EvidenceAvailability::AVAILABLE, $fallback->availability);
        self::assertSame(1, $fallback->data['count']);
        self::assertSame('page', $fallback->data['document_types'][0]['type']);
        self::assertContains('EDIS_DOCUMENT_TYPE_FALLBACK_USED', array_map(
            static fn ($diagnostic): string => $diagnostic->code,
            $fallback->diagnostics,
        ));

        \Elementor\Plugin::$instance = (object) [
            'documents' => new class {
                public function get_document_types(): array
                {
                    throw new \RuntimeException('primary failure');
                }

                public function get_document_type_classes(): array
                {
                    throw new \RuntimeException('fallback failure');
                }
            },
        ];

        $this->expectException(\RuntimeException::class);
        (new RegisteredDocumentTypesCollector())->collect($this->context());
    }

    /** T08 */
    public function testActiveKitManagerExceptionCannotBecomeOrdinaryAbsence(): void
    {
        \Elementor\Plugin::$instance = (object) [
            'kits_manager' => new class {
                public function get_active_id(): int
                {
                    throw new \RuntimeException('synthetic active-kit failure');
                }
            },
        ];

        $this->expectException(\RuntimeException::class);
        (new KitSettingsCollector())->collect($this->context());
    }

    /** T10 + T11 + T12 */
    public function testEnvelopeUsesArtifactObservationTimeAndExplicitPackagingTime(): void
    {
        $exporter = new ExportService($this->registry(), $this->pluginRoot());
        $method = new \ReflectionMethod($exporter, 'envelope');
        $context = $this->context('2026-07-30T00:00:00Z');
        $artifact = [
            'component_id' => 'synthetic_component',
            'component_type' => 'SOURCE_COLLECTOR',
            'source_truth_state' => 'VERIFIED',
            'source_availability' => 'AVAILABLE',
            'data' => [],
            'diagnostics' => [],
            'source_references' => [],
            'provenance' => [],
            'observed_at' => '2026-07-30T01:02:03Z',
        ];

        $componentEnvelope = $method->invoke(
            $exporter,
            'urn:edis:test',
            '1.0.0',
            'synthetic_component',
            $artifact,
            $context,
        );
        self::assertSame('2026-07-30T01:02:03Z', $componentEnvelope['captured_at']);

        $syntheticEnvelope = $method->invoke(
            $exporter,
            'urn:edis:test',
            '1.0.0',
            'synthetic_package_artifact',
            $artifact,
            $context,
            '2026-07-30T04:05:06Z',
        );
        self::assertSame('2026-07-30T04:05:06Z', $syntheticEnvelope['captured_at']);
    }

    private function context(string $capturedAt = '2026-07-30T00:00:00Z'): CollectionContext
    {
        return new CollectionContext(
            [],
            false,
            'analysis-test',
            'bundle-test',
            'Strict',
            [
                'export_scope' => 'METADATA_ONLY',
                'dependency_scope' => 'SOURCE_ONLY',
            ],
            $capturedAt,
        );
    }

    private function registry(): CollectorRegistry
    {
        /** @var list<\EDIS\EvidenceExporter\Infrastructure\Collector\CollectorDefinition> $definitions */
        $definitions = require $this->pluginRoot() . 'config/collectors.php';
        return CollectorRegistry::fromDefinitions($definitions);
    }

    private function pluginRoot(): string
    {
        return dirname(__DIR__, 2) . '/';
    }
}
