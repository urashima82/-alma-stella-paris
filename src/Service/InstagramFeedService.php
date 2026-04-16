<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class InstagramFeedService
{
    private const string API_URL = 'https://feeds.behold.so/';
    private const int CACHE_TTL_SECONDS = 21600; // 6 hours
    private const int MAX_POSTS = 6;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $beholdFeedId,
    ) {
    }

    /**
     * @return list<array{
     *     id: string,
     *     permalink: string,
     *     imageUrl: string,
     *     caption: string,
     *     mediaType: string,
     * }>
     */
    public function getLatestPosts(int $limit = self::MAX_POSTS): array
    {
        if ($this->beholdFeedId === '') {
            return [];
        }

        $posts = $this->fetchFeed();

        return \array_slice($posts, 0, $limit);
    }

    /**
     * @return list<array{
     *     id: string,
     *     permalink: string,
     *     imageUrl: string,
     *     caption: string,
     *     mediaType: string,
     * }>
     */
    private function fetchFeed(): array
    {
        return $this->cache->get('instagram_feed_posts', function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            try {
                $response = $this->httpClient->request('GET', self::API_URL.$this->beholdFeedId);
                $data = $response->toArray();

                $rawPosts = $data['posts'] ?? [];
                $posts = [];

                foreach ($rawPosts as $raw) {
                    $imageUrl = $this->resolveImageUrl($raw);

                    if ($imageUrl === null) {
                        continue;
                    }

                    $posts[] = [
                        'id' => (string) ($raw['id'] ?? ''),
                        'permalink' => (string) ($raw['permalink'] ?? ''),
                        'imageUrl' => $imageUrl,
                        'caption' => (string) ($raw['prunedCaption'] ?? $raw['caption'] ?? ''),
                        'mediaType' => (string) ($raw['mediaType'] ?? 'IMAGE'),
                    ];
                }

                return $posts;
            } catch (\Throwable) {
                $item->expiresAfter(300); // Retry in 5 minutes on failure

                return [];
            }
        });
    }

    /**
     * Resolve the best image URL for a post.
     * Prefers medium size for performance, falls back to thumbnailUrl or mediaUrl.
     *
     * @param array<string, mixed> $post
     */
    private function resolveImageUrl(array $post): ?string
    {
        // Prefer sized versions (served via Behold CDN, optimized)
        $sizes = $post['sizes'] ?? [];
        foreach (['medium', 'small', 'large'] as $size) {
            if (isset($sizes[$size]['mediaUrl']) && $sizes[$size]['mediaUrl'] !== '') {
                return (string) $sizes[$size]['mediaUrl'];
            }
        }

        // For videos, use the thumbnail
        if (isset($post['thumbnailUrl']) && $post['thumbnailUrl'] !== '') {
            return (string) $post['thumbnailUrl'];
        }

        // Last resort: direct media URL
        if (isset($post['mediaUrl']) && $post['mediaUrl'] !== '') {
            return (string) $post['mediaUrl'];
        }

        return null;
    }
}
