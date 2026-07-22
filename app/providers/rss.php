<?php

require_once __DIR__ . '/base.php';

class ProviderRSS extends ProviderBase {

    public function fetch(int $providerId, array $keywords, string $endpoint, int $ttl, $apiKey, ?string $providerName = null, ?string $providerType = null, ?string $host = null): array {

        $providerName = $providerName ?? 'Unknown';
        $providerType = $providerType ?? 'rss';
        $topic = $this->buildCacheKey($keywords);

        if ($cached = $this->cacheGet($providerId, $topic, $ttl)) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $endpoint, 'cache', 'Served from cache', count($cached));
            return $cached;
        }

        if ($endpoint === '') {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $endpoint, 'error', 'No endpoint configured', 0);
            return [];
        }

        $query = rawurlencode(implode(' ', array_map(fn($k) => trim($k), $keywords)));
        $url = str_replace('{query}', $query, $endpoint);

        $xml = @simplexml_load_file($url);
        if (!$xml) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'error', 'Unable to load RSS feed', 0);
            return [];
        }

        $items = [];
        $channelItems = $xml->channel->item ?? [];

        foreach ($channelItems as $item) {
            $items[] = $this->normalizeItem([
                'title' => (string) ($item->title ?? ''),
                'summary' => (string) ($item->description ?? ''),
                'url' => (string) ($item->link ?? ''),
            ]);
        }

        $this->cacheSet($providerId, $topic, $items);
        $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'ok', 'Fetched RSS items', count($items));

        return $items;
    }
}
