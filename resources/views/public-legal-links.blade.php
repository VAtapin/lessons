@php($legalLinks = config('public-site.links.'.$locale))
<nav class="public-legal-links" aria-label="{{ trans('interface.legal_navigation', [], $locale) }}">
    <a href="{{ $legalLinks['supportUrl'] }}" rel="noreferrer">{{ trans('interface.contact_support', [], $locale) }}</a>
    <a href="{{ $legalLinks['imprintUrl'] }}" rel="noreferrer">{{ trans('interface.legal_imprint', [], $locale) }}</a>
    <a href="{{ $legalLinks['privacyUrl'] }}" rel="noreferrer">{{ trans('interface.legal_privacy', [], $locale) }}</a>
</nav>
