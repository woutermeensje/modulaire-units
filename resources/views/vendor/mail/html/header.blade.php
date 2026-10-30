@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}">
<img src="{{ asset('images/favicon-180x180.png') }}" class="brand-mark" width="34" height="34" alt="">
<span class="brand-name">{!! $slot !!}</span>
</a>
</td>
</tr>
