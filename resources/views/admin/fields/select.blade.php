<label class="portal-label" for="{{ $field->name }}">{{ $field->label }}</label>
<select class="portal-input" id="{{ $field->name }}" name="{{ $field->name }}">
    @foreach ($field->options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
    @endforeach
</select>
