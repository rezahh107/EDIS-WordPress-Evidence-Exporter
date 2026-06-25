<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use PHPUnit\Framework\TestCase;

final class CanonicalJsonTest extends TestCase
{
    public function testObjectKeysAreSorted(): void
    {
        self::assertSame('{"a":1,"b":2}', CanonicalJson::encode(['b' => 2, 'a' => 1]));
    }
}
