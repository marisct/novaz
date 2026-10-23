<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $service): View
    {
        $user = $request->user();
        $payload = match ($user->role) {
            'teacher' => $service->teacher((int) $user->teacherProfile?->id),
            'student' => $service->student($user->studentProfile),
            default => $service->admin(),
        };

        return view('dashboard', ['payload' => $payload, 'role' => $user->role]);
    }
}
