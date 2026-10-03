<?php

namespace CharlesStOlive\FilamentMap\Http\Controllers;

use CharlesStOlive\FilamentMap\Support\IconCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Le catalogue d'icônes que parcourt IconPicker : ses groupes, et les icônes qui répondent à la recherche. */
class IconCatalogController
{
    public function __invoke(Request $request, IconCatalog $catalog): JsonResponse
    {
        return response()->json([
            'groups' => $catalog->groups(),
            ...$catalog->search($request->string('search')->toString(), $request->string('group')->toString() ?: null),
        ]);
    }
}
