<?php

namespace MetaForge\Discord\Support;

/**
 * Env-driven observability hooks. No OpenTelemetry SDK dependency — host apps may
 * bridge these calls to OTEL or other metrics backends when enabled.
 */
class DiscordObservability
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function recordCounter(string $name, array $attributes = [], int $delta = 1): void
    {
        if (! config('discord.observability.metrics_enabled', false)) {
            return;
        }

        DiscordStructuredLog::info('discord.metric', [
            'metric' => $name,
            'delta' => $delta,
            'attributes' => $attributes,
            'otel_service_name' => config('discord.observability.otel_service_name'),
            'otel_exporter_otlp_endpoint' => config('discord.observability.otel_exporter_otlp_endpoint'),
        ]);
    }
}
