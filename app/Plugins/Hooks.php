<?php

namespace App\Plugins;

use Closure;

/**
 * Erweiterungspunkte für Plugins, ähnlich den Actions und Filtern von WordPress.
 *
 *   $hooks->addAction('admin.booted', fn () => ...);
 *   $hooks->addFilter('dashboard.hints', fn (array $hints) => [...$hints, $neuerHinweis]);
 *
 * Plugins sollten bevorzugt die bequemen Helfer addMenuItem(), addDashboardWidget()
 * und addHint() nutzen.
 */
class Hooks
{
    /** @var array<string, array<int, list<Closure>>> */
    private array $actions = [];

    /** @var array<string, array<int, list<Closure>>> */
    private array $filters = [];

    public function addAction(string $name, Closure $callback, int $priority = 10): void
    {
        $this->actions[$name][$priority][] = $callback;
    }

    public function doAction(string $name, mixed ...$args): void
    {
        foreach ($this->sorted($this->actions[$name] ?? []) as $callback) {
            $callback(...$args);
        }
    }

    public function addFilter(string $name, Closure $callback, int $priority = 10): void
    {
        $this->filters[$name][$priority][] = $callback;
    }

    public function applyFilters(string $name, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->sorted($this->filters[$name] ?? []) as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }

    /**
     * Fügt einen Menüpunkt in die linke Navigation des Admin-Bereichs ein.
     *
     * @param  string  $icon  Name eines Symbols aus resources/views/components/icon.blade.php
     */
    public function addMenuItem(string $label, string $url, string $icon = 'puzzle', int $order = 50, ?string $activePattern = null): void
    {
        $this->addFilter('admin.menu', fn (array $items) => [...$items, [
            'label' => $label, 'url' => $url, 'icon' => $icon, 'order' => $order, 'active' => $activePattern,
        ]]);
    }

    /**
     * Fügt eine Kachel auf dem Dashboard hinzu. $render liefert HTML (z. B. view(...)->render()).
     */
    public function addDashboardWidget(string $title, Closure $render, int $order = 50): void
    {
        $this->addFilter('dashboard.widgets', fn (array $widgets) => [...$widgets, [
            'title' => $title, 'render' => $render, 'order' => $order,
        ]]);
    }

    /**
     * Meldet einen Hinweis "Hier fehlt noch etwas" an das Dashboard.
     * $check liefert true, wenn alles erledigt ist.
     */
    public function addHint(string $title, string $help, Closure $check, ?string $url = null, string $area = 'Inhalt'): void
    {
        $this->addFilter('dashboard.hints', fn (array $hints) => [...$hints, [
            'title' => $title, 'help' => $help, 'check' => $check, 'url' => $url, 'area' => $area,
        ]]);
    }

    /** @param array<int, list<Closure>> $byPriority  @return list<Closure> */
    private function sorted(array $byPriority): array
    {
        ksort($byPriority);

        return array_merge(...array_values($byPriority) ?: [[]]);
    }
}
