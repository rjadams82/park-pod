<?php

require_once __DIR__ . '/base.php';

class ProviderWikipedia extends ProviderBase {

    public function fetch(int $providerId, array $keywords, string $endpoint, int $ttl, $apiKey, ?string $providerName = null, ?string $providerType = null, ?string $host = null): array {

        $providerName = $providerName ?? 'Unknown';
        $providerType = $providerType ?? 'wikipedia';
        $topic = trim($keywords[0]);

        if ($cached = $this->cacheGet($providerId, $topic, $ttl)) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $endpoint, 'cache', 'Served from cache', count($cached));
            return $cached;
        }

        if ($endpoint === '') {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $endpoint, 'error', 'No endpoint configured', 0);
            return [];
        }

        $urlTopic = rawurlencode($topic);
        $url = rtrim($endpoint, '/') . '/' . $urlTopic;
        $ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: ParkPod/1.0\r\n"]]);
        $json = @file_get_contents($url, false, $ctx);
        if (!$json) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'error', 'Unable to fetch Wikipedia content', 0);
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'error', 'Wikipedia response was not valid JSON', 0);
            return [];
        }

        $items = [
            $this->normalizeItem([
                'title' => $topic,
                'summary' => $data['extract'] ?? '',
                'url' => $data['content_urls']['desktop']['page'] ?? ''
            ])
        ];

        $this->cacheSet($providerId, $topic, $items);
        $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'ok', 'Fetched Wikipedia content', count($items));

        return $items;
    }
}
