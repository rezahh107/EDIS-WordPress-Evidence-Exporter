<?php
declare(strict_types=1);

namespace EDIS\EvidenceExporter\Admin;

use EDIS\EvidenceExporter\Admin\Page\PageInterface;

final class AdminMenu
{
    /** @var array<string, PageInterface> */
    private array $pages = [];
    /** @var array<string, string> */
    private array $hooks = [];

    /** @param array<string, mixed> $config @param list<PageInterface> $pages */
    public function __construct(private readonly array $config, array $pages)
    {
        foreach ($pages as $page) {
            $this->pages[$page->id()] = $page;
        }
    }

    public function register(): void
    {
        $rootPage = $this->page('overview');
        $rootHook = add_menu_page(
            __('EDIS Evidence', 'edis-evidence-exporter'),
            __('EDIS Evidence', 'edis-evidence-exporter'),
            (string) $this->config['capability'],
            (string) $this->config['menu_slug'],
            [$rootPage, 'render'],
            (string) $this->config['icon'],
            (int) $this->config['position'],
        );
        $this->hooks[$rootHook] = 'overview';

        foreach ($this->config['pages'] as $pageConfig) {
            if (!is_array($pageConfig)) {
                continue;
            }
            $page = $this->page((string) $pageConfig['id']);
            $hook = add_submenu_page(
                (string) $this->config['menu_slug'],
                $this->translatedPageLabel((string) $pageConfig['id']),
                $this->translatedPageLabel((string) $pageConfig['id']),
                (string) $this->config['capability'],
                (string) $pageConfig['slug'],
                [$page, 'render'],
            );
            if (is_string($hook) && $hook !== '') {
                $this->hooks[$hook] = $page->id();
                add_action('load-' . $hook, fn (): bool => $this->addContextualHelp($page->id()));
            }
        }
    }

    public function isEdisHook(string $hook): bool
    {
        return isset($this->hooks[$hook]);
    }

    public function pageIdForHook(string $hook): string
    {
        return $this->hooks[$hook] ?? '';
    }

    private function page(string $id): PageInterface
    {
        $page = $this->pages[$id] ?? null;
        if (!$page instanceof PageInterface) {
            throw new \LogicException('Missing admin page implementation: ' . $id);
        }
        return $page;
    }

    private function translatedPageLabel(string $id): string
    {
        return match ($id) {
            'overview' => __('Overview', 'edis-evidence-exporter'),
            'create-export' => __('Create Export', 'edis-evidence-exporter'),
            'data-sources' => __('Data Sources', 'edis-evidence-exporter'),
            'data-coverage' => __('Data Coverage', 'edis-evidence-exporter'),
            'diagnostics' => __('Diagnostics', 'edis-evidence-exporter'),
            'settings' => __('Settings', 'edis-evidence-exporter'),
            'help' => __('Help', 'edis-evidence-exporter'),
            default => $id,
        };
    }

    private function addContextualHelp(string $pageId): bool
    {
        $screen = get_current_screen();
        if ($screen === null) {
            return false;
        }
        $screen->add_help_tab([
            'id' => 'edis-evidence-' . $pageId,
            'title' => esc_html__('EDIS Help', 'edis-evidence-exporter'),
            'content' => '<p>' . esc_html__('EDIS exports saved technical evidence and reports explicit truth states. Open the Help page for the complete workflow and privacy guidance.', 'edis-evidence-exporter') . '</p>',
        ]);
        return true;
    }
}
