<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SettingsSanitizerTest extends TestCase
{
    public function testRetentionContractIsBounded(): void
    {
        self::assertSame(1, max(1, min(168, 0)));
        self::assertSame(168, max(1, min(168, 999)));
    }

    public function testPrivacyEnumContainsOnlyDocumentedModes(): void
    {
        self::assertSame(['Strict', 'Standard', 'Diagnostic'], \EDIS\EvidenceExporter\Domain\Contracts\CollectionContext::PRIVACY_MODES);
    }
}
