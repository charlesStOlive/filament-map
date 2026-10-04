<?php

namespace CharlesStOlive\FilamentMap\Support;

use BladeUI\Icons\Factory;
use CharlesStOlive\FilamentMap\Models\GeoPointType;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Les icônes qu'on peut poser dans un marqueur (IconPicker), rangées en groupes :
 *
 * - en tête, les **dessins des types de points** : la forme SVG personnalisée de chaque type (actif ou non : un type
 *   peut n'exister que pour son dessin), nommée `type:<clé>` (voir MarkerSvg::icon()) ;
 * - puis les jeux Blade Icons de l'application — les variantes d'Heroicons à part (contour, plein, mini, micro :
 *   `filament-map.icons.groups`), un groupe par autre jeu —, nommées comme on les écrit ailleurs (`heroicon-o-map-pin`).
 *
 * Les fichiers des jeux sont listés une fois, puis gardés en cache ; les dessins des types sont relus à chaque fois.
 */
final class IconCatalog
{
    /** Le groupe des dessins des types de points. */
    public const TYPES_GROUP = 'type';

    /** @var array<string, array{name: string, group: string, path?: string, label?: string, svg?: string}>|null */
    private ?array $icons = null;

    /** @return array<string, string> Clé du groupe → libellé, dans l'ordre où on les propose. */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->icons() as $icon) {
            $groups[$icon['group']] ??= $this->groupLabel($icon['group']);
        }

        // Les dessins des types d'abord ; puis les groupes configurés, dans leur ordre ; les autres jeux ensuite, par nom.
        $order = array_flip([self::TYPES_GROUP, ...array_keys(config('filament-map.icons.groups', []))]);
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

            // Un dessin de type se cherche aussi par le nom du type.
            $haystack = $icon['name'].' '.Str::lower(Str::ascii($icon['label'] ?? ''));

            foreach ($words as $word) {
                if (! str_contains($haystack, $word)) {
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
                'svg' => $this->drawing($icon),
            ], array_slice($found, 0, $limit)),
            'total' => count($found),
        ];
    }

    /** Le dessin d'une icône du catalogue, ou null. */
    public function svg(?string $name): ?string
    {
        $icon = filled($name) ? ($this->icons()[$name] ?? null) : null;

        return $icon === null ? null : ($this->drawing($icon) ?: null);
    }

    private function drawing(array $icon): string
    {
        return $icon['svg'] ?? (string) @file_get_contents($icon['path']);
    }

    /** @return array<string, array{name: string, group: string, path?: string, label?: string, svg?: string}> */
    private function icons(): array
    {
        if ($this->icons !== null) {
            return $this->icons;
        }

        $files = cache()->remember('filament-map.icon-catalog', now()->addDay(), fn (): array => $this->read());

        return $this->icons = [
            ...$this->typeDrawings(),
            ...array_filter($files, fn (array $icon): bool => is_file($icon['path'])),
        ];
    }

    /**
     * Les dessins des types de points : la forme SVG personnalisée de chaque type qui en a une lisible, nettoyée et sans
     * sa zone de contenu.
     *
     * @return array<string, array{name: string, group: string, label: string, svg: string}>
     */
    private function typeDrawings(): array
    {
        $drawings = [];

        foreach (GeoPointType::query()->orderBy('name')->get() as $type) {
            $svg = MarkerSvg::typeDrawing($type);

            if ($svg !== null) {
                $name = MarkerSvg::TYPE_ICON_PREFIX.$type->key;
                $drawings[$name] = ['name' => $name, 'group' => self::TYPES_GROUP, 'label' => $type->name, 'svg' => $svg];
            }
        }

        return $drawings;
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
        return $group === self::TYPES_GROUP
            ? 'Dessins des types de points'
            : (config("filament-map.icons.groups.{$group}") ?? $group);
    }

    /** Le nom lisible : celui du type pour un dessin de type, sinon le nom sans le préfixe de son groupe (`map-pin`). */
    private function label(array $icon): string
    {
        return $icon['label'] ?? Str::after($icon['name'], $icon['group'].'-');
    }
}
