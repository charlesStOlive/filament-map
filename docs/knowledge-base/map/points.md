---
title: Points géographiques
icon: heroicon-o-map-pin
order: 40
---

# Points géographiques

Un point géographique représente un lieu précis, indépendant de toute scène cartographique. Il devient visible et interactif en étant utilisé comme hotpoint dans un ou plusieurs parcours interactifs, où il peut aussi servir de source d'événement.

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
- **Coordonnées** : un résumé (latitude, longitude) et une icône pour le modifier. Le popup ouvre une grande carte (avec un bouton plein écran) : cliquez pour placer le point, ou déplacez le repère, saisissez des coordonnées, ou cherchez un lieu ou une adresse. **Valider** reporte le choix dans le formulaire ; **Annuler**, la croix et Échap l'abandonnent.

Dans la plupart des formulaires, la latitude est affichée avant la longitude. Dans un fichier GeoJSON, l'ordre est généralement longitude puis latitude. Cette différence est une cause fréquente de points placés au mauvais endroit.

## Afficher le point sur une scène

Il n'existe pas de champ pour rattacher un point directement à une scène cartographique : ce lien se fait uniquement à travers un parcours interactif (ou l'automatisation Voyage simplifié). Le point reste un seul enregistrement partagé, réutilisable par plusieurs parcours ; le retirer d'un parcours ne le supprime pas de la bibliothèque.

Un parcours peut cependant porter ses propres réglages pour l'occurrence d'un point sur sa scène, par exemple sa clé, son ordre d'affichage ou son infobulle. Ces réglages vivent dans le parcours, pas sur le point lui-même, et n'affectent pas les autres parcours qui réutilisent ce même point.

## Apparence

- **Image du marqueur** : image propre à ce point.
- **Style du marqueur** : surcharge structurée de la forme, du contenu, de la taille ou du CSS.
- **Options** : paramètres complémentaires du moteur de carte.

Ne recopiez pas le même style sur des dizaines de points. Créez plutôt un type de point commun, puis utilisez une surcharge uniquement pour les exceptions.

## Point et hotpoint

Dans l'administration cartographique, la ressource est appelée **point géographique**. Dans un parcours interactif, ce même point peut être présenté comme un **hotpoint** : un lieu cliquable susceptible de déclencher des actions.

Le hotpoint n'est donc pas une deuxième copie du lieu. C'est le rôle joué par le point dans le parcours.

## Avant de rattacher à un parcours

- Vérifiez le point sur une scène (aperçu).
- Donnez-lui un slug stable.
- Confirmez son état actif.
- Choisissez son type et son apparence.
- Ne placez pas le texte narratif principal dans la description si celui-ci doit s'ouvrir de manière interactive ; utilisez un contenu narratif.
