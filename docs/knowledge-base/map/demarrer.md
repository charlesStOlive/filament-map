---
title: Bien démarrer avec la cartographie
icon: heroicon-o-play
order: 10
---

# Bien démarrer avec la cartographie

La cartographie est organisée autour de quatre ressources complémentaires :

1. **Types de points** : définissent une apparence réutilisable, par exemple « Étape », « Musée » ou « Restaurant ».
2. **Points géographiques** : représentent des lieux avec une latitude et une longitude.
3. **Couches cartographiques** : ajoutent un fond, des limites, des tracés ou des données GeoJSON.
4. **Scènes cartographiques** : assemblent une vue initiale (centre, zoom, limites) et une composition de couches. C'est la seule ressource qui produit une carte affichable.

Une scène décrit ce qui doit être affiché en fond. Les points géographiques n'appartiennent pas à une scène : ils sont placés dessus par un **parcours interactif** (ou par l'automatisation Voyage simplifié, qui en crée un pour chaque journée). Les réactions à un clic, l'ouverture d'un contenu ou le déplacement automatique de la vue sont aussi configurés dans le parcours interactif.

## Ordre conseillé

Pour créer une scène fiable sans revenir plusieurs fois sur les mêmes écrans :

1. Créez les types de points nécessaires.
2. Créez les couches et vérifiez leur aperçu.
3. Créez la scène, réglez sa vue initiale (cadrage) puis composez ses couches.
4. Ouvrez l'aperçu de la scène.
5. Créez les points géographiques nécessaires et placez-les avec le sélecteur de coordonnées.
6. Rattachez la scène et les points à un parcours interactif (ou à un voyage simplifié), puis configurez les déclencheurs.

## Notions à ne pas confondre

### Scène et couche

La **scène cartographique** est le conteneur visible : elle porte le cadrage et choisit les couches à afficher. Une **couche** est un jeu de données superposé à une scène. Une même couche peut être utilisée sur plusieurs scènes.

### Point et type de point

Le **point** porte le lieu et ses coordonnées. Le **type de point** fournit son apparence par défaut. Modifier un type peut donc modifier plusieurs points.

### Point et scène

Un point géographique n'est jamais rattaché directement à une scène : il n'existe aucun champ ni écran pour cela. C'est le parcours interactif (ou le voyage simplifié) qui les fait cohabiter, en donnant à la scène le rôle **Scène cartographique** et à chaque point le rôle **Hotpoint**.

### Affichage et comportement

Filament Map gère les données et le rendu. Filament Orchestrator gère les événements et les actions. Par exemple :

- la couleur d'un point appartient à la cartographie ;
- « au clic sur ce point, ouvrir cette histoire » appartient au parcours interactif.

## Vérification rapide

Avant de publier une scène, contrôlez :

- que la scène, ses couches et les points utilisés sont actifs ;
- que les sources des couches sont accessibles ;
- que la latitude et la longitude ne sont pas inversées ;
- que la vue initiale montre la zone utile ;
- que les niveaux de zoom minimum et maximum restent cohérents ;
- que l'aperçu correspond au résultat attendu.
