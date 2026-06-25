<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\UrlNormalizer;
use PHPUnit\Framework\TestCase;

final class UrlNormalizerVectorsTest extends TestCase
{
    public function testSharedVectors(): void
    {
        $path=dirname(__DIR__,2).'/contracts/edis-url-1-vectors.json';
        $decoded=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        foreach($decoded['cases'] as $case){
            self::assertSame($case['normalized'],UrlNormalizer::normalize($case['url'],$case['site_path_scope']),$case['id']);
            self::assertSame($case['page_locator_sha256'],UrlNormalizer::hash($case['url'],$case['site_path_scope']),$case['id']);
        }
    }
}
