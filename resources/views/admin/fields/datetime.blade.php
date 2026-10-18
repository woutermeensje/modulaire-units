@php($dateValue = $value instanceof \DateTimeInterface ? $value->format('Y-m-d\TH:i') : ($value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i') : ''))
<label class="portal-label" for="{{ $field->name }}">{{ $field->label }}</label>
<input class="portal-input" id="{{ $field->name }}" name="{{ $field->name }}" type="datetime-local" value="{{ $dateValue }}">
