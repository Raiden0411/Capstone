@component('mail::layout')
@slot('header')
@component('mail::header', ['url' => config('app.url')])
@endcomponent
@endslot

{{ $slot }}

@isset($subcopy)
@slot('subcopy')
@component('mail::subcopy')
{{ $subcopy }}
@endcomponent
@endslot
@endisset

@slot('footer')
@component('mail::footer')
@endcomponent
@endslot
@endcomponent