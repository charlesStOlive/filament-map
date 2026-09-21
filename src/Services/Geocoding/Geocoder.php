<?php

namespace CharlesStOlive\FilamentMap\Services\Geocoding;

/**
 * Retrouve des lieux à partir d'un texte (« Siem Reap », « 12 rue de la Paix, Paris »).
 *
 * Le pilote par défaut est Nominatim (OpenStreetMap : gratuit, sans clé). Pour un autre service, on écrit une classe qui
 * implémente cette interface et on la nomme dans `filament-map.geocoding.driver`.
 */
interface Geocoder
{
    /**
     * @return array<int, GeocodingResult> Du plus pertinent au moins pertinent ; vide quand rien n'est trouvé.
     *
     * @throws GeocodingException Quand le service ne répond pas ou refuse la recherche : le message est destiné à l'utilisateur.
     */
    public function search(string $query, int $limit = 5): array;
}
