<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use EDIS\EvidenceExporter\Infrastructure\Support\CanonicalJson;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicFilesystem;
use EDIS\EvidenceExporter\Infrastructure\Support\DeterministicZipWriter;
use EDIS\EvidenceExporter\Infrastructure\Support\DocumentIdentity;

if (!CanonicalJson::environmentReady()) {
    throw new RuntimeException('EDIS-CJ-2 runtime preflight failed.');
}

$node = DocumentIdentity::decodeLossless('{"1":"one","0":"zero","array":[],"object":{},"number":1.2300e2}');
$expected = '{"0":"zero","1":"one","array":[],"number":123,"object":{}}';
if ($node->canonicalJson() !== $expected) {
    throw new RuntimeException('Lossless canonical JSON smoke test failed.');
}

$writer = new DeterministicZipWriter();
$first = $writer->build(['b.txt' => 'b', 'a.txt' => 'a']);
$second = $writer->build(['a.txt' => 'a', 'b.txt' => 'b']);
if (!hash_equals(hash('sha256', $first), hash('sha256', $second))) {
    throw new RuntimeException('Deterministic ZIP smoke test failed.');
}

$filesystem = new DeterministicFilesystem();
$test = $filesystem->selfTest(sys_get_temp_dir() . '/edis-runtime-smoke');
if (!$test['durable_write'] || !$test['atomic_rename'] || !$test['atomic_replace'] || !$test['lock_exclusion'] || $test['multiprocess_lock_exclusion'] !== 'PASS' || !$test['cleanup']) {
    throw new RuntimeException('Deterministic filesystem smoke test failed.');
}

echo "EDIS runtime smoke: PASS\n";
