<label class="portal-label" for="{{ $field->name }}">{{ $field->label }}</label>
<textarea class="portal-textarea code-input" id="{{ $field->name }}" name="{{ $field->name }}" rows="{{ $field->rows }}" data-quill="off" spellcheck="false">{{ $value }}</textarea>
