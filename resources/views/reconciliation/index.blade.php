@extends('layouts.app')

@section('title', 'Master Reconciliation Sheet — NIS Audit')

@section('content')

{{-- Page header --}}
<div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
    <div>
        <h1 class="text-2xl font-bold text-[#0F1F3D] tracking-tight" style="font-family:'Plus Jakarta Sans',sans-serif">
            Master Reconciliation Sheet
        </h1>
        <p class="text-slate-500 text-sm mt-0.5">
            All registered housing units — live audit view. Every field is editable.
        </p>
    </div>
    {{-- Breadcrumb-style badge --}}
    <div class="flex items-center gap-2 text-xs text-slate-400 bg-slate-200 rounded-full px-3 py-1.5 self-start mt-1">
        <span>NIS</span>
        <span class="text-slate-300">/</span>
        <span class="text-slate-600 font-medium">Reconciliation</span>
    </div>
</div>

{{-- Livewire component --}}
@livewire('reconciliation-table')

@endsection
