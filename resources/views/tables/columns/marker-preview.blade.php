@php
    $record = $getRecord();
    $collection = config('filament-map.media_collections.default_marker_image', 'default_marker_image');
@endphp
<div class="px-3 py-2">
    @include('filament-map::partials.marker-preview', [
        'compact' => true,
        'preview' => \CharlesStOlive\FilamentMap\Support\MarkerPreview::for(
            $record->marker_style ?? [],
            $record->icon,
            $record->color,
            $record->getFirstMediaUrl($collection) ?: null,
        ),
    ])
</div>
