---
title: Couches cartographiques
icon: heroicon-o-square-3-stack-3d
order: 30
---

# Couches cartographiques

Une couche est un jeu de données affichable sur une ou plusieurs scènes cartographiques : fond de carte, frontières, itinéraire, zones, points calculés ou illustration SVG.

## Informations générales

- **Nom** : libellé compréhensible dans l'administration.
- **Clé** : identifiant technique unique. Gardez-la stable car le code ou un parcours peut l'utiliser.
- **Type** : nature de la couche (voir ci-dessous). Il décide des champs proposés.
- **Active** : permet de désactiver la couche sans la supprimer.
- **Scène d'exemple** : scène utilisée uniquement pour contrôler visuellement la couche dans l'aperçu. Elle ne rattache pas automatiquement la couche à cette scène.

## État de la couche

Chaque couche est **vérifiée** à son enregistrement : l'application interroge sa source et contrôle qu'elle contient ce que son type attend. Le résultat s'affiche dans la colonne **État** de la liste (la raison apparaît au survol) et en tête de la fiche :

- **Fonctionne** : la source répond et se lit, par exemple « Style « Backdrop » chargé : 4 source(s), 46 calque(s) ».
- **À surveiller** : elle fonctionne avec une réserve, par exemple un GeoJSON vide, ou un serveur qui n'autorise pas explicitement l'affichage depuis un autre site (CORS).
- **En erreur** : la carte ne l'affichera pas. Le message dit pourquoi : clé refusée (403), adresse introuvable (404), style collé dans une couche de tuiles, clé `{key:…}` non configurée, JSON illisible…
- **Non vérifiée** : la couche n'a pas été enregistrée depuis l'arrivée de la vérification.

Le bouton **Vérifier** (dans la liste, en tête de la fiche, ou sur plusieurs couches cochées) relance la vérification sans rien modifier : utile après un changement de clé chez le fournisseur. La commande `php artisan filament-map:check-layers` vérifie toutes les couches d'un coup.

La vérification part du serveur. L'**aperçu**, lui, charge la couche dans le navigateur, comme une carte : il affiche « Chargement… », puis « Style chargé », ou l'erreur rencontrée (en rouge). Il suit le formulaire : inutile d'enregistrer pour voir l'effet d'une nouvelle URL.

## Types de couche

- **Fond de carte vectoriel (style.json)** : un fond complet décrit par un fichier de style MapLibre, comme ceux de MapTiler ou TileCat (routes, relief, libellés). Il remplace le fond de la carte : une scène n'en montre qu'un à la fois.
- **Fond de carte en tuiles images** : un fond fait d'images carrées, à une adresse qui contient `{z}`, `{x}` et `{y}` (OpenStreetMap…).
- **Tracés et zones (GeoJSON)** : lignes, polygones ou points posés sur le fond.
- **Points (GeoJSON)** : collection de points posés sur le fond.
- **Image SVG calée sur la carte** : dessin placé sur une emprise géographique, précisée dans les options (`bounds`).
- **Personnalisée** : rendu pris en charge par une extension propre au projet.

## Ajouter un fond MapTiler

1. Dans MapTiler, copiez l'adresse du style de la carte : `https://api.maptiler.com/maps/<identifiant>/style.json?key=…`.
2. Créez une couche et collez-la dans l'URL. Le type **Fond de carte vectoriel** est choisi tout seul, et la clé, si c'est celle configurée, est remplacée par `{key:maptiler}`.
3. Enregistrez : l'état doit être **Fonctionne**. Les options peuvent rester vides.

## Source

### Fonds de carte

Un fond n'a qu'une source : son **URL**. Pour un fond vectoriel, c'est l'adresse du `style.json` ; pour des tuiles, une adresse comme :

```text
https://serveur.example/{z}/{x}/{y}.png
```

Si le fournisseur demande une clé (MapTiler, par exemple), ne l'écrivez pas dans la couche : mettez-la dans le fichier `.env` de l'application (`MAPTILER_API_KEY=…`) et citez-la dans l'URL par `{key:maptiler}`. Elle est remplacée à l'affichage, et un changement de clé ne demande pas de retoucher les couches.

### Couches de données : URL

Les données sont chargées depuis une adresse distante ou publique (`/storage/…`). Vérifiez que le serveur autorise l'accès depuis le navigateur et que l'URL utilise HTTPS sur un site HTTPS.

### Couches de données : JSON

Les données sont collées directement dans le formulaire. Ce choix est pratique pour une petite couche stable ou un essai. Pour un volume important ou fréquemment mis à jour, préférez un fichier ou une URL.

### Couches de données : fichier

Le fichier est téléversé sur le stockage public. Les formats attendus sont notamment JSON, GeoJSON et SVG. Enregistrez puis rechargez l'aperçu après le téléversement.

## Style

Le champ **Style** contient les propriétés MapLibre appliquées à l'ensemble de la couche, par exemple :

```json
{
  "color": "#2563eb",
  "weight": 3,
  "fillOpacity": 0.25
}
```

## Règles de style

Les **règles de style** permettent de varier le rendu selon les propriétés de chaque objet GeoJSON. Elles sont utiles lorsqu'une propriété comme `category`, `status` ou `region` doit déterminer une couleur.

Le style et les règles ne concernent que les couches GeoJSON : un fond porte son propre style.

## Options

Les **options** pilotent le comportement du moteur de rendu : opacité, attribution, ordre d'affichage, interaction ou paramètres spécifiques au type de couche. Elles ne remplacent pas le style visuel. Pour un fond en tuiles, on y met en général le crédit : `{"attribution": "© OpenStreetMap contributors"}`. Pour un fond vectoriel, elles sont rarement utiles. Un JSON illisible est refusé à l'enregistrement.

## Diagnostiquer un aperçu vide

1. Lisez l'**état** de la couche et le message sous l'aperçu : ils donnent le plus souvent la cause.
2. Vérifiez que la couche est active.
3. Vérifiez la scène d'exemple et sa zone visible.
4. Ouvrez directement l'URL ou le fichier source.
5. Validez la syntaxe JSON.
6. Pour du GeoJSON, vérifiez l'ordre des coordonnées : longitude puis latitude.
7. Contrôlez que le type de couche correspond réellement aux données.
8. Retirez temporairement les styles et options avancés.
