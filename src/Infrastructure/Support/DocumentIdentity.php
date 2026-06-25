<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonArrayNode;
use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonNode;
use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonParseException;
use EDIS\EvidenceExporter\Infrastructure\Support\Json\LosslessJsonParser;

final class DocumentIdentity
{
    /** @return array{raw_storage_bytes_sha256:string,canonical_saved_source_sha256:?string,saved_source_sha256:?string,json_validation_error_code:?string,canonicalization_profile:string} */
    public static function sourceHashes(string $raw): array
    {
        return self::inspectSource($raw)['hashes'];
    }

    /** @return array{hashes:array{raw_storage_bytes_sha256:string,canonical_saved_source_sha256:?string,saved_source_sha256:?string,json_validation_error_code:?string,canonicalization_profile:string},processing_value:?array} */
    public static function inspectSource(string $raw): array
    {
        $canonical = null;
        $errorCode = null;
        $processingValue = null;
        try {
            $node = self::decodeLossless($raw);
            $canonical = 'sha256:' . hash('sha256', $node->canonicalJson());
            if ($node instanceof LosslessJsonArrayNode) {
                $value = $node->toProcessingValue();
                if (is_array($value)) {
                    $processingValue = $value;
                }
            }
        } catch (LosslessJsonParseException $exception) {
            $errorCode = $exception->diagnosticCode;
        } catch (\LengthException) {
            $errorCode = 'EDIS_JSON_NUMBER_LIMIT_EXCEEDED';
        } catch (\Throwable) {
            $errorCode = 'EDIS_JSON_CANONICALIZATION_FAILED';
        }

        return [
            'hashes' => [
                'raw_storage_bytes_sha256' => 'sha256:' . hash('sha256', $raw),
                'canonical_saved_source_sha256' => $canonical,
                'saved_source_sha256' => $canonical,
                'json_validation_error_code' => $errorCode,
                'canonicalization_profile' => CanonicalJson::PROFILE,
            ],
            'processing_value' => $processingValue,
        ];
    }

    public static function decodeLossless(string $raw): LosslessJsonNode
    {
        return (new LosslessJsonParser())->parse($raw);
    }

    public static function fingerprint(string $documentId, string $documentType, string $sourceStorageKind): string
    {
        $identity = [
            'site_scope' => function_exists('get_current_blog_id') ? (string) get_current_blog_id() : '1',
            'document_id' => $documentId,
            'document_type' => $documentType,
            'source_storage_kind' => $sourceStorageKind,
        ];
        return 'sha256:' . hash('sha256', CanonicalJson::encode($identity));
    }

    /** @return list<mixed>|null */
    public static function decodeAssociative(string $raw): ?array
    {
        try {
            $decoded = self::decodeLossless($raw);
        } catch (\Throwable) {
            return null;
        }
        if (!$decoded instanceof LosslessJsonArrayNode) {
            return null;
        }
        $value = $decoded->toProcessingValue();
        return is_array($value) && array_is_list($value) ? $value : null;
    }
}
