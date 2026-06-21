{{--
    Partial: form-field
    Usage: @include('livewire.partials.form-field', [
        'label'     => 'Field Label',
        'errorKey'  => 'wire_model_name',
        'inputHtml' => '<input ...>',
    ])
--}}
<div>
    <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest mb-1">
        {{ $label }}
    </label>
    {!! $inputHtml !!}
    @error($errorKey)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
