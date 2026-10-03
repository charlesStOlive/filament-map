# Changelog

All notable changes to `filament-map` will be documented in this file.

## Unreleased

- Couches : type « Fond de carte vectoriel » (`style`, URL du style.json dans `source_url`), migration des anciennes
  couches `tile` + `options.style_url`.
- Couches : vérification à l'enregistrement, bouton « Vérifier » et commande `filament-map:check-layers` ; état
  (`check_status`, `check_message`, `checked_at`) dans la liste et la fiche.
- Aperçu d'une couche : clés `{key:…}` remplacées, chargement suivi (en cours, chargé, erreur expliquée), mise à jour
  au fil du formulaire.
- Cartes : un style qui ne se charge pas laisse place au fond par défaut (événement `filament-map:layer-error`) ;
  changer de fond ne revient plus au fond de départ.
- Initial release
