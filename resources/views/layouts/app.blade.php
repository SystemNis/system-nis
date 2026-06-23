<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'NIS Audit System')</title>

    {{-- Tailwind CSS via CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        // NIS brand palette — deep navy authority + amber accent
                        navy:  { DEFAULT: '#0F1F3D', 50: '#E8ECF4', 100: '#C5CFE3', 200: '#8A9FC7', 300: '#4F6FAB', 400: '#2D4F8E', DEFAULT: '#0F1F3D', 600: '#0C1A33', 700: '#091429', 800: '#06101F', 900: '#030A14' },
                        amber: { DEFAULT: '#E8A020', light: '#F5C96A', dark: '#B87A10' },
                        slate: { 50: '#F8FAFC', 100: '#F1F5F9', 200: '#E2E8F0', 300: '#CBD5E1', 400: '#94A3B8', 500: '#64748B', 600: '#475569', 700: '#334155', 800: '#1E293B', 900: '#0F172A' },
                    },
                    fontFamily: {
                        sans:  ['"Inter"', 'system-ui', 'sans-serif'],
                        mono:  ['"JetBrains Mono"', 'monospace'],
                        display: ['"Plus Jakarta Sans"', '"Inter"', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    {{-- Livewire Styles --}}
    @livewireStyles

    <style>
        /* Base reset */
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; }

        /* Scrollbar styling */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #F1F5F9; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94A3B8; }

        /* Number cells use monospace for alignment */
        .font-num { font-family: 'JetBrains Mono', monospace; }

        /* Alpine.js: hide [x-cloak] elements until Alpine has initialized,
           preventing a flash of unstyled/incorrectly-visible content. */
        [x-cloak] { display: none !important; }

        /* Table header sticky */
        .table-header-sticky thead th { position: sticky; top: 0; z-index: 10; }

        /* Status badge base */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 10px; border-radius: 9999px;
            font-size: 0.72rem; font-weight: 600; letter-spacing: 0.03em;
            white-space: nowrap;
        }

        /* Modal backdrop */
        .modal-backdrop {
            position: fixed; inset: 0; background: rgba(15,31,61,0.55);
            backdrop-filter: blur(3px); z-index: 50;
            display: flex; align-items: center; justify-content: center; padding: 1rem;
        }

        /* Animated entrance for modals */
        @keyframes modal-in {
            from { opacity: 0; transform: translateY(-12px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-card { animation: modal-in 0.18s ease-out; }

        /* Form inputs inside modals */
        .form-input {
            width: 100%;
            padding: 0.45rem 0.65rem;
            font-size: 0.8rem;
            border: 1px solid #E2E8F0;
            border-radius: 0.5rem;
            background: #fff;
            color: #1E293B;
            transition: border-color 0.15s, box-shadow 0.15s;
            outline: none;
        }
        .form-input:focus {
            border-color: #0F1F3D;
            box-shadow: 0 0 0 3px rgba(15,31,61,0.12);
        }
        .form-input.uppercase { text-transform: uppercase; }
        .form-input.font-num  { font-family: 'JetBrains Mono', monospace; }
        .form-error {
            color: #E11D48;
            font-size: 0.7rem;
            margin-top: 0.2rem;
        }

        /* Kavling meter */
        .kavling-bar-track { background:#E2E8F0; border-radius:9999px; height:5px; overflow:hidden; }
        .kavling-bar-fill  { height:100%; border-radius:9999px; transition: width 0.3s ease; }
    </style>
</head>

<body class="h-full bg-slate-100 text-slate-800">

    {{-- ── Top Navigation Bar ──────────────────────────────────────────── --}}
    <header class="bg-[#0F1F3D] shadow-lg sticky top-0 z-30">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">

                {{-- Logo / Brand --}}
                <div class="flex items-center gap-3">
                    {{-- NIS monogram --}}
                    <div class="w-8 h-8 rounded bg-amber-400 flex items-center justify-center flex-shrink-0">
                        <span class="text-[#0F1F3D] font-bold text-xs tracking-tight leading-none" style="font-family:'Plus Jakarta Sans',sans-serif">NIS</span>
                    </div>
                    <div class="leading-tight">
                        <p class="text-white font-semibold text-sm tracking-wide" style="font-family:'Plus Jakarta Sans',sans-serif">NIS Audit System</p>
                        <p class="text-slate-400 text-[10px] tracking-widest uppercase">Real Estate Financial Audit</p>
                    </div>
                </div>

                {{-- Navigation Links --}}
                <nav class="flex items-center gap-1">
                    <a href="{{ route('dashboard') }}"
                       class="px-3 py-1.5 rounded text-sm font-medium transition-colors
                              {{ request()->routeIs('dashboard')
                                 ? 'bg-amber-400 text-[#0F1F3D]'
                                 : 'text-slate-300 hover:text-white hover:bg-white/10' }}">
                        📊 Dashboard
                    </a>
                    <a href="{{ route('reconciliation.index') }}"
                       class="px-3 py-1.5 rounded text-sm font-medium transition-colors
                              {{ request()->routeIs('reconciliation.*')
                                 ? 'bg-amber-400 text-[#0F1F3D]'
                                 : 'text-slate-300 hover:text-white hover:bg-white/10' }}">
                        📋 Reconciliation
                    </a>
                    <a href="{{ route('history.index') }}"
                       class="px-3 py-1.5 rounded text-sm font-medium transition-colors
                              {{ request()->routeIs('history.*')
                                 ? 'bg-amber-400 text-[#0F1F3D]'
                                 : 'text-slate-300 hover:text-white hover:bg-white/10' }}">
                        🗂 History
                    </a>
                </nav>
            </div>
        </div>
    </header>

    {{-- ── Flash Messages ───────────────────────────────────────────────── --}}
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="fixed top-16 right-4 z-50 bg-emerald-600 text-white text-sm font-medium px-4 py-3 rounded-lg shadow-lg flex items-start gap-2 max-w-md">
            <span class="text-base flex-shrink-0">✅</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 10000)"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="fixed top-16 right-4 z-50 bg-rose-600 text-white text-sm font-medium px-4 py-3 rounded-lg shadow-lg flex items-start gap-2 max-w-md">
            <span class="text-base flex-shrink-0">❌</span>
            <div>
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Page Content ─────────────────────────────────────────────────── --}}
    <main class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @yield('content')
    </main>

    {{-- ── Footer ──────────────────────────────────────────────────────── --}}
    <footer class="border-t border-slate-200 mt-12 py-4 text-center text-xs text-slate-400">
        NIS Financial Audit System &mdash; Internal Use Only &mdash; {{ now()->year }}
    </footer>

    {{-- Livewire Scripts --}}
    {{-- ════════════════════════════════════════════════════════════════
         GLOBAL ALPINE COMPONENT: moneyInput (the "JT" million-multiplier)
         ════════════════════════════════════════════════════════════════
         Drop-in helper for any Rupiah field. When the JT toggle is ON,
         what the user TYPES is treated as millions: typing "4.5" writes
         4500000 into the entangled Livewire property. Toggling JT off
         reverts to typing the raw integer directly.
         Usage in Blade — wrap a wire:model input:
           <div x-data="moneyInput(@entangle('add_actual_amount'))"> ... </div>
         See livewire/partials/money-input.blade.php for the markup partial.
    --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('moneyInput', (livewireValue) => ({
                // Two-way bound to the Livewire property (the real Rupiah integer)
                real: livewireValue,
                // JT toggle state — NOT persisted, resets per page load (by design)
                jt: false,
                // What the user sees/types in the input when JT is on
                display: '',

                init() {
                    // Keep `display` in sync if `real` changes from outside
                    // (e.g. modal reopened with a pre-filled value)
                    this.$watch('real', (val) => {
                        if (this.jt && val) {
                            // Reflect the million-equivalent back into display
                            // only if it's not actively being typed (avoid fighting the user)
                        }
                    });
                },

                toggleJt() {
                    this.jt = !this.jt;
                    if (this.jt) {
                        // Switching ON: pre-fill display with current value ÷ 1,000,000
                        this.display = this.real ? String(this.real / 1000000) : '';
                    } else {
                        // Switching OFF: pre-fill display with the raw integer
                        this.display = this.real ? String(this.real) : '';
                    }
                },

                onInput(value) {
                    this.display = value;
                    if (value === '' || isNaN(value)) {
                        this.real = '';
                        return;
                    }
                    const n = parseFloat(value);
                    this.real = this.jt ? Math.round(n * 1000000) : Math.round(n);
                },
            }));
        });
    </script>

    @livewireScripts

    {{-- Alpine.js (Livewire 3 ships it, but explicit for clarity) --}}
    {{-- @alpineVersion is bundled with Livewire 3 --}}

</body>
</html>