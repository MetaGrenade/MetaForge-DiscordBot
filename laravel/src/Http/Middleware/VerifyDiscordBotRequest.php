<?php

namespace MetaForge\Discord\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDiscordBotRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('discord.bot_shared_secret');

        if ($secret === '') {
            abort(503, 'Discord bot webhook secret is not configured.');
        }

        $signature = $request->header('X-Discord-Bot-Signature');
        $timestamp = $request->header('X-Discord-Bot-Timestamp');

        if (! is_string($signature) || ! is_string($timestamp)) {
            abort(401, 'Missing bot signature headers.');
        }

        $skew = (int) config('discord.webhook_max_skew_seconds');
        if (abs(time() - (int) $timestamp) > $skew) {
            abort(401, 'Bot request timestamp is outside the allowed window.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid bot signature.');
        }

        return $next($request);
    }
}
