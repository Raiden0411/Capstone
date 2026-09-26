{{--
    Safe SVG emitter.

    Runs the raw SVG string through SvgSanitizerService and injects the
    requested class onto the root <svg> element. Use in place of every
    `{!! str_replace('<svg ', ..., $iconSvg) !!}` and `{!! $iconSvg !!}`
    site that renders a user-uploaded or DB-stored SVG.

    Props:
      :svg    (string|null)  Raw SVG markup.
      class   (string)       Class string to merge onto the root <svg>.
--}}
@props([
    'svg'   => null,
    'class' => '',
])

@php
    $safeSvg = $svg
        ? app(\App\Services\SvgSanitizerService::class)->sanitize((string) $svg, (string) $class)
        : '';
@endphp

@if($safeSvg !== '')
    {!! $safeSvg !!}
@endif
