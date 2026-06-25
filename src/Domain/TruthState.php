<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Domain;

enum TruthState: string
{
    case VERIFIED = 'VERIFIED';
    case PARTIAL = 'PARTIAL';
    case UNKNOWN = 'UNKNOWN';
    case UNSUPPORTED = 'UNSUPPORTED';
}
