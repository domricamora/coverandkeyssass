<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'tenantCount' => Tenant::query()->count(),
            'activeTenants' => Tenant::query()->where('status', 'active')->count(),
            'userCount' => User::query()->count(),
            'recentTenants' => Tenant::query()->latest()->take(5)->get(),
            'recentAudit' => AuditLog::query()->with('user:id,name')->latest()->take(8)->get(),
        ]);
    }
}
