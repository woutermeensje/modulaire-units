<label class="portal-label" for="{{ $field->name }}">{{ $field->label }}</label>
<textarea class="portal-textarea" id="{{ $field->name }}" name="{{ $field->name }}" rows="{{ $field->rows }}" data-quill="off">{{ $value }}</textarea>
