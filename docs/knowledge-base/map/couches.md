---
title: Couches cartographiques
icon: heroicon-o-square-3-stack-3d
order: 30
---

# Couches cartographiques

Une couche est un jeu de données affichable sur une ou plusieurs scènes cartographiques : fond de tuiles, frontières, itinéraire, zones, points calculés ou illustration SVG.

## Informations générales

- **Scène d'exemple** : scène utilisée uniquement pour contrôler visuellement la couche. Elle ne rattache pas automatiquement la couche à cette scène.
- **Nom** : libellé compréhensible dans l'administration.
- **Clé** : identifiant technique unique. Gardez-la stable car le code ou un parcours peut l'utiliser.
- **Type** : nature du rendu.
- **Type de source** : endroit où les données sont lues.
- **Active** : permet de désactiver la couche sans la supprimer.

## Types de couche

- **GeoJSON** : objets géographiques structurés en JSON, comme des polygones, lignes et points.
- **Tuiles** : images chargées par niveau de zoom et coordonnées, généralement avec une URL contenant `{z}`, `{x}` et `{y}`.
- **Points** : collection de marqueurs produite par une source dédiée.
- **Superposition SVG** : dessin vectoriel placé au-dessus d'une emprise géographique.
- **Personnalisée** : rendu pris en charge par une extension propre au projet.

## Types de source

### URL

Les données sont chargées depuis une adresse distante ou publique. Pour un fond de tuiles, l'URL ressemble souvent à :

```text
https://serveur.example/{z}/{x}/{y}.png
```

Vérifiez que le serveur autorise l'accès depuis le navigateur et que l'URL utilise HTTPS sur un site HTTPS.

### JSON

Les données sont collées directement dans le formulaire. Ce choix est pratique pour une petite couche stable ou un essai. Pour un volume important ou fréquemment mis à jour, préférez un fichier ou une URL.

### Fichier

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

## Options

Les **options** pilotent le comportement du moteur de rendu : opacité, attribution, ordre d'affichage, interaction ou paramètres spécifiques au type de couche. Elles ne remplacent pas le style visuel.

## Diagnostiquer un aperçu vide

1. Vérifiez que la couche est active.
2. Vérifiez la scène d'exemple et sa zone visible.
3. Ouvrez directement l'URL ou le fichier source.
4. Validez la syntaxe JSON.
5. Pour du GeoJSON, vérifiez l'ordre des coordonnées : longitude puis latitude.
6. Contrôlez que le type de couche correspond réellement aux données.
7. Retirez temporairement les styles et options avancés.
