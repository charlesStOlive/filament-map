---
title: Cartes
icon: heroicon-o-map
order: 20
---

# Cartes

Une carte définit le cadre géographique : centre, zoom initial, limites de zoom et limites géographiques. Une scène cartographique utilise ce cadre et compose les couches.

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

## Composer les couches

Ouvrez **Scènes cartographiques** pour assembler les couches et définir leur visibilité initiale. Une même carte peut servir à plusieurs scènes. Les points métier restent dans les scénarios qui utilisent ces scènes.

## Options avancées

Les champs JSON ou clé/valeur s'adressent aux besoins non couverts par les champs principaux. N'y placez que des options comprises par le moteur de rendu. Une syntaxe JSON invalide empêche la configuration d'être utilisée correctement.

## Bonnes pratiques

- Donnez un nom fonctionnel à la carte, pas seulement un nom de projet.
- Gardez le slug stable.
- Testez les zooms minimum et maximum sur mobile.
- Utilisez les surcharges locales avec parcimonie ; préférez corriger la couche lorsque toutes les cartes doivent changer.
- Vérifiez l'aperçu après chaque modification importante.
