<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->query('event'), fn ($q, $event) => $q->where('event', 'like', "%{$event}%"))
            ->when($request->query('user'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->query('from'), fn ($q, $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($request->query('to'), fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name']);
        $events = AuditLog::query()->select('event')->distinct()->orderBy('event')->pluck('event');

        return view('audit.index', compact('logs', 'users', 'events'));
    }
}
