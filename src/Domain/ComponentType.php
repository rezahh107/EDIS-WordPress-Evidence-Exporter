<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Domain;

enum ComponentType: string
{
    case SOURCE_COLLECTOR = 'SOURCE_COLLECTOR';
    case INDEX_BUILDER = 'INDEX_BUILDER';
    case BUNDLE_PROCESSOR = 'BUNDLE_PROCESSOR';
}
