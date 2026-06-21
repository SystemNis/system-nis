<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * PAGE 1 — Audit Dashboard & Search
     * KPI widgets live at the top; the search + deep-dive panel is
     * handled entirely by the AuditDashboard Livewire component below.
     */
    public function index(): View
    {
        return view('dashboard.index');
    }
}
