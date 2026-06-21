<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Use Tailwind CSS pagination views
        Paginator::useTailwind();

        // Livewire 3 auto-discovers components in app/Livewire — no manual
        // registration needed. The explicit Livewire::component() call has
        // been removed so this file boots cleanly before Livewire is installed.
    }
}