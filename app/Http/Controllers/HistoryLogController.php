<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HistoryLogController extends Controller
{
    public function index(): View
    {
        return view('history.index');
    }
}
