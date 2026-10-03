<?php

namespace CharlesStOlive\FilamentMap\Support;

use BladeUI\Icons\Factory;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Les icônes qu'on peut poser dans un marqueur (IconPicker) : celles des jeux Blade Icons de l'application, rangées
 * en groupes — les variantes d'Heroicons à part (contour, plein, mini, micro : `filament-map.icons.groups`), un groupe
 * par autre jeu. Le nom d'une icône est celui qu'on écrit ailleurs (`heroicon-o-map-pin`).
 *
 * Les noms sont lus une fois sur le disque, puis gardés en cache ; le dessin d'une icône est son fichier SVG.
 */
final class IconCatalog
{
    /** @var array<string, array{name: string, group: string, path: string}>|null */
    private ?array $icons = null;

    /** @return array<string, string> Clé du groupe → libellé, dans l'ordre où on les propose. */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->icons() as $icon) {
            $groups[$icon['group']] ??= $this->groupLabel($icon['group']);
        }

        // Les groupes configurés d'abord, dans leur ordre ; les autres jeux ensuite, par nom.
        $order = array_flip(array_keys(config('filament-map.icons.groups', [])));
        uksort($groups, fn (string $a, string $b): int => [$order[$a] ?? PHP_INT_MAX, $a] <=> [$order[$b] ?? PHP_INT_MAX, $b]);

        return $groups;
    }

    /**
     * Les icônes dont le nom contient tous les mots cherchés, dans un groupe (ou tous), par groupe puis par nom.
     *
     * @return array{icons: array<int, array{name: string, label: string, group: string, svg: string}>, total: int}
     */
    public function search(?string $query = null, ?string $group = null, int $limit = 240): array
    {
        $words = array_filter(preg_split('/[\s\-_]+/', Str::lower(Str::ascii(trim((string) $query)))) ?: []);

        $found = array_values(array_filter($this->icons(), function (array $icon) use ($words, $group): bool {
            if (filled($group) && $icon['group'] !== $group) {
                return false;
            }

            foreach ($words as $word) {
                if (! str_contains($icon['name'], $word)) {
                    return false;
                }
            }

            return true;
        }));

        // Par groupe (dans l'ordre des groupes), puis par nom.
        $order = array_flip(array_keys($this->groups()));
        usort($found, fn (array $a, array $b): int => [$order[$a['group']] ?? PHP_INT_MAX, $a['name']] <=> [$order[$b['group']] ?? PHP_INT_MAX, $b['name']]);

        return [
            'icons' => array_map(fn (array $icon): array => [
                'name' => $icon['name'],
                'label' => $this->label($icon),
                'group' => $icon['group'],
                'svg' => (string) @file_get_contents($icon['path']),
            ], array_slice($found, 0, $limit)),
            'total' => count($found),
        ];
    }

    /** Le dessin d'une icône du catalogue, ou null. */
    public function svg(?string $name): ?string
    {
        $icon = filled($name) ? ($this->icons()[$name] ?? null) : null;

        return $icon === null ? null : ((string) @file_get_contents($icon['path']) ?: null);
    }

    /** @return array<string, array{name: string, group: string, path: string}> */
    private function icons(): array
    {
        if ($this->icons !== null) {
            return $this->icons;
        }

        $files = cache()->remember('filament-map.icon-catalog', now()->addDay(), fn (): array => $this->read());

        return $this->icons = array_filter($files, fn (array $icon): bool => is_file($icon['path']));
    }

    /** @return array<string, array{name: string, group: string, path: string}> */
    private function read(): array
    {
        if (! class_exists(Factory::class)) {
            return [];
        }

        try {
            $sets = app(Factory::class)->all();
        } catch (Throwable) {
            return [];
        }

        $excluded = config('filament-map.icons.exclude_sets', ['filament']);
        $groups = array_keys(config('filament-map.icons.groups', []));
        $icons = [];

        foreach ($sets as $setName => $set) {
            if (in_array($setName, $excluded, true)) {
                continue;
            }

            foreach ((array) ($set['paths'] ?? []) as $path) {
                if (! is_dir($path)) {
                    continue;
                }

                foreach (Finder::create()->files()->in($path)->name('*.svg')->sortByName() as $file) {
                    $relative = str_replace(DIRECTORY_SEPARATOR, '.', Str::beforeLast($file->getRelativePathname(), '.svg'));
                    $name = $set['prefix'].'-'.$relative;
                    $group = collect($groups)->first(fn (string $key): bool => str_starts_with($name, $key.'-')) ?? $set['prefix'];

                    $icons[$name] = ['name' => $name, 'group' => $group, 'path' => $file->getRealPath()];
                }
            }
        }

        ksort($icons);

        return $icons;
    }

    private function groupLabel(string $group): string
    {
        return config("filament-map.icons.groups.{$group}") ?? $group;
    }

    /** Le nom lisible : sans le préfixe de son groupe (`map-pin`). */
    private function label(array $icon): string
    {
        return Str::after($icon['name'], $icon['group'].'-');
    }
}
