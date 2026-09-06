---
title: Scènes cartographiques
icon: heroicon-o-square-3-stack-3d
order: 25
---

# Scènes cartographiques

Une scène prépare l’utilisation d’une carte : ses couches, leur ordre, leur style et leur visibilité à l’ouverture. Plusieurs scènes peuvent utiliser la même carte et les mêmes sources sans partager leur composition.

La carte définit le cadre géographique et les limites de zoom. La bibliothèque contient les couches réutilisables. La scène choisit les couches à afficher et peut préciser un centre et un zoom initiaux. Les points métier et leurs interactions restent dans les scénarios qui utilisent la scène.

## Préparer une scène

1. Créez ou choisissez une carte de référence.
2. Ouvrez **Scènes cartographiques**, puis **Créer**.
3. Choisissez les couches de la bibliothèque et ordonnez-les.
4. Pour chaque couche, choisissez sa visibilité initiale et, si nécessaire, un style propre à cette scène.
5. Laissez les champs de cadrage vides pour reprendre la vue de la carte, ou choisissez une autre vue.
6. Enregistrez puis contrôlez l’aperçu.

Une couche masquée à l’ouverture fait toujours partie de la scène : une interaction peut l’afficher. Une couche absente doit être ajoutée dans cet écran avant de pouvoir être utilisée par un scénario.

## Utiliser la scène

Dans Voyage simplifié, choisissez une scène puis renseignez les journées. Leurs points appartiennent au voyage ; deux voyages partageant une scène gardent leurs propres étapes.

Dans l’éditeur complexe, ajoutez une scène dans l’onglet **Scènes cartographiques**, puis les hotpoints et contenus du scénario. Une action sur une couche cible cette scène et la clé d’une de ses couches. On ne rattache plus de couche directement au scénario.

Pour un clic GeoJSON, la source est la scène. La condition `layer.key` permet de limiter le déclencheur à une couche particulière.

Une scène inactive, ou dont la carte est inactive ou supprimée, n’est pas affichée.
