<?php

namespace App\Providers;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Temporary: log every Livewire update request hitting the server
        app('events')->listen(RouteMatched::class, function (RouteMatched $event) {
            $uri = $event->request->getRequestUri();

            if (! (str_contains($uri, 'livewire') && str_contains($uri, 'update'))) {
                return;
            }

            $payload = json_decode($event->request->getContent(), true);

            Log::debug('[LW-UPDATE] request received', [
                'uri'        => $uri,
                'user_id'    => $event->request->user()?->id,
                'components' => collect($payload['components'] ?? [])->map(fn ($c) => [
                    'name'  => data_get($c, 'snapshot.memo.name'),
                    'calls' => collect(data_get($c, 'calls', []))->pluck('method')->toArray(),
                ])->toArray(),
            ]);
        });
    }
}
