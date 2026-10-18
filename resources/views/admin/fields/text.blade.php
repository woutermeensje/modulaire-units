<label class="portal-label" for="{{ $field->name }}">{{ $field->label }}</label>
<input class="portal-input" id="{{ $field->name }}" name="{{ $field->name }}" type="text" value="{{ $value }}" @if ($field->placeholder) placeholder="{{ $field->placeholder }}" @endif @required($field->required) @if ($field->name === 'title' || $field->name === 'name') data-slug-source="slug" @endif>
