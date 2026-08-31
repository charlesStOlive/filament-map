---
title: Points géographiques
icon: heroicon-o-map-pin
order: 40
---

# Points géographiques

Un point géographique représente un lieu précis. Il peut être rattaché à plusieurs cartes et utilisé comme source d'un événement dans un parcours interactif.

## Informations du point

- **Nom** : nom lisible du lieu.
- **Slug** : identifiant technique unique et stable.
- **Type de point** : catégorie qui fournit l'apparence par défaut.
- **Active** : contrôle la disponibilité du point.
- **Description** : information éditoriale ou interne sur le lieu.

Un point sans type reste utilisable, mais son apparence dépendra davantage des valeurs par défaut du projet.

## Coordonnées

- **Latitude** : position nord/sud, comprise entre `-90` et `90`.
- **Longitude** : position est/ouest, comprise entre `-180` et `180`.
- **Sélecteur de coordonnées** : place le point visuellement et synchronise les deux valeurs.

Dans la plupart des formulaires, la latitude est affichée avant la longitude. Dans un fichier GeoJSON, l'ordre est généralement longitude puis latitude. Cette différence est une cause fréquente de points placés au mauvais endroit.

## Rattacher le point à des cartes

Le champ **Cartes** accepte plusieurs cartes. Le point reste un seul enregistrement partagé. Le retirer d'une carte ne le supprime pas de la bibliothèque.

Un rattachement peut aussi porter des informations propres à une carte : ordre, visibilité, libellé, infobulle, contenu de popup, style et options. Ces valeurs ont priorité sur les réglages généraux du point.

## Apparence

- **Image du marqueur** : image propre à ce point.
- **Style du marqueur** : surcharge structurée de la forme, du contenu, de la taille ou du CSS.
- **Options** : paramètres complémentaires du moteur de carte.

Ne recopiez pas le même style sur des dizaines de points. Créez plutôt un type de point commun, puis utilisez une surcharge uniquement pour les exceptions.

## Point et hotpoint

Dans l'administration cartographique, la ressource est appelée **point géographique**. Dans un parcours interactif, ce même point peut être présenté comme un **hotpoint** : un lieu cliquable susceptible de déclencher des actions.

Le hotpoint n'est donc pas une deuxième copie du lieu. C'est le rôle joué par le point dans le parcours.

## Avant de rattacher à un parcours

- Vérifiez le point sur une carte.
- Donnez-lui un slug stable.
- Confirmez son état actif.
- Choisissez son type et son apparence.
- Ne placez pas le texte narratif principal dans la description si celui-ci doit s'ouvrir de manière interactive ; utilisez un contenu narratif.
