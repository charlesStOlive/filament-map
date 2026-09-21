<?php

namespace CharlesStOlive\FilamentMap\Services\Geocoding;

use RuntimeException;

/** Une recherche d'adresse qui n'a pas pu aboutir. Son message s'affiche tel quel à l'utilisateur. */
class GeocodingException extends RuntimeException {}
