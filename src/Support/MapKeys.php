<?php

namespace CharlesStOlive\FilamentMap\Support;

/**
 * Les clés des fournisseurs de fonds de carte (`filament-map.keys`) : une couche cite « {key:maptiler} » sans porter la clé,
 * elle est remplacée à l'affichage. Une clé inconnue laisse une chaîne vide, l'URL échoue alors chez le fournisseur — d'où
 * `missing()`, que la vérification d'une couche signale.
 *
 * Seules les entrées de `filament-map.keys` sont lisibles : le nom cité n'admet ni point ni autre séparateur, il ne
 * remonte donc jamais vers le reste de la configuration (APP_KEY, base de données, autres services). Ces clés partent
 * dans le navigateur avec les cartes : elles doivent être des clés publiques, restreintes chez le fournisseur.
 */
final class MapKeys
{
    private const PATTERN = '/\{key:([a-z0-9_-]+)\}/i';

    /**
     * @template T of array|string|null
     *
     * @param  T  $value
     * @return T
     */
    public static function resolve(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_contains($value, '{key:')
                ? preg_replace_callback(self::PATTERN, static fn (array $match): string => (string) self::value($match[1]), $value)
                : $value;
        }

        if (is_array($value)) {
            array_walk_recursive($value, static function (mixed &$item): void {
                $item = self::resolve($item);
            });
        }

        return $value;
    }

    /**
     * Les clés citées par la valeur mais absentes de la configuration.
     *
     * @return array<int, string>
     */
    public static function missing(mixed $value): array
    {
        $missing = [];

        foreach (is_array($value) ? $value : [$value] as $item) {
            if (is_array($item)) {
                $missing = [...$missing, ...self::missing($item)];
            } elseif (is_string($item) && preg_match_all(self::PATTERN, $item, $matches)) {
                foreach ($matches[1] as $name) {
                    if (! filled(self::value($name))) {
                        $missing[] = strtolower($name);
                    }
                }
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * Les clés configurées, pour un aperçu qui lit le formulaire en cours de saisie. Ce sont des clés publiques : elles
     * partent de toute façon vers le navigateur dans les URL des cartes.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $keys = array_filter((array) config('filament-map.keys', []), 'is_scalar');

        return array_filter(array_map('strval', $keys), 'filled');
    }

    /** Remet « {key:…} » à la place des clés dans un texte gardé ou affiché (un message d'erreur qui cite l'URL). */
    public static function redact(string $text): string
    {
        foreach (self::all() as $name => $value) {
            $text = str_replace($value, '{key:'.$name.'}', $text);
        }

        return $text;
    }

    private static function value(string $name): ?string
    {
        $keys = (array) config('filament-map.keys', []);
        $value = $keys[strtolower($name)] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
