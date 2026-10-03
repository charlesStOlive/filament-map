<?php

namespace CharlesStOlive\FilamentMap\Support;

use RuntimeException;

/** Une adresse que la vérification d'une couche refuse d'appeler (réseau interne, schéma autre que http(s)). */
final class UnsafeMapSourceUrl extends RuntimeException {}
