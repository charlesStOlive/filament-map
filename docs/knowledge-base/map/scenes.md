---
title: Scènes cartographiques
icon: heroicon-o-square-3-stack-3d
order: 20
---

# Scènes cartographiques

Une scène cartographique est la seule ressource qui décrit une carte affichable : son cadre géographique (centre, zoom, limites) et la composition de ses couches (lesquelles, dans quel ordre, avec quelle visibilité à l'ouverture). Il n'existe pas de ressource « Carte » séparée : tout est ici.

La bibliothèque des couches contient les couches réutilisables. La scène choisit celles à afficher et peut préciser un centre et un zoom initiaux propres. Les points géographiques et leurs interactions restent en dehors de la scène : ils sont placés par les parcours interactifs qui l'utilisent (voir plus bas).

## Créer une scène

### Informations générales

- **Nom** : nom lisible dans l'administration.
- **Slug** : identifiant technique lisible dans une URL. Il doit être unique et rester stable après mise en service.
- **Mode** : famille de rendu utilisée par la scène.
- **Active** : une scène inactive reste enregistrée mais ne doit pas être proposée au public.
- **Description** : note éditoriale ou fonctionnelle destinée aux administrateurs.

Le slug est généré à partir du nom. Évitez de le modifier si la scène est déjà référencée par un parcours ou une page publique.

### Choisir le mode

- **GeoJSON** : adapté aux formes, limites et tracés décrits par des données géographiques.
- **OpenStreetMap** : fond cartographique standard basé sur des tuiles.
- **Hybride** : combinaison de plusieurs types de rendu selon la configuration du projet.
- **Superposition SVG** : image vectorielle placée sur des limites géographiques, utile pour un plan illustré.

Le mode ne remplace pas les couches. Il définit le contexte général dans lequel elles sont rendues.

### Composer les couches

1. Choisissez les couches de la bibliothèque et ordonnez-les.
2. Pour chaque couche, choisissez sa visibilité initiale et, si nécessaire, un style propre à cette scène.

Une couche masquée à l'ouverture fait toujours partie de la scène : une interaction peut l'afficher. Une couche absente doit être ajoutée dans la bibliothèque des couches avant de pouvoir être utilisée ici.

### Régler la vue initiale (cadrage)

- **Latitude du centre** et **longitude du centre** : point placé au centre à l'ouverture.
- **Zoom** : niveau initial (accepte deux décimales, par exemple `9.33`). Une valeur faible montre une grande zone ; une valeur élevée montre davantage de détails.
- **Zoom minimum** et **zoom maximum** : limitent respectivement le recul et le rapprochement autorisés (également en décimal).
- **Sélecteur de cadrage** : ouvre une grande carte. **Cliquez** pour poser le repère (ou déplacez-le, ou cherchez un lieu ou une adresse : cliquer un résultat y pose le repère). La scène s'ouvrira **centrée sur le repère**, au **zoom que la carte affiche** (arrondi au centième). Vous pouvez déplacer la carte pour regarder ailleurs sans perdre la position du repère ; « Centrer sur le repère » y ramène la vue.
- **Bounds** : rectangle géographique décrivant la zone visible ou autorisée (réglages avancés).

Pour éviter une scène vide à l'ouverture, utilisez le sélecteur de cadrage après avoir ajouté les premières couches.

## Enregistrer puis contrôler

Après avoir composé les couches et réglé le cadrage, enregistrez puis contrôlez l'aperçu affiché sur la fiche de la scène.

## Utiliser la scène

Une scène ne place jamais de point toute seule : les points géographiques (hotpoints) apparaissent sur elle uniquement à travers un parcours interactif qui rattache à la fois la scène (rôle **Scène cartographique**) et les points (rôle **Hotpoint**) — voir la documentation de l'orchestrateur. Deux parcours peuvent partager la même scène tout en montrant des points différents.

Dans Voyage simplifié, choisissez une scène puis renseignez les journées : chaque journée crée automatiquement son propre hotpoint, sans jamais modifier la scène partagée. Un voyage peut aussi surcharger localement le centre et le zoom de départ, sans toucher à la scène ni aux autres voyages qui l'utilisent.

Dans l'éditeur complexe (parcours interactif), ajoutez une scène dans l'onglet **Scènes cartographiques**, puis les hotpoints et contenus du scénario. Une action sur une couche cible cette scène et la clé d'une de ses couches.

Pour un clic GeoJSON, la source est la scène. La condition `layer.key` permet de limiter le déclencheur à une couche particulière.

Une scène inactive n'est pas affichée.

## Bonnes pratiques

- Donnez un nom fonctionnel à la scène, pas seulement un nom de projet.
- Gardez le slug stable.
- Testez les zooms minimum et maximum sur mobile.
- Utilisez les surcharges locales (par exemple celles d'un voyage) avec parcimonie ; préférez corriger la scène ou la couche lorsque tous les parcours doivent changer.
- Vérifiez l'aperçu après chaque modification importante.
