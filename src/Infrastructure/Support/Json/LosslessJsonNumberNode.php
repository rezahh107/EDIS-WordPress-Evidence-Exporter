<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support\Json;

final class LosslessJsonNumberNode implements LosslessJsonNode
{
    private const MAX_EXPANSION = 100000;

    public function __construct(private readonly string $lexeme)
    {
        if (preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?$/D', $lexeme) !== 1) {
            throw new \InvalidArgumentException('Invalid JSON number lexeme.');
        }
    }

    public function kind(): string { return 'number'; }
    public function canonicalJson(): string { return self::normalize($this->lexeme); }
    public function toNative(): mixed { return $this->canonicalJson(); }
    public function toProcessingValue(): mixed
    {
        $canonical = $this->canonicalJson();
        if (!str_contains($canonical, '.') && strlen(ltrim($canonical, '-')) <= 16) {
            $integer = filter_var($canonical, FILTER_VALIDATE_INT);
            if (is_int($integer) && abs($integer) <= 9007199254740991) { return $integer; }
        }
        // PHP has no built-in decimal type. Keep non-integer JSON numbers as exact
        // number nodes so downstream canonical serialization never round-trips through float.
        return $this;
    }
    public function jsonSerialize(): mixed { return $this->canonicalJson(); }
    public function lexeme(): string { return $this->lexeme; }

    private static function normalize(string $number): string
    {
        $negative = str_starts_with($number, '-');
        if ($negative) { $number = substr($number, 1); }
        $exponent = 0;
        $ePosition = strpbrk($number, 'eE');
        if ($ePosition !== false) {
            $position = strcspn($number, 'eE');
            $exponentText = substr($number, $position + 1);
            $number = substr($number, 0, $position);
            $unsignedExponent = ltrim($exponentText, '+-');
            if (strlen($unsignedExponent) > 6 || (int) $unsignedExponent > self::MAX_EXPANSION) {
                throw new \LengthException('JSON number exponent exceeds the deterministic normalization budget.');
            }
            $exponent = (int) $exponentText;
        }
        [$integer, $fraction] = array_pad(explode('.', $number, 2), 2, '');
        $digits = $integer . $fraction;
        $decimalPosition = strlen($integer) + $exponent;
        if (strlen($digits) + abs($exponent) > self::MAX_EXPANSION) {
            throw new \LengthException('JSON number exceeds the deterministic normalization budget.');
        }
        if ($decimalPosition <= 0) {
            $plain = '0.' . str_repeat('0', -$decimalPosition) . $digits;
        } elseif ($decimalPosition >= strlen($digits)) {
            $plain = $digits . str_repeat('0', $decimalPosition - strlen($digits));
        } else {
            $plain = substr($digits, 0, $decimalPosition) . '.' . substr($digits, $decimalPosition);
        }
        if (str_contains($plain, '.')) {
            [$whole, $decimal] = explode('.', $plain, 2);
            $whole = ltrim($whole, '0');
            if ($whole === '') { $whole = '0'; }
            $decimal = rtrim($decimal, '0');
            $plain = $decimal === '' ? $whole : $whole . '.' . $decimal;
        } else {
            $plain = ltrim($plain, '0');
            if ($plain === '') { $plain = '0'; }
        }
        return $negative && $plain !== '0' ? '-' . $plain : $plain;
    }
}
