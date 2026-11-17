<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('dashboard', [
            'loginAudits' => $request->user()
                ->loginAudits()
                ->latest()
                ->paginate(10),
            'serverLabel' => config('laboratorio.server_label'),
            'visitorIp' => $request->ip(),
        ]);
    }
}
