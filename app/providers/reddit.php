<?php

require_once __DIR__ . '/base.php';

class ProviderReddit extends ProviderBase {

    public function fetch(int $providerId, array $keywords, string $endpoint, int $ttl, $apiKey, ?string $providerName = null, ?string $providerType = null, ?string $host = null): array {

        $providerName = $providerName ?? 'Unknown';
        $providerType = $providerType ?? 'reddit';
        $topic = $this->buildCacheKey($keywords);

        if ($cached = $this->cacheGet($providerId, $topic, $ttl)) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $endpoint, 'cache', 'Served from cache', count($cached));
            return $cached;
        }

        if ($endpoint === '') {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $endpoint, 'error', 'No endpoint configured', 0);
            return [];
        }

        $query = rawurlencode(implode('+', array_map(fn($k) => trim($k), $keywords)));
        $url = rtrim($endpoint, '/') . '?q=' . $query;

        $ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: ParkPod/1.0\r\n"]]);
        $json = @file_get_contents($url, false, $ctx);
        if (!$json) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'error', 'Unable to fetch Reddit content', 0);
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['data']['children']) || !is_array($data['data']['children'])) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'error', 'Reddit response was not in the expected format', 0);
            return [];
        }

        $items = [];

        foreach ($data['data']['children'] as $post) {
            if (!is_array($post) || !isset($post['data']) || !is_array($post['data'])) {
                continue;
            }

            $p = $post['data'];
            $items[] = $this->normalizeItem([
                'title' => $p['title'] ?? '',
                'summary' => $p['selftext'] ?? '',
                'url' => 'https://reddit.com' . ($p['permalink'] ?? '')
            ]);
        }

        $this->cacheSet($providerId, $topic, $items);
        $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'ok', 'Fetched Reddit posts', count($items));

        return $items;
    }
}
