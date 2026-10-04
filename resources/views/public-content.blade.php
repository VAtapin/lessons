@php
    $lesson = $publicContent['entry'] ?? null;
    $path = ($page ?? 'home') === 'catalog' ? '/catalog'.($lesson ? '/'.$lesson['slug'] : '') : '';
    $languages = array_intersect(config('lessons.ui_locales'), $lesson['locales'] ?? config('lessons.ui_locales'));
@endphp
<div class="public-site">
    <header class="public-header">
        <a class="public-brand" href="/{{ $locale }}">lessons.atapin.de</a>
        <nav aria-label="{{ $messages['home'] }}"><a href="/{{ $locale }}">{{ $messages['home'] }}</a> · <a href="/{{ $locale }}/catalog">{{ $messages['catalog_title'] }}</a></nav>
        <nav class="public-languages" aria-label="{{ $messages['language'] }}">
            @foreach ($languages as $language)<a href="/{{ $language }}{{ $path }}" lang="{{ $language }}">{{ strtoupper($language) }}</a>@endforeach
        </nav>
    </header>
    <main class="public-content" id="main-content">
        @if (($page ?? 'home') === 'home')
            <section><h1>{{ $messages['title'] }}</h1><p>{{ $messages['description'] }}</p><a class="public-button" href="/{{ $locale }}/catalog">{{ $messages['open_catalog'] }}</a></section>
            @foreach (['audiences', 'topics', 'steps', 'formats'] as $section)
                <section><h2>{{ $messages[$section.'_title'] }}</h2><p>{{ $messages[$section.'_intro'] }}</p></section>
            @endforeach
            <section><h2>{{ $messages['nav_about'] }}</h2><p>{{ $messages['about_text'] }}</p></section>
        @elseif (isset($publicContent['entry']))
            @php($lesson = $publicContent['entry'])
            <nav class="catalog-breadcrumb" aria-label="{{ $messages['home'] }}"><a href="/{{ $locale }}">{{ $messages['home'] }}</a> → <a href="/{{ $locale }}/catalog">{{ $messages['catalog_title'] }}</a> → <span>{{ $lesson['title'] }}</span></nav>
            <article class="catalog-detail">
                <h1>{{ $lesson['title'] }}</h1><p>{{ $lesson['description'] }}</p>
                @if ($lesson['coverUrl'])<img src="{{ $lesson['coverUrl'] }}" alt="{{ $lesson['title'] }}" width="{{ $lesson['coverWidth'] }}" height="{{ $lesson['coverHeight'] }}" style="max-width:100%;height:auto" fetchpriority="high">@endif
                <p>{{ $messages['filter_duration'] }}: {{ $lesson['durationMinutes'] }} {{ $messages['minutes'] }}</p>
                @foreach (['age', 'topic', 'audience', 'format'] as $field)
                    <p>{{ $messages['filter_'.$field] }}: @foreach ($lesson[$field] as $value){{ $messages[$field.'_'.$value] ?? $value }}@if (!$loop->last), @endif @endforeach</p>
                @endforeach
                @if ($lesson['details'])
                    @foreach (['goals' => 'lesson_goals', 'materials' => 'lesson_materials'] as $field => $label)
                        <section><h2>{{ $messages[$label] }}</h2><ul>@foreach ($lesson['details'][$field] as $text)<li>{{ $text }}</li>@endforeach</ul></section>
                    @endforeach
                    <p>{{ $lesson['details']['devices'] }}</p><p>{{ $lesson['details']['conditions'] }}</p>
                @endif
                <section><h2>{{ $messages['lesson_content'] }}</h2><ol>@foreach ($lesson['stages'] as $stage)<li>{{ $stage['title'] }}</li>@endforeach</ol></section>
                @if ($lesson['documentation']['plan'] ?? null)
                    <section><h2>{{ $studioMessages['documentation_title'] }}</h2>
                    <div class="documentation-plan-text">{!! $lesson['documentation']['planHtml'] !!}</div>
                    @foreach ($lesson['documentation']['files'] as $file)<a href="{{ $file['url'] ?? '/lesson-files/'.$file['fileId'] }}">{{ $file['label'] ?? $studioMessages['documentation_'.$file['kind']] }} ({{ strtoupper($file['locale']) }})</a> @endforeach
                    </section>
                @endif
                <noscript><p>{{ $messages['javascript_required'] }}</p></noscript>
            </article>
        @else
            <h1>{{ $messages['catalog_title'] }}</h1><p>{{ $messages['catalog_intro'] }}</p>
            <div class="catalog-results">
                @foreach ($publicContent['entries'] as $lesson)
                    <article class="catalog-card"><a href="/{{ $locale }}/catalog/{{ $lesson['slug'] }}">
                        @if ($lesson['coverUrl'])<img src="{{ $lesson['coverUrl'] }}" alt="{{ $lesson['title'] }}" width="{{ $lesson['coverWidth'] }}" height="{{ $lesson['coverHeight'] }}" loading="lazy">@endif
                        <div><h2>{{ $lesson['title'] }}</h2><p>{{ $lesson['description'] }}</p><p>{{ implode(', ', $lesson['age']) }} · {{ $lesson['durationMinutes'] }} {{ $messages['minutes'] }}</p></div>
                    </a>
                    @if (isset($lesson['downloads']))
                        <div class="catalog-material"><p>{{ implode(' / ', array_map('strtoupper', $lesson['locales'])) }}</p>
                        @foreach ($lesson['downloads'] as $file)<a class="public-button" href="{{ $file['url'] }}" download>{{ $messages['download_material'] }} {{ $file['extension'] }}</a> @endforeach
                        <a class="card-detail-link" href="/{{ $locale }}/catalog/{{ $lesson['slug'] }}">{{ $messages['related_lesson'] }}</a></div>
                    @endif
                    </article>
                @endforeach
            </div>
            @if ($publicContent['pagination']['lastPage'] > 1)
                <nav aria-label="{{ $messages['page'] }}">@for ($number = 1; $number <= $publicContent['pagination']['lastPage']; $number++)<a href="{{ request()->fullUrlWithQuery(['page' => $number]) }}">{{ $number }}</a> @endfor</nav>
            @endif
        @endif
    </main>
    <footer class="public-footer"><a href="/{{ $locale }}/catalog">{{ $messages['open_catalog'] }}</a><p>{{ $messages['about_text'] }}</p></footer>
</div>
