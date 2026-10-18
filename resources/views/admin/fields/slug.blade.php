<label class="portal-label" for="{{ $field->name }}">{{ $field->label }}</label>
<input class="portal-input" id="{{ $field->name }}" name="{{ $field->name }}" type="text" value="{{ $value }}" placeholder="Wordt automatisch ingevuld als je dit leeg laat" @readonly($record->exists && $record instanceof \App\Models\Page && $record->path === '')>
