---
title: Glossaire cartographique
icon: heroicon-o-book-open
order: 70
---

# Glossaire cartographique

## Bounds ou emprise

Rectangle défini par des coordonnées minimales et maximales. Il décrit une zone géographique à afficher ou à limiter.

## Clé

Identifiant technique stable destiné aux relations, au code et aux parcours. Elle est différente du nom affiché.

## Couche

Jeu de données superposé à une scène cartographique : tuiles, GeoJSON, tracé, zones, points ou SVG.

## GeoJSON

Format JSON standard pour décrire des objets géographiques et leurs propriétés. Ses coordonnées utilisent généralement l'ordre longitude, latitude.

## Hotpoint

Nom fonctionnel d'un point géographique utilisé comme élément interactif dans un parcours.

## Latitude

Position nord/sud, de `-90` à `90`.

## Longitude

Position est/ouest, de `-180` à `180`.

## Marqueur

Représentation visuelle d'un point sur une scène.

## Rattachement ou pivot

Relation entre deux ressources, par exemple une couche et une scène cartographique. Elle peut porter des réglages propres à cette association. Les points géographiques n'ont, eux, aucun rattachement de ce type : ils rejoignent une scène uniquement via un parcours interactif.

## Scène cartographique

Conteneur qui rassemble une vue initiale (centre, zoom, limites) et une composition de couches. C'est la seule ressource qui produit une carte affichable ; il n'existe pas de ressource « Carte » séparée.

## Slug

Identifiant lisible utilisé dans les URL ou les échanges. Il doit être unique et, autant que possible, stable.

## Source

Origine des données d'une couche : URL, JSON saisi ou fichier téléversé.

## Tuiles

Petites images cartographiques chargées selon le zoom et la position. Leur URL contient souvent `{z}`, `{x}` et `{y}`.

## Type de point

Catégorie réutilisable qui définit l'apparence par défaut de plusieurs points.

## Zoom

Niveau de détail de la scène. Plus la valeur est élevée, plus la zone affichée est précise et réduite. Les valeurs acceptent deux décimales (par exemple `9.33`).
