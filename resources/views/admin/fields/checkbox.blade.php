<span class="portal-label">&nbsp;</span>
<label class="portal-checkbox">
    <input type="hidden" name="{{ $field->name }}" value="0">
    <input type="checkbox" name="{{ $field->name }}" value="1" @checked($value ?? ($field->name === 'is_visible'))>
    {{ $field->label }}
</label>
