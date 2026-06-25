<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\ArtifactStore;
use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonNode;
use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonParser;
use PHPUnit\Framework\TestCase;

final class ArtifactStoreLosslessRoundTripTest extends TestCase
{
    public function testOriginalSavedDocumentAndExactNumbersSurviveStoreRoundTrip(): void
    {
        $root = sys_get_temp_dir() . '/edis-artifact-lossless-' . bin2hex(random_bytes(6));
        try {
            $document = (new LosslessJsonParser())->parse('[{"n":1.2300,"numeric":{"0":"a"},"settings":{},"styles":[]}]');
            $store = new ArtifactStore($root);
            $store->put('job', 'component', [
                'component_id' => 'component',
                'data' => [
                    'original_saved_document' => $document,
                    'exact_decimal' => (new LosslessJsonParser())->parse('1.2300'),
                ],
            ]);
            $artifact = $store->get('job', 'component');
            self::assertIsArray($artifact);
            $original = $artifact['data']['original_saved_document'] ?? null;
            self::assertInstanceOf(LosslessJsonNode::class, $original);
            self::assertSame('[{"n":1.23,"numeric":{"0":"a"},"settings":{},"styles":[]}]', CanonicalJson::encode($original));
            self::assertSame('1.23', CanonicalJson::encode($artifact['data']['exact_decimal'] ?? null));
        } finally {
            $this->remove($root);
        }
    }

    private function remove(string $path): void
    {
        if (is_file($path) && !is_link($path)) { unlink($path); return; }
        if (!is_dir($path) || is_link($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') { continue; }
            $this->remove($path . '/' . $entry);
        }
        rmdir($path);
    }
}
