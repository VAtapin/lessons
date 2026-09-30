<!DOCTYPE html>
<html lang="{{ $locale }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="{{ $messages['description'] }}">
        <title>lessons.atapin.de — {{ $messages['tagline'] }}</title>
        @vite('resources/js/app.ts')
    </head>
    <body>
        <div id="app" data-locale="{{ $locale }}" data-messages="{{ json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"
            data-page="{{ $page ?? 'home' }}"
            data-context="{{ json_encode($context ?? [], JSON_THROW_ON_ERROR) }}"
            data-studio-messages="{{ json_encode($studioMessages ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">
            <noscript>
                <main class="no-script">
                    <h1>{{ $messages['title'] }}</h1>
                    <p>{{ $messages['status'] }}</p>
                    <p>{{ $messages['coming_soon'] }}</p>
                    <a href="/ru" lang="ru">Русский</a> · <a href="/de" lang="de">Deutsch</a>
                </main>
            </noscript>
        </div>
    </body>
</html>
