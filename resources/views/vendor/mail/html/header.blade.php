@props(['url'])
@php
    $__logoPath = \App\Models\SiteSetting::getValue('site_logo');
    $__logoUrl  = $__logoPath ? url('/storage/' . ltrim($__logoPath, '/')) : null;
    $__siteName = \App\Models\SiteSetting::getValue('site_name', config('app.name'));
@endphp
<tr>
<td class="header">
<table width="570" cellpadding="0" cellspacing="0" role="presentation" align="center">
<tr>
<td align="center" style="padding: 32px 0 8px;">
<a href="{{ $url ?? config('app.url') }}" style="display: inline-block; text-decoration: none; text-align: center;">
@if($__logoUrl)
<img src="{{ $__logoUrl }}" alt="{{ $__siteName }}" width="40" height="40" style="display: block; margin: 0 auto 10px; border-radius: 9999px;">
@endif
<span style="display: block; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 11px; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: #d97706; line-height: 1;">
{{ $__siteName }}
</span>
</a>
</td>
</tr>
</table>
</td>
</tr>