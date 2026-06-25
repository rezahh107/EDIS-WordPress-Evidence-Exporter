<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Infrastructure\Support\Json;

final class LosslessJsonParser
{
    private int $offset = 0;
    private int $length = 0;

    public function __construct(private readonly int $maximumDepth = 512) {}

    public function parse(string $json): LosslessJsonNode
    {
        if (preg_match('//u', $json) !== 1) {
            throw new LosslessJsonParseException('EDIS_JSON_INVALID_UTF8', 0, 'JSON input is not valid UTF-8.');
        }
        $this->offset = 0;
        $this->length = strlen($json);
        $node = $this->parseValue($json, 0);
        $this->skipWhitespace($json);
        if ($this->offset !== $this->length) {
            throw $this->error('EDIS_JSON_TRAILING_CONTENT', 'Unexpected content after the JSON value.');
        }
        return $node;
    }

    private function parseValue(string $json, int $depth): LosslessJsonNode
    {
        if ($depth > $this->maximumDepth) {
            throw $this->error('EDIS_JSON_DEPTH_EXCEEDED', 'JSON nesting exceeds the configured maximum depth.');
        }
        $this->skipWhitespace($json);
        if ($this->offset >= $this->length) { throw $this->error('EDIS_JSON_UNEXPECTED_END', 'Unexpected end of JSON input.'); }
        return match ($json[$this->offset]) {
            '{' => $this->parseObject($json, $depth + 1),
            '[' => $this->parseArray($json, $depth + 1),
            '"' => new LosslessJsonScalarNode($this->parseString($json)),
            't' => $this->parseLiteral($json, 'true', new LosslessJsonScalarNode(true)),
            'f' => $this->parseLiteral($json, 'false', new LosslessJsonScalarNode(false)),
            'n' => $this->parseLiteral($json, 'null', new LosslessJsonScalarNode(null)),
            default => $this->parseNumber($json),
        };
    }

    private function parseObject(string $json, int $depth): LosslessJsonObjectNode
    {
        $this->offset++;
        $this->skipWhitespace($json);
        $members = [];
        $seen = [];
        if ($this->peek($json) === '}') { $this->offset++; return new LosslessJsonObjectNode([]); }
        while (true) {
            $this->skipWhitespace($json);
            if ($this->peek($json) !== '"') { throw $this->error('EDIS_JSON_OBJECT_KEY_REQUIRED', 'JSON object keys must be strings.'); }
            $keyOffset = $this->offset;
            $key = $this->parseString($json);
            $identity = strlen($key) . ':' . base64_encode($key);
            if (isset($seen[$identity])) {
                throw new LosslessJsonParseException('EDIS_DUPLICATE_JSON_OBJECT_KEY', $keyOffset, 'Duplicate JSON object key: ' . $key);
            }
            $seen[$identity] = true;
            $this->skipWhitespace($json);
            if ($this->peek($json) !== ':') { throw $this->error('EDIS_JSON_COLON_REQUIRED', 'A colon is required after a JSON object key.'); }
            $this->offset++;
            $members[] = [$key, $this->parseValue($json, $depth)];
            $this->skipWhitespace($json);
            $next = $this->peek($json);
            if ($next === '}') { $this->offset++; break; }
            if ($next !== ',') { throw $this->error('EDIS_JSON_OBJECT_SEPARATOR_REQUIRED', 'A comma or closing brace is required in a JSON object.'); }
            $this->offset++;
        }
        return new LosslessJsonObjectNode($members);
    }

    private function parseArray(string $json, int $depth): LosslessJsonArrayNode
    {
        $this->offset++;
        $this->skipWhitespace($json);
        $items = [];
        if ($this->peek($json) === ']') { $this->offset++; return new LosslessJsonArrayNode([]); }
        while (true) {
            $items[] = $this->parseValue($json, $depth);
            $this->skipWhitespace($json);
            $next = $this->peek($json);
            if ($next === ']') { $this->offset++; break; }
            if ($next !== ',') { throw $this->error('EDIS_JSON_ARRAY_SEPARATOR_REQUIRED', 'A comma or closing bracket is required in a JSON array.'); }
            $this->offset++;
        }
        return new LosslessJsonArrayNode($items);
    }

    private function parseString(string $json): string
    {
        $start = $this->offset;
        $this->offset++;
        while ($this->offset < $this->length) {
            $character = $json[$this->offset];
            if ($character === '"') {
                $this->offset++;
                $token = substr($json, $start, $this->offset - $start);
                try {
                    $decoded = json_decode($token, true, 1, JSON_THROW_ON_ERROR);
                } catch (\JsonException $exception) {
                    throw new LosslessJsonParseException('EDIS_JSON_STRING_INVALID', $start, 'Invalid JSON string.', $exception);
                }
                if (!is_string($decoded)) { throw new LosslessJsonParseException('EDIS_JSON_STRING_INVALID', $start, 'Invalid JSON string.'); }
                return $decoded;
            }
            if ($character === '\\') {
                $this->offset += 2;
                continue;
            }
            if (ord($character) < 0x20) { throw $this->error('EDIS_JSON_STRING_CONTROL_CHARACTER', 'Unescaped control character in JSON string.'); }
            $this->offset++;
        }
        throw new LosslessJsonParseException('EDIS_JSON_UNEXPECTED_END', $start, 'Unterminated JSON string.');
    }

    private function parseNumber(string $json): LosslessJsonNumberNode
    {
        $remaining = substr($json, $this->offset);
        if (preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/', $remaining, $matches) !== 1) {
            throw $this->error('EDIS_JSON_VALUE_INVALID', 'Invalid JSON value.');
        }
        $lexeme = $matches[0];
        $this->offset += strlen($lexeme);
        return new LosslessJsonNumberNode($lexeme);
    }

    private function parseLiteral(string $json, string $literal, LosslessJsonNode $node): LosslessJsonNode
    {
        if (substr($json, $this->offset, strlen($literal)) !== $literal) {
            throw $this->error('EDIS_JSON_VALUE_INVALID', 'Invalid JSON literal.');
        }
        $this->offset += strlen($literal);
        return $node;
    }

    private function skipWhitespace(string $json): void
    {
        while ($this->offset < $this->length && str_contains(" \t\r\n", $json[$this->offset])) { $this->offset++; }
    }

    private function peek(string $json): ?string
    {
        return $this->offset < $this->length ? $json[$this->offset] : null;
    }

    private function error(string $code, string $message): LosslessJsonParseException
    {
        return new LosslessJsonParseException($code, $this->offset, $message);
    }
}
