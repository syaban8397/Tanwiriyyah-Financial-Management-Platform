<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DomainEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RealtimeController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        $cursor = $this->cursor($request);

        return response()->stream(function () use ($user, $cursor) {
            $events = DomainEvent::query()->where('id', '>', $cursor)->orderBy('id')->limit(50)->get()
                ->filter(fn (DomainEvent $event) => $user->canSeeEventUnit($event->unit_id ? (int) $event->unit_id : null));

            if ($events->isEmpty()) {
                echo 'id: '.$cursor."\n";
                echo "event: heartbeat\n";
                echo "data: {}\n\n";

                return;
            }

            foreach ($events as $event) {
                echo 'id: '.$event->id."\n";
                echo 'data: '.json_encode([
                    'id' => $event->id,
                    'type' => $event->type,
                    'payload' => $event->payload,
                ])."\n\n";
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function cursor(Request $request): int
    {
        $header = $request->headers->get('Last-Event-ID');

        if ($header !== null && $header !== '') {
            return (int) $header;
        }

        if ($request->exists('after')) {
            return (int) $request->query('after');
        }

        return (int) DomainEvent::query()->max('id');
    }
}
