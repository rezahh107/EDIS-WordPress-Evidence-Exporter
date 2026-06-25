<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin\View;

final class ViewRenderer
{
    /** @param array<string, string> $templates */
    public function __construct(
        private readonly string $pluginRoot,
        private readonly array $templates,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(string $viewId, array $data = []): void
    {
        $relative = $this->templates[$viewId] ?? null;
        if (!is_string($relative) || $relative === '') {
            throw new \OutOfBoundsException('Unknown admin view: ' . $viewId);
        }
        $base = realpath($this->pluginRoot . 'templates/admin');
        $path = realpath($this->pluginRoot . $relative);
        if ($base === false || $path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Unsafe or missing admin template.');
        }
        extract($data, EXTR_SKIP);
        require $path;
    }
}
