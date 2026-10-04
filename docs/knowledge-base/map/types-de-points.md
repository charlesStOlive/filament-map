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
- **Couleur** : celle de la forme. Un parcours peut la remplacer pour ses points.
- **Ordre** : position dans les listes ; les petites valeurs apparaissent en premier.
- **Active** : rend le type disponible sans supprimer sa configuration.
- **Description** : explique quand utiliser ce type.

## Aperçu

À droite du formulaire, l'aperçu dessine le marqueur exactement comme la carte, et suit chaque réglage :

- en **taille réelle**, sur un fond clair et sur un fond sombre ;
- **agrandi** autant qu'il tient dans la fenêtre, avec sa zone de contenu en pointillés et une croix rouge à l'endroit de la position du point : c'est là que l'ancrage pose le marqueur (la pointe d'une épingle, le centre d'un cercle), avant décalage et rotation. Le dessin est toujours centré dans la fenêtre, même décalé ou pivoté ;
- son **statut** : « Accepte une image », « Icône », « Texte », « Forme seule »… Quand un réglage ne produit pas l'effet attendu, l'aperçu dit pourquoi : forme sans zone de contenu, icône introuvable, SVG illisible.

La liste des types montre aussi, pour chacun, son marqueur en taille réelle et ce statut (colonne « Marqueur »), avec son image par défaut s'il en a une, sinon une image d'exemple.

Quand le type accepte une image, des **images d'exemple** (carrée, paysage, portrait, l'image par défaut du type s'il en a une, ou aucune) montrent comment une image se recadre dans la zone. Chacune a un repère à ses bords : ce qui disparaît est ce que la zone coupe.

## Rendu par défaut

Le formulaire suit l'ordre du dessin : la section **Forme** (la forme, sa taille, sa position), puis **Contenu de la zone** (ce qu'elle montre), puis **Options avancées** (variables de style, options), repliée.

### Forme

- **Pin** : marqueur cartographique classique.
- **Cercle** : repère compact adapté aux données nombreuses.
- **Étoile** : met en avant un point remarquable.
- **SVG** : forme vectorielle personnalisée.

### Contenu de la zone

La section **Contenu de la zone** commence par dire si la forme en a une : les formes fournies (épingle, cercle, étoile) en ont toutes une ; un SVG personnalisé seulement s'il marque un élément `data-slot` (voir plus bas). Sans zone, les choix autres que « Rien » sont grisés : la forme ne montre qu'elle-même.

La zone montre :

- **Rien** : le point n'est que sa forme et sa couleur.
- **Une icône** : celle du champ **Icône**. Son bouton **Choisir…** ouvre un popup qui parcourt toutes les icônes de l'application : cherchez par nom (en anglais : `map`, `camera`, `plane`…), filtrez par jeu (Heroicons en contour, plein, mini ou micro ; Font Awesome), puis cliquez sur l'icône. En tête, **Dessins des types de points** : la forme SVG personnalisée de chaque type peut servir d'icône dans un autre marqueur — une île dessinée pour un type, posée dans une épingle. Elle y est rendue d'une seule couleur, comme une icône. Un type inactif garde son dessin disponible : on peut créer un type rien que pour son dessin. Un parcours peut remplacer l'icône pour un point : dans le voyage, une période peut prendre la sienne.

  **Inverser les couleurs de la zone** pose l'icône (ou le texte) de la couleur du point sur un fond blanc, au lieu de blanc sur la couleur du point.
- **Une image (mini-vignette)** : celle que le parcours donne au point — dans le voyage, l'image de une de l'étape, à défaut sa première photo —, sinon l'**image par défaut** du type. Sans aucune image, le point montre l'icône du champ **Icône, à défaut d'image**. L'image elle-même ne se choisit pas point par point : c'est le parcours qui la fournit.
- **Un texte** : court (4 caractères au plus), le même pour tous les points du type, par exemple un numéro.

Seul le champ qui sert à ce choix est affiché.

Seul un type dont le contenu est **Image**, et dont la forme a une zone de contenu, montre une image. Un parcours peut proposer une image à tous ses points : les points d'un type qui n'est qu'une forme ou une icône l'ignorent simplement, sans erreur. On peut donc changer le type des points d'un parcours sans rien changer d'autre.

### Taille et CSS

Tous les marqueurs partagent une **taille standard** : 40 px sur leur plus grand côté, celle de l'épingle (30 × 40). Le curseur **Taille** la règle en pourcentage : 100 % la garde, 60 % fait un point plus discret, 150 % un point plus visible. L'autre côté suit les proportions de la forme. Les variables CSS permettent des ajustements avancés. Testez toujours le résultat à plusieurs niveaux de zoom et sur un petit écran.

### Position

Trois réglages placent le marqueur par rapport à la position du point (la croix rouge de l'aperçu) :

- **Ancrage** : le point de la forme posé sur la position. Laissé vide, celui de la forme : la pointe d'une épingle, le `data-anchor` d'un SVG personnalisé, sinon le centre.
- **Rotation** : en degrés, autour de l'ancrage. L'image ou l'icône de la zone tourne avec la forme.
- **Décalage horizontal et vertical** : en % de la largeur et de la hauteur du marqueur, il suit donc la taille. Utile pour écarter un marqueur de sa position, par exemple une étiquette posée à côté du lieu.

### SVG personnalisé

Le SVG convient à une identité visuelle spécifique. Utilisez un dessin simple, avec une `viewBox` correcte, et `currentColor` pour ce qui doit prendre la couleur du point. Le rendu est nettoyé avant affichage : les scripts, contenus embarqués et liens externes sont retirés. Un SVG illisible est remplacé par l'épingle.

Le SVG désigne lui-même deux choses :

- **sa zone de contenu**, là où se posent l'image, l'icône ou le texte : un `circle`, une `ellipse` ou un `rect` marqué `data-slot`. Cette zone n'est pas dessinée : pour un fond ou un liseré, dessinez un autre élément en dessous. Une image remplit la zone et y est recadrée (au centre), avec les coins d'un `rect` arrondis par son `rx`. **Sans zone, la forme reste seule** : elle n'accepte ni image, ni icône, ni texte ;
- **son point d'ancrage**, posé sur la position du point : `data-anchor="bottom"` sur la balise `svg` pour une forme qui pointe vers le bas, comme une épingle. Sans lui, le centre.

On utilise un attribut et non un `id` : une carte montre des dizaines de marqueurs, et un `id` doit être unique dans la page.

Exemple, une vignette façon polaroïd qui pointe vers le bas :

```svg
<svg viewBox="0 0 40 50" data-anchor="bottom">
  <path d="M2 2h36v38H24l-4 8-4-8H2Z" fill="currentColor" stroke="#fff" stroke-width="1.5"/>
  <rect data-slot x="5" y="5" width="30" height="30" rx="3"/>
</svg>
```

Les formes fournies (épingle, cercle, étoile) sont écrites de la même façon : elles ont toutes une zone de contenu.

Sa taille suit la même règle que les autres formes : la taille standard, en pourcentage, dans les proportions de sa `viewBox`.

## Image du point

Avec un contenu **Image**, le marqueur prend la première image disponible :

1. l'image propre au point ;
2. l'image que le parcours lui propose (par exemple l'image de une de l'étape) ;
3. l'image par défaut du type.

Sans aucune image, il montre l'icône du type ; sans icône, la forme seule.

## Dupliquer un type

Pour une variante (une autre couleur, une autre taille), **Dupliquer**, dans la liste des types ou en haut de la fiche d'un type, en fait une copie : forme, contenu, taille, position, variables de style, options et image par défaut. La fenêtre ne demande que le **nom** et la **clé** de la copie, proposés d'après l'original (« … (copie) ») ; la clé doit être libre. On arrive ensuite sur la fiche de la copie. Les points de l'original restent à lui.

Il faut le droit de créer un type.

## Modifier un type existant

Une modification peut affecter tous les points qui héritent de ce type. Avant de changer radicalement sa forme ou sa signification, vérifiez le nombre de points associés. Créez un nouveau type si l'ancien et le nouveau sens doivent coexister.
