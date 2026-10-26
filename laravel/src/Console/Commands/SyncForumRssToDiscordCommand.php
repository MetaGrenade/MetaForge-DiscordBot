<?php

namespace MetaForge\Discord\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use MetaForge\Discord\Jobs\CrossPostForumItemJob;

class SyncForumRssToDiscordCommand extends Command
{
    protected $signature = 'discord:forum-rss-sync {--limit=10 : Maximum new items to queue per run}';

    protected $description = 'Poll the configured forum RSS/Atom feed and queue Discord cross-posts for new entries.';

    public function handle(): int
    {
        if (! config('discord.forum_crosspost_enabled', false)) {
            $this->warn('Forum cross-post is disabled (DISCORD_FORUM_CROSSPOST_ENABLED=false).');

            return self::SUCCESS;
        }

        $feedUrl = (string) config('discord.forum_rss_url', '');
        if ($feedUrl === '') {
            $this->error('Set DISCORD_FORUM_RSS_URL to your MetaForge forum feed URL.');

            return self::FAILURE;
        }

        $response = Http::timeout(20)->get($feedUrl);
        if (! $response->successful()) {
            $this->error('Feed request failed with HTTP '.$response->status());

            return self::FAILURE;
        }

        $xml = @simplexml_load_string($response->body());
        if ($xml === false) {
            $this->error('Could not parse feed XML.');

            return self::FAILURE;
        }

        $items = $this->extractItems($xml);
        $limit = max(1, (int) $this->option('limit'));
        $queued = 0;

        foreach (array_slice($items, 0, $limit) as $item) {
            CrossPostForumItemJob::dispatch($item['payload'], $item['source_key']);
            $queued++;
        }

        $this->info("Queued {$queued} forum RSS cross-post job(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<array{source_key: string, payload: array{title: string, url: string, excerpt?: string|null, board_slug?: string|null}}>
     */
    private function extractItems(\SimpleXMLElement $xml): array
    {
        $results = [];

        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $parsed = $this->parseRssItem($item);
                if ($parsed !== null) {
                    $results[] = $parsed;
                }
            }

            return $results;
        }

        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['atom'])) {
            foreach ($xml->entry as $entry) {
                $parsed = $this->parseAtomEntry($entry, $namespaces);
                if ($parsed !== null) {
                    $results[] = $parsed;
                }
            }
        }

        return $results;
    }

    /**
     * @return array{source_key: string, payload: array{title: string, url: string, excerpt?: string|null, board_slug?: string|null}}|null
     */
    private function parseRssItem(\SimpleXMLElement $item): ?array
    {
        $title = trim((string) ($item->title ?? ''));
        $url = trim((string) ($item->link ?? ''));
        if ($title === '' || $url === '') {
            return null;
        }

        $guid = trim((string) ($item->guid ?? $url));
        $description = trim(strip_tags((string) ($item->description ?? '')));

        return [
            'source_key' => 'forum-rss:'.sha1($guid),
            'payload' => [
                'title' => $title,
                'url' => $url,
                'excerpt' => mb_substr($description, 0, 280),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $namespaces
     * @return array{source_key: string, payload: array{title: string, url: string, excerpt?: string|null, board_slug?: string|null}}|null
     */
    private function parseAtomEntry(\SimpleXMLElement $entry, array $namespaces): ?array
    {
        $title = trim((string) ($entry->title ?? ''));
        $url = '';

        if (isset($entry->link)) {
            foreach ($entry->link as $link) {
                $attributes = $link->attributes();
                if ($attributes !== null && (string) ($attributes['rel'] ?? 'alternate') === 'alternate') {
                    $url = trim((string) ($attributes['href'] ?? ''));
                    break;
                }
            }
        }

        if ($title === '' || $url === '') {
            return null;
        }

        $id = trim((string) ($entry->id ?? $url));
        $summary = trim(strip_tags((string) ($entry->summary ?? $entry->content ?? '')));

        return [
            'source_key' => 'forum-rss:'.sha1($id),
            'payload' => [
                'title' => $title,
                'url' => $url,
                'excerpt' => mb_substr($summary, 0, 280),
            ],
        ];
    }
}
