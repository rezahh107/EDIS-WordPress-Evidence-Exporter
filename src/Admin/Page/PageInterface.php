<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin\Page;

interface PageInterface
{
    public function id(): string;

    public function render(): void;
}
