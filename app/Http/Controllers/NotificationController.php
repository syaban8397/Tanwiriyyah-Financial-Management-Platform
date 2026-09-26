<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Notifications/Index', [
            'items' => $request->user()->notifications()->latest()->paginate(20)->through(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? '',
                'body' => $notification->data['body'] ?? '',
                'href' => $notification->data['href'] ?? '/notifications',
                'kind' => $notification->data['kind'] ?? 'system',
                'read' => $notification->read_at !== null,
                'at' => $notification->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return redirect($notification->data['href'] ?? '/notifications');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Semua notifikasi ditandai dibaca.');
    }
}
