---
title: Apparence, priorités et options
icon: heroicon-o-swatch
order: 60
---

# Apparence, priorités et options

Plusieurs niveaux peuvent définir l'apparence d'un même élément. Le niveau le plus précis complète ou remplace les valeurs générales.

## Priorité d'un marqueur

Du plus général au plus précis :

1. configuration globale du plugin ;
2. style du type de point ;
3. style du point géographique ;
4. style du rattachement entre le point et une carte.

Si une couleur modifiée sur le type ne semble pas appliquée, vérifiez les niveaux 3 et 4. Une surcharge plus précise peut encore imposer l'ancienne valeur.

## Priorité d'une couche

1. style et options enregistrés sur la couche ;
2. style, règles et options du rattachement de cette couche à une carte.

Une surcharge au niveau de la carte est adaptée à une exception. Pour une correction générale, modifiez la couche elle-même.

## Différence entre style et options

- **Style** : décrit principalement l'apparence, comme la couleur, l'épaisseur ou l'opacité.
- **Options** : décrivent le comportement ou la configuration du moteur, comme l'interactivité, l'attribution ou certains réglages de chargement.
- **Règles de style** : choisissent un style selon les données d'un objet.

## Champs JSON et clé/valeur

Les écrans proposent soit une zone JSON, soit un éditeur clé/valeur. Dans les deux cas :

- utilisez des clés attendues par le moteur ;
- respectez les types, par exemple `true` sans guillemets pour un booléen en JSON ;
- évitez d'y stocker du texte métier sans rapport avec le rendu ;
- testez après chaque ajout.

Exemple JSON valide :

```json
{
  "interactive": true,
  "opacity": 0.8
}
```

## Revenir à l'héritage

Pour retrouver la valeur du niveau supérieur, supprimez la clé de surcharge au lieu de recopier la valeur actuelle. Le futur changement du réglage général pourra alors se propager.
