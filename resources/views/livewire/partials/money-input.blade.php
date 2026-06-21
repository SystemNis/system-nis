{{--
    Partial: money-input
    A Rupiah amount field with a "JT" (juta/million) multiplier toggle.

    Usage:
        @include('livewire.partials.money-input', [
            'wireModel'   => 'add_actual_amount',   // the Livewire property name (string)
            'label'       => 'Actual Amount Paid (Rp)',
            'placeholder' => '100000000',
            'errorKey'    => 'add_actual_amount',    // optional, defaults to wireModel
            'live'        => true,                   // optional, adds .live to wire:model for instant previews
            'inputId'     => 'add-amount-input',      // optional, sets id="" on the <input> for autofocus targeting
            'autofocus'   => true,                    // optional, focuses + selects the input once Alpine mounts
        ])

    How it works:
        - Wraps the input in x-data="moneyInput(@entangle('{{ wireModel }}'))"
          (moneyInput is registered globally in layouts/app.blade.php)
        - The visible <input> is bound to Alpine's `display`, NOT directly
          to the Livewire property — `real` (which IS entangled) only
          updates through onInput(), where the ×1,000,000 math happens.
        - Toggling "JT" converts between the two representations in place
          so the user never loses what they typed.
--}}
@php
    $errorKey  = $errorKey ?? $wireModel;
    $live      = $live ?? false;
    $autofocus = $autofocus ?? false;
@endphp

<div x-data="moneyInput(@entangle($wireModel){{ $live ? '.live' : '' }})"
     @if($autofocus) x-init="$nextTick(() => { $refs.moneyInputField.focus(); $refs.moneyInputField.select(); })" @endif>
    <div class="flex items-center justify-between mb-1">
        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-widest">
            {{ $label }}
        </label>
        <button type="button"
                @click="toggleJt()"
                :class="jt
                    ? 'bg-[#0F1F3D] text-white border-[#0F1F3D]'
                    : 'bg-white text-slate-400 border-slate-200 hover:border-slate-300'"
                class="text-[10px] font-bold px-2 py-0.5 rounded-md border transition-colors leading-none"
                title="Toggle million (juta) multiplier — type 4.5 instead of 4500000">
                JT
        </button>
    </div>

    <div class="relative">
        <input type="text"
               @if(isset($inputId)) id="{{ $inputId }}" @endif
               x-ref="moneyInputField"
               inputmode="decimal"
               x-model="display"
               @input="onInput($event.target.value)"
               placeholder="{{ $placeholder ?? '' }}"
               class="form-input font-num pr-12">
        <span x-show="jt"
              x-cloak
              class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded">
            ×1JT
        </span>
    </div>

    {{-- Live readout of the real Rupiah value while JT is active --}}
    <p x-show="jt && real" x-cloak class="mt-1 text-[10px] text-slate-400 font-num">
        = Rp <span x-text="real ? Number(real).toLocaleString('id-ID') : 0"></span>
    </p>

    @error($errorKey)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>