<?php

require_once __DIR__ . '/base.php';

class ProviderTinyFish extends ProviderBase {

    public function fetch(int $providerId, array $keywords, string $endpoint, int $ttl, $apiKey, ?string $providerName = null, ?string $providerType = null, ?string $host = null): array {

        $providerName = $providerName ?? 'TinyFish';
        $providerType = $providerType ?? 'tinyfish';
        $topic = $this->buildCacheKey($keywords);

        if ($cached = $this->cacheGet($providerId, $topic, $ttl)) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $endpoint, 'cache', 'Served from cache', count($cached));
            return $cached;
        }

        if ($endpoint === '') {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $endpoint, 'error', 'No endpoint configured', 0);
            return [];
        }

        if ($apiKey === '') {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $endpoint, 'error', 'No API key configured', 0);
            return [];
        }

        $query = rawurlencode(implode(' ', array_map(fn($k) => trim($k), $keywords)));
        $url = rtrim($endpoint, '?& ') . '&query=' . $query;

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 15,
                'header' => "X-API-Key: {$apiKey}\r\nAccept: application/json\r\n",
            ],
        ]);
        $json = @file_get_contents($url, false, $ctx);
        if (!$json) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'error', 'Unable to fetch TinyFish content', 0);
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'error', 'TinyFish response was not valid JSON', 0);
            return [];
        }

        $items = [];
        $results = $data['results'] ?? $data['data'] ?? [];
        if (!is_array($results)) {
            $results = [];
        }

        foreach ($results as $r) {
            $items[] = $this->normalizeItem([
                'title' => $r['title'] ?? '',
                'summary' => $r['snippet'] ?? $r['description'] ?? '',
                'url' => $r['url'] ?? '',
            ]);
        }

        if (!empty($items)) {
            $this->cacheSet($providerId, $topic, $items);
        }
        $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'ok', 'Fetched TinyFish content', count($items));

        return $items;
    }
}
