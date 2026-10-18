<label class="portal-label" for="{{ $field->name }}">{{ $field->label }}</label>
<input class="portal-input" id="{{ $field->name }}" name="{{ $field->name }}" type="number" value="{{ $value ?? 0 }}">
