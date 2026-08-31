---
title: Cartes
icon: heroicon-o-map
order: 20
---

# Cartes

Une carte assemble une vue initiale, des couches et des points géographiques. Elle peut être utilisée seule ou devenir l'élément principal d'un parcours interactif.

## Créer une carte

### Informations générales

- **Nom** : nom lisible dans l'administration.
- **Slug** : identifiant technique lisible dans une URL. Il doit être unique et rester stable après mise en service.
- **Mode** : famille de rendu utilisée par la carte.
- **Active** : une carte inactive reste enregistrée mais ne doit pas être proposée au public.
- **Description** : note éditoriale ou fonctionnelle destinée aux administrateurs.

Le slug est généré à partir du nom. Évitez de le modifier si la carte est déjà référencée par une page ou un parcours.

## Choisir le mode

- **GeoJSON** : adapté aux formes, limites et tracés décrits par des données géographiques.
- **OpenStreetMap** : fond cartographique standard basé sur des tuiles.
- **Hybride** : combinaison de plusieurs types de rendu selon la configuration du projet.
- **Superposition SVG** : image vectorielle placée sur des limites géographiques, utile pour un plan illustré.

Le mode ne remplace pas les couches. Il définit le contexte général dans lequel elles sont rendues.

## Régler la vue initiale

- **Latitude du centre** et **longitude du centre** : point placé au centre à l'ouverture.
- **Zoom** : niveau initial. Une valeur faible montre une grande zone ; une valeur élevée montre davantage de détails.
- **Zoom minimum** : limite le recul autorisé.
- **Zoom maximum** : limite le rapprochement autorisé.
- **Sélecteur de vue** : permet de déplacer et zoomer directement sur une carte. Les valeurs numériques et les limites sont synchronisées automatiquement.
- **Bounds** : rectangle géographique décrivant la zone visible ou autorisée.

Pour éviter une carte vide, utilisez le sélecteur de vue après avoir ajouté les premières données.

## Ajouter des couches

Dans **Couches de la carte**, chaque ligne représente l'utilisation d'une couche sur cette carte :

- **Couche** : donnée cartographique à afficher.
- **Ordre** : ordre d'empilement. Une couche rendue plus tard peut recouvrir les précédentes.
- **Visible par défaut** : indique si elle est affichée dès l'ouverture.
- **Style**, **règles de style** et **options** : surcharges propres à cette carte.

Une surcharge ne modifie pas la couche d'origine. Utilisez-la lorsqu'une même couche doit avoir un rendu différent sur une carte précise.

## Ajouter des points

Les points peuvent être rattachés depuis leur propre écran ou par les outils disponibles sur la carte. Le rattachement ne duplique pas le point : il crée une relation entre la carte et un lieu existant.

## Options avancées

Les champs JSON ou clé/valeur s'adressent aux besoins non couverts par les champs principaux. N'y placez que des options comprises par le moteur de rendu. Une syntaxe JSON invalide empêche la configuration d'être utilisée correctement.

## Bonnes pratiques

- Donnez un nom fonctionnel à la carte, pas seulement un nom de projet.
- Gardez le slug stable.
- Testez les zooms minimum et maximum sur mobile.
- Utilisez les surcharges locales avec parcimonie ; préférez corriger la couche lorsque toutes les cartes doivent changer.
- Vérifiez l'aperçu après chaque modification importante.
