---
title: Types de points
icon: heroicon-o-tag
order: 50
---

# Types de points

Un type de point est un modèle d'apparence réutilisable. Il permet de garantir que tous les lieux d'une même catégorie restent cohérents.

## Exemples

- Étape du voyage
- Hébergement
- Restaurant
- Monument
- Point de départ
- Alerte ou zone sensible

## Informations générales

- **Nom** : libellé visible dans l'administration.
- **Clé** : identifiant technique unique et stable.
- **Icône** : nom d'icône reconnu par l'application, par exemple `heroicon-o-map-pin`.
- **Couleur** : couleur principale du type.
- **Ordre** : position dans les listes ; les petites valeurs apparaissent en premier.
- **Active** : rend le type disponible sans supprimer sa configuration.
- **Description** : explique quand utiliser ce type.

## Rendu par défaut

### Forme

- **Pin** : marqueur cartographique classique.
- **Cercle** : repère compact adapté aux données nombreuses.
- **Étoile** : met en avant un point remarquable.
- **SVG** : forme vectorielle personnalisée.

### Contenu

- **Aucun** : seule la forme est affichée.
- **Icône** : affiche une icône dans le marqueur.
- **Image** : une mini-vignette ronde dans le marqueur (voir *Image du point* ci-dessous).
- **Texte** : affiche une valeur courte, par exemple un numéro d'étape.

Seul un type dont le contenu est **Image** montre une image. Un parcours peut proposer une image à tous ses points (l'image de une d'une étape, par exemple) : les points d'un type qui n'est qu'une forme ou une icône l'ignorent simplement, sans erreur. On peut donc changer le type des points d'un parcours sans rien changer d'autre.

### Dimensions et CSS

La largeur et la hauteur fixent la taille du marqueur. Les variables CSS permettent des ajustements avancés. Testez toujours le résultat à plusieurs niveaux de zoom et sur un petit écran.

### SVG personnalisé

Le SVG convient à une identité visuelle spécifique. Utilisez un dessin simple, avec une `viewBox` correcte, et `currentColor` pour ce qui doit prendre la couleur du point. Le rendu est nettoyé avant affichage : les scripts, contenus embarqués et liens externes sont retirés. Un SVG illisible est remplacé par l'épingle.

## Image du point

Avec un contenu **Image**, le marqueur prend la première image disponible :

1. l'image propre au point ;
2. l'image que le parcours lui propose (par exemple l'image de une de l'étape) ;
3. l'image par défaut du type.

Sans aucune image, il montre l'icône du type ; sans icône, la forme seule.

## Modifier un type existant

Une modification peut affecter tous les points qui héritent de ce type. Avant de changer radicalement sa forme ou sa signification, vérifiez le nombre de points associés. Créez un nouveau type si l'ancien et le nouveau sens doivent coexister.
