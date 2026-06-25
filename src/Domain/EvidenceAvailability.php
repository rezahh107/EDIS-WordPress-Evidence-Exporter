<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Domain;

enum EvidenceAvailability: string
{
    case AVAILABLE = 'AVAILABLE';
    case PARTIAL = 'PARTIAL';
    case INSUFFICIENT = 'INSUFFICIENT';
    case DISABLED = 'DISABLED';
    case UNAVAILABLE = 'UNAVAILABLE';
    case NOT_APPLICABLE = 'NOT_APPLICABLE';
    case ERROR = 'ERROR';
}
