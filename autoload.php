<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'EDIS\\EvidenceExporter\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    if ($relative === false || $relative === '') {
        return;
    }
    $parts = explode('\\', $relative);
    foreach ($parts as $part) {
        if ($part === '' || $part === '.' || $part === '..' || str_contains($part, '/')) {
            return;
        }
    }
    $sharedApplicationContracts = [
        'Application\\JobFailureCursor',
        'Application\\FailureObservation',
        'Application\\FailureObservationException',
        'Application\\ExpectedOperationRejection',
        'Application\\DurableJobFailureException',
        'Application\\ExportCreateConformance',
    ];
    $path = in_array($relative, $sharedApplicationContracts, true)
        ? __DIR__ . '/src/Application/JobFailureCursor.php'
        : __DIR__ . '/src/' . implode('/', $parts) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
