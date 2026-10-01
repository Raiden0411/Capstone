@php
    $__siteName = \App\Models\SiteSetting::getValue('site_name', config('app.name'));
@endphp
<tr>
<td class="footer">
<table width="570" cellpadding="0" cellspacing="0" role="presentation" align="center">
<tr>
<td align="center" style="padding: 24px 0 32px;">
<p style="color: #9CA3AF; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 12px; line-height: 1.6; margin: 0 0 6px; text-align: center;">
© {{ date('Y') }} {{ $__siteName }}
</p>
<p style="color: #9CA3AF; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 12px; line-height: 1.6; margin: 0; text-align: center;">
Victorias City, Negros Occidental · Philippines
</p>
</td>
</tr>
</table>
</td>
</tr>