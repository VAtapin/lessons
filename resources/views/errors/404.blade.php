@php
    $language = request()->segment(1);
    $language = in_array($language, config('lessons.ui_locales'), true) ? $language : config('app.locale');
    $text = trans('interface', [], $language);
@endphp
<!DOCTYPE html>
<html lang="{{ $language }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, follow"><title>404 — {{ $text['not_found'] }}</title></head>
<body><main><h1>404</h1><p>{{ $text['not_found'] }}</p><a href="/{{ $language }}/catalog">{{ $text['back_catalog'] }}</a></main></body></html>
