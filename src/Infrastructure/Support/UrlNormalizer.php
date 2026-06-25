<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support;

final class UrlNormalizer
{
    private const SENSITIVE_MARKERS = ['token', 'nonce', 'password', 'passwd', 'secret', 'auth', 'session', 'bearer'];

    /** @return array{scheme:string,host_ascii:string,port:?int,path:string,site_path_scope:string} */
    public static function normalize(string $url, string $sitePathScope = '/'): array
    {
        self::rejectInvalidPercentEscapes($url);
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) { throw new \InvalidArgumentException('EDIS-URL-1 rejects invalid URLs and credentials.'); }
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) { throw new \InvalidArgumentException('EDIS-URL-1 supports HTTP and HTTPS only.'); }
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host === '') { throw new \InvalidArgumentException('EDIS-URL-1 requires a host.'); }
        if (preg_match('/[^\x00-\x7F]/', $host) === 1) {
            if (!function_exists('idn_to_ascii')) { throw new \RuntimeException('EDIS-URL-1 IDN normalization is unavailable because PHP Intl is not installed.'); }
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (!is_string($ascii) || $ascii === '' || preg_match('/[^\x00-\x7F]/', $ascii) === 1) { throw new \InvalidArgumentException('EDIS-URL-1 could not serialize the host to ASCII.'); }
            $host = strtolower($ascii);
        }
        if (preg_match('/\A(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[0-9a-f:.]+\])\z/iD', $host) !== 1) { throw new \InvalidArgumentException('EDIS-URL-1 host is invalid.'); }
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) { throw new \InvalidArgumentException('EDIS-URL-1 port is invalid.'); }
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) { $port = null; }
        $path = self::safePath(self::normalizePercentEncoding(self::removeDotSegments((string)($parts['path'] ?? '/'))));
        $scope = self::safePath(self::normalizePercentEncoding(self::removeDotSegments($sitePathScope)));
        return ['host_ascii' => $host, 'path' => $path, 'port' => $port, 'scheme' => $scheme, 'site_path_scope' => $scope];
    }

    public static function hash(string $url, string $sitePathScope = '/'): string { return 'sha256:' . hash('sha256', CanonicalJson::encode(self::normalize($url, $sitePathScope))); }

    private static function rejectInvalidPercentEscapes(string $value): void
    {
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            if ($value[$i] !== '%') { continue; }
            if ($i + 2 >= $length || !ctype_xdigit(substr($value, $i + 1, 2))) { throw new \InvalidArgumentException('EDIS-URL-1 rejects invalid percent-encoding.'); }
            $i += 2;
        }
    }

    private static function removeDotSegments(string $path): string
    {
        if ($path === '') { return '/'; }
        $absolute = str_starts_with($path, '/'); $trailing = str_ends_with($path, '/'); $output = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '.') { continue; }
            if ($segment === '..') { while ($output !== [] && end($output) === '') { array_pop($output); } if ($output !== []) { array_pop($output); } continue; }
            $output[] = $segment;
        }
        $result = implode('/', $output);
        if ($absolute && !str_starts_with($result, '/')) { $result = '/' . $result; }
        if ($trailing && $result !== '/' && !str_ends_with($result, '/')) { $result .= '/'; }
        return $result === '' ? '/' : $result;
    }

    private static function normalizePercentEncoding(string $path): string
    {
        $result = ''; $length = strlen($path);
        for ($index = 0; $index < $length; $index++) {
            $character = $path[$index];
            if ($character === '%') {
                $pair = substr($path, $index + 1, 2); $decoded = chr(hexdec($pair));
                if (ctype_alnum($decoded) || str_contains('-._~', $decoded)) { $result .= $decoded; } else { $result .= '%' . strtoupper($pair); }
                $index += 2; continue;
            }
            $result .= $character;
        }
        return $result;
    }

    private static function safePath(string $path): string
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $decoded = rawurldecode($segment); $lower = strtolower($decoded); $sensitive = strlen($decoded) > 128 || str_contains($decoded, '@');
            foreach (self::SENSITIVE_MARKERS as $marker) { if (str_contains($lower, $marker)) { $sensitive = true; break; } }
            $segments[] = $sensitive ? '{redacted}' : $segment;
        }
        return implode('/', $segments);
    }
}
