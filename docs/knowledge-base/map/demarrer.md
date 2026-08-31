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
4. **Cartes** : assemblent une vue initiale, des couches et des points.

Une carte décrit ce qui doit être affiché. Les réactions à un clic, l'ouverture d'un contenu ou le déplacement automatique de la carte sont configurés dans un **parcours interactif**.

## Ordre conseillé

Pour créer une carte fiable sans revenir plusieurs fois sur les mêmes écrans :

1. Créez les types de points nécessaires.
2. Créez les couches et vérifiez leur aperçu.
3. Créez les points et placez-les avec le sélecteur de coordonnées.
4. Créez la carte, réglez sa vue initiale puis rattachez ses couches et ses points.
5. Ouvrez l'aperçu de la carte.
6. Si la carte doit être interactive, rattachez-la à un parcours et configurez les déclencheurs.

## Trois notions à ne pas confondre

### Carte et couche

La **carte** est le conteneur visible. Une **couche** est un jeu de données superposé à cette carte. Une même couche peut être utilisée sur plusieurs cartes.

### Point et type de point

Le **point** porte le lieu et ses coordonnées. Le **type de point** fournit son apparence par défaut. Modifier un type peut donc modifier plusieurs points.

### Affichage et comportement

Filament Map gère les données et le rendu. Filament Orchestrator gère les événements et les actions. Par exemple :

- la couleur d'un point appartient à la cartographie ;
- « au clic sur ce point, ouvrir cette histoire » appartient au parcours interactif.

## Vérification rapide

Avant de publier une carte, contrôlez :

- que la carte, ses couches et ses points sont actifs ;
- que les sources des couches sont accessibles ;
- que la latitude et la longitude ne sont pas inversées ;
- que la vue initiale montre la zone utile ;
- que les niveaux de zoom minimum et maximum restent cohérents ;
- que l'aperçu correspond au résultat attendu.
