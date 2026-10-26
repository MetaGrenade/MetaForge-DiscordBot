<?php

namespace MetaForge\Discord\Listeners;

use MetaForge\Discord\Jobs\CrossPostForumItemJob;

class QueueForumDiscordCrosspost
{
    public function handle(object $event): void
    {
        if (! config('discord.forum_crosspost_enabled', false)) {
            return;
        }

        if ((string) config('discord.forum_crosspost_mode', 'new_threads') === 'new_threads'
            && ! $this->isInitialThreadPost($event)) {
            return;
        }

        $item = $this->resolveItem($event);
        if ($item === null) {
            return;
        }

        $sourceKey = $this->resolveSourceKey($event, $item);
        CrossPostForumItemJob::dispatch($item, $sourceKey);
    }

    private function isInitialThreadPost(object $event): bool
    {
        if (method_exists($event, 'isInitialThreadPost')) {
            return (bool) $event->isInitialThreadPost();
        }

        if (! method_exists($event, 'thread') || ! method_exists($event, 'post')) {
            return true;
        }

        $thread = $event->thread();
        $post = $event->post();

        if (! is_object($thread) || ! is_object($post)) {
            return true;
        }

        if (isset($thread->first_post_id) && isset($post->id)) {
            return (int) $thread->first_post_id === (int) $post->id;
        }

        if (method_exists($thread, 'posts')) {
            return $thread->posts()->count() <= 1;
        }

        return true;
    }

    /**
     * @return array{title: string, url: string, excerpt?: string|null, board_slug?: string|null}|null
     */
    private function resolveItem(object $event): ?array
    {
        if (method_exists($event, 'toDiscordForumCrosspost')) {
            /** @var array{title?: string, url?: string, excerpt?: string|null, board_slug?: string|null} $data */
            $data = $event->toDiscordForumCrosspost();

            if (! isset($data['title'], $data['url'])) {
                return null;
            }

            return [
                'title' => (string) $data['title'],
                'url' => (string) $data['url'],
                'excerpt' => isset($data['excerpt']) ? (string) $data['excerpt'] : null,
                'board_slug' => isset($data['board_slug']) ? (string) $data['board_slug'] : null,
            ];
        }

        $thread = method_exists($event, 'thread') ? $event->thread() : ($event->thread ?? null);
        $post = method_exists($event, 'post') ? $event->post() : ($event->post ?? null);

        if (! is_object($thread)) {
            return null;
        }

        $title = isset($thread->title) ? (string) $thread->title : 'Forum thread';
        $boardSlug = null;

        if (isset($thread->board) && is_object($thread->board) && isset($thread->board->slug)) {
            $boardSlug = (string) $thread->board->slug;
        } elseif (method_exists($thread, 'board') && is_object($thread->board()) && isset($thread->board()->slug)) {
            $boardSlug = (string) $thread->board()->slug;
        }

        $url = $this->resolveThreadUrl($thread, $post);
        if ($url === null) {
            return null;
        }

        $excerpt = null;
        if (is_object($post) && isset($post->body) && is_string($post->body)) {
            $excerpt = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($post->body)) ?? ''), 0, 280);
        }

        return [
            'title' => $title,
            'url' => $url,
            'excerpt' => $excerpt,
            'board_slug' => $boardSlug,
        ];
    }

    private function resolveThreadUrl(object $thread, ?object $post): ?string
    {
        if (method_exists($thread, 'url')) {
            $url = $thread->url();
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        $template = (string) config('discord.forum_crosspost_url_template', '');
        if ($template === '') {
            return null;
        }

        $boardSlug = 'board';
        if (isset($thread->board) && is_object($thread->board) && isset($thread->board->slug)) {
            $boardSlug = (string) $thread->board->slug;
        }

        $threadSlug = isset($thread->slug) ? (string) $thread->slug : (string) ($thread->id ?? '');

        return str_replace(
            ['{board}', '{thread}', '{thread_id}'],
            [$boardSlug, $threadSlug, (string) ($thread->id ?? '')],
            $template,
        );
    }

    /**
     * @param  array{title: string, url: string, excerpt?: string|null, board_slug?: string|null}  $item
     */
    private function resolveSourceKey(object $event, array $item): string
    {
        if (method_exists($event, 'post') && is_object($event->post()) && isset($event->post()->id)) {
            return 'forum-post:'.(string) $event->post()->id;
        }

        if (isset($event->post) && is_object($event->post) && isset($event->post->id)) {
            return 'forum-post:'.(string) $event->post->id;
        }

        return 'forum-url:'.sha1($item['url']);
    }
}
