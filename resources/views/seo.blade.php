@php
    $entry = $publicContent['entry'] ?? null;
    $isCatalog = ($page ?? 'home') === 'catalog';
    $public = isset($publicContent) || ($page ?? 'home') === 'home';
    $title = ($entry['title'] ?? ($isCatalog ? $messages['catalog_title'] : $messages['title'])).' — lessons.atapin.de';
    $description = $entry['description'] ?? ($isCatalog ? $messages['catalog_intro'] : $messages['description']);
    $path = $isCatalog ? '/catalog'.($entry ? '/'.$entry['slug'] : '') : '';
    $canonical = url('/'.$locale.$path);
    $image = url(isset($entry['coverUrl']) ? str_replace('/media/builtin/', '/social/builtin/', $entry['coverUrl']).'.jpg' : '/social-cover.jpg');
    $languages = array_values(array_intersect(config('lessons.ui_locales'), $entry['locales'] ?? config('lessons.ui_locales')));
    $breadcrumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => $messages['home'], 'item' => url('/'.$locale)]];
    if ($isCatalog) $breadcrumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $messages['catalog_title'], 'item' => url('/'.$locale.'/catalog')];
    if ($entry) $breadcrumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $entry['title'], 'item' => $canonical];
    $graph = [['@type' => 'WebSite', '@id' => url('/').'#website', 'url' => url('/'), 'name' => 'lessons.atapin.de', 'inLanguage' => config('lessons.ui_locales')], ['@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumbs]];
    if ($entry) $graph[] = ['@type' => 'LearningResource', 'name' => $entry['title'], 'description' => $description, 'url' => $canonical, 'image' => $image, 'inLanguage' => $locale, 'learningResourceType' => 'Lesson', 'timeRequired' => 'PT'.$entry['durationMinutes'].'M'];
    $structuredData = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
@endphp
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $public && !request()->query->count() ? 'index, follow' : 'noindex, follow' }}">
@if ($public)
<link rel="canonical" href="{{ $canonical }}">
@foreach ($languages as $language)
<link rel="alternate" hreflang="{{ $language }}" href="{{ url('/'.$language.$path) }}">
@endforeach
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:alt" content="{{ $entry['title'] ?? $messages['image_alt'] }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="lessons.atapin.de">
<meta property="og:locale" content="{{ str_replace('-', '_', trans('interface.social_locale')) }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">
<script type="application/ld+json">{!! $structuredData !!}</script>
@endif
