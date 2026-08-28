@props(['url'])
<tr>
<td class="header">
{{-- A wordmark rather than an image: remote images are blocked by default in
     most clients, so a logo would show as a broken box to many recipients. --}}
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<span style="font-size: 20px; font-weight: 700; letter-spacing: 0.02em; color: #255325;">AHAIC</span>
<span style="display: block; margin-top: 2px; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #0a9fa5;">Partner Portal</span>
</a>
</td>
</tr>
