<x-mail::message>
# {{ $submission->typeLabel() }}

Er is een nieuwe {{ strtolower($submission->typeLabel()) }} binnengekomen via de website.

**Naam:** {{ $submission->name }}<br>
**E-mail:** {{ $submission->email }}<br>
@if ($submission->phone)
**Telefoon:** {{ $submission->phone }}<br>
@endif
@foreach (collect($submission->data)->except(['cv_path', 'cv_name']) as $label => $value)
**{{ ucfirst(str_replace('_', ' ', $label)) }}:** {{ is_array($value) ? implode(', ', $value) : $value }}<br>
@endforeach

@if ($submission->items)
<x-mail::table>
| Item | Aantal |
| :--- | -----: |
@foreach ($submission->items as $item)
| [{{ $item['title'] }}]({{ $item['url'] }}) | {{ $item['quantity'] }} |
@endforeach
</x-mail::table>
@endif

@if ($submission->message)
**Bericht:**

{{ $submission->message }}
@endif

@if ($submission->source_url)
<small>Verstuurd vanaf {{ $submission->source_url }}</small>
@endif

<x-mail::button :url="route('admin.submissions.show', $submission)">
Bekijk in beheer
</x-mail::button>
</x-mail::message>
