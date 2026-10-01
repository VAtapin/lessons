lessons.atapin.de
{!! __('mail.tagline', [], $locale) !!}

{!! __('mail.'.$kind.'.title', [], $locale) !!}

{!! __('mail.greeting', ['name' => $recipientName], $locale) !!}

{!! __('mail.'.$kind.'.intro', [], $locale) !!}

{!! __('mail.'.$kind.'.action', [], $locale) !!}:
{!! $actionUrl !!}

{!! __('mail.'.$kind.'.expiry', ['minutes' => $expiresMinutes], $locale) !!}

{!! __('mail.'.$kind.'.ignore', [], $locale) !!}

{!! __('mail.footer', [], $locale) !!}
