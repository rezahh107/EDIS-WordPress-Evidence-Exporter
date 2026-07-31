<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\WordPress\Collectors;

use EDIS\EvidenceExporter\Domain\CollectionResult;
use EDIS\EvidenceExporter\Domain\ComponentType;
use EDIS\EvidenceExporter\Domain\Contracts\CollectionContext;
use EDIS\EvidenceExporter\Domain\Contracts\EvidenceCollector;
use EDIS\EvidenceExporter\Domain\Diagnostic;
use EDIS\EvidenceExporter\Domain\EvidenceAvailability;
use EDIS\EvidenceExporter\Domain\TruthState;
use EDIS\EvidenceExporter\Infrastructure\Support\UrlNormalizer;

final class EnvironmentCollector implements EvidenceCollector
{
    public function id(): string { return 'environment'; }

    public function collect(CollectionContext $context, array $artifacts = []): CollectionResult
    {
        global $wp_version;
        $multisite = function_exists('is_multisite') && is_multisite();
        $siteUrl = function_exists('get_site_url') ? (string) get_site_url() : '';
        $homeUrl = function_exists('get_home_url') ? (string) get_home_url() : '';
        $sitePathScope = function_exists('wp_parse_url') ? (string) (wp_parse_url($homeUrl, PHP_URL_PATH) ?: '/') : '/';
        $locatorCandidates = [];
        $diagnostics = [];
        foreach ([['HOME_URL', $homeUrl], ['SITE_URL', $siteUrl]] as [$kind, $url]) {
            if ($url === '') { continue; }
            try {
                $locatorCandidates[] = [
                    'locator_kind' => $kind,
                    'page_locator_sha256' => UrlNormalizer::hash($url, $sitePathScope),
                ];
            } catch (\Throwable $exception) {
                $diagnostics[] = new Diagnostic(
                    'EDIS_URL_NORMALIZATION_FAILED',
                    'WARNING',
                    'SEMANTIC',
                    'diagnostic.environment.url_normalization_failed',
                    ['locator_kind' => $kind, 'exception_class' => get_class($exception)],
                );
            }
        }
        usort($locatorCandidates, static fn(array $a, array $b): int => strcmp((string) $a['locator_kind'], (string) $b['locator_kind']));
        $data = [
            'wordpress_version' => (string) $wp_version,
            'php_version' => PHP_VERSION,
            'multisite' => $multisite,
            'site_url_sha256' => $siteUrl !== '' ? 'sha256:' . hash('sha256', $siteUrl) : null,
            'home_url_sha256' => $homeUrl !== '' ? 'sha256:' . hash('sha256', $homeUrl) : null,
            'site_path_scope' => $sitePathScope,
            'site_locator_candidates' => $locatorCandidates,
            'url_normalization_profile' => 'EDIS-URL-1',
            'locale' => function_exists('get_locale') ? get_locale() : null,
            'timezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : null,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'memory_limit' => function_exists('wp_convert_hr_to_bytes') ? wp_convert_hr_to_bytes((string) ini_get('memory_limit')) : null,
        ];
        $partial = $diagnostics !== [];
        return new CollectionResult(
            $this->id(),
            $partial ? TruthState::PARTIAL : TruthState::VERIFIED,
            $partial ? EvidenceAvailability::PARTIAL : EvidenceAvailability::AVAILABLE,
            ComponentType::SOURCE_COLLECTOR,
            $data,
            $diagnostics,
            [],
            $this->provenance(),
        );
    }

    private function provenance(): array
    {
        return [
            'collector_id' => $this->id(),
            'adapter_id' => 'wordpress.core',
            'adapter_version' => '1.2.0',
            'source_kind' => 'WORDPRESS_RUNTIME',
            'retrieval_strategy' => 'public_wordpress_apis_and_edis_url_1',
        ];
    }
}
