<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Rest;

final class CanonicalDiagnosticResponse extends \WP_REST_Response
{
    public function __construct(private readonly string $canonicalBytes)
    {
        parent::__construct(null, 200);
        $this->header('Content-Type', 'application/vnd.edis.diagnostic+json; charset=UTF-8');
        $this->header('Cache-Control', 'private, no-store, max-age=0');
        $this->header('X-Content-Type-Options', 'nosniff');
        $this->header('Content-Length', (string) strlen($canonicalBytes));
    }

    public function canonicalBytes(): string
    {
        return $this->canonicalBytes;
    }
}
