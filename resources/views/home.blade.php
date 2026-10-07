<!DOCTYPE html>
<html lang="{{ $locale }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('seo')
        @vite('resources/js/app.ts')
    </head>
    <body>
        <div id="app" data-locale="{{ $locale }}" data-messages="{{ json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"
            data-locales="{{ json_encode(array_values(array_intersect(config('lessons.ui_locales'), $publicContent['entry']['locales'] ?? config('lessons.ui_locales'))), JSON_THROW_ON_ERROR) }}"
            data-page="{{ $page ?? 'home' }}"
            data-context="{{ json_encode($context ?? [], JSON_THROW_ON_ERROR) }}"
            data-studio-messages="{{ json_encode($studioMessages ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">
            @if (isset($publicContent) || ($page ?? 'home') === 'home')
                @include('public-content')
            @else
            <noscript>
                <main class="no-script">
                    <h1>{{ $messages['title'] }}</h1>
                    <p>{{ $messages['description'] }}</p>
                    <p>{{ $messages['javascript_required'] ?? '' }}</p>
                    <a href="/ru" lang="ru">Русский</a> · <a href="/de" lang="de">Deutsch</a>
                </main>
            </noscript>
            @endif
        </div>
        @if (! in_array($page ?? 'home', ['home', 'catalog', 'projector', 'student', 'teacher', 'control'], true))
            @include('public-legal-links')
        @endif
    </body>
</html>
