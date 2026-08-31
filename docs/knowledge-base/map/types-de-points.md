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
- **Image** : utilise une image de la médiathèque.
- **Texte** : affiche une valeur courte, par exemple un numéro d'étape.

### Dimensions et CSS

La largeur et la hauteur fixent la taille du marqueur. Les variables CSS permettent des ajustements avancés. Testez toujours le résultat à plusieurs niveaux de zoom et sur un petit écran.

### SVG personnalisé

Le SVG convient à une identité visuelle spécifique. Utilisez un dessin simple, avec une `viewBox` correcte. Le rendu est nettoyé avant affichage ; les scripts ou contenus dangereux ne sont pas acceptés.

## Image par défaut

L'image du type est utilisée lorsque le point ne possède pas sa propre image. Elle est particulièrement utile avec un contenu de type **Image**.

## Modifier un type existant

Une modification peut affecter tous les points qui héritent de ce type. Avant de changer radicalement sa forme ou sa signification, vérifiez le nombre de points associés. Créez un nouveau type si l'ancien et le nouveau sens doivent coexister.
