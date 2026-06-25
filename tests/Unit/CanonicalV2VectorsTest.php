<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\DocumentIdentity;
use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonParseException;
use PHPUnit\Framework\TestCase;

final class CanonicalV2VectorsTest extends TestCase
{
    public function testSharedValidVectors(): void
    {
        $path = dirname(__DIR__, 2) . '/contracts/edis-cj-2-vectors.json';
        $decoded = json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
        foreach ($decoded->cases as $case) {
            $canonical = DocumentIdentity::decodeLossless($case->raw_json)->canonicalJson();
            self::assertSame($case->canonical, $canonical, $case->id);
            self::assertSame($case->sha256, 'sha256:' . hash('sha256', $canonical), $case->id);
        }
    }

    public function testSharedInvalidVectors(): void
    {
        $path = dirname(__DIR__, 2) . '/contracts/edis-cj-2-vectors.json';
        $decoded = json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
        foreach ($decoded->invalid_cases as $case) {
            $actual = null;
            try {
                DocumentIdentity::decodeLossless($case->raw_json)->canonicalJson();
            } catch (LosslessJsonParseException $exception) {
                $actual = $exception->diagnosticCode;
            } catch (\LengthException) {
                $actual = 'EDIS_JSON_NUMBER_LIMIT_EXCEEDED';
            }
            self::assertSame($case->diagnostic_code, $actual, $case->id);
        }
    }
}
