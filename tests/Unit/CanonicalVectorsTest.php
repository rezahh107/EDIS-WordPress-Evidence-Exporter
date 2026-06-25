<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use PHPUnit\Framework\TestCase;

final class CanonicalVectorsTest extends TestCase
{
    public function testSharedVectors(): void
    {
        $path=dirname(__DIR__,2).'/contracts/edis-cj-1-vectors.json';
        $decoded=json_decode((string)file_get_contents($path),false,512,JSON_THROW_ON_ERROR);
        foreach($decoded->cases as $case){
            $canonical=CanonicalJson::encode($case->input);
            self::assertSame($case->canonical,$canonical,$case->id);
            self::assertSame($case->sha256,'sha256:'.hash('sha256',$canonical),$case->id);
        }
    }

    public function testRejectsNonFiniteNumbers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CanonicalJson::encode(['value'=>INF]);
    }
}
