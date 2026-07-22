<?php

require_once __DIR__ . '/base.php';

class ProviderDuckDuckGo extends ProviderBase {

    public function fetch(int $providerId, array $keywords, string $endpoint, int $ttl, $apiKey, ?string $providerName = null, ?string $providerType = null, ?string $host = null): array {

        $providerName = $providerName ?? 'DuckDuckGo';
        $providerType = $providerType ?? 'duckduckgo';
        $topic = trim($keywords[0]);

        if ($cached = $this->cacheGet($providerId, $topic, $ttl)) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $endpoint, 'cache', 'Served from cache', count($cached));
            return $cached;
        }

        if ($endpoint === '') {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $endpoint, 'error', 'No endpoint configured', 0);
            return [];
        }

        $query = rawurlencode(trim($keywords[0]));
        $url = $endpoint . $query;

        $ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: ParkPod/1.0\r\n"]]);
        $json = @file_get_contents($url, false, $ctx);
        if (!$json) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'error', 'Unable to fetch DuckDuckGo content', 0);
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'error', 'DuckDuckGo response was not valid JSON', 0);
            return [];
        }

        $items = [];

        $abstract = trim($data['AbstractText'] ?? '');
        $abstractUrl = $data['AbstractURL'] ?? '';
        if ($abstract !== '' && $abstractUrl !== '') {
            $items[] = $this->normalizeItem([
                'title' => $data['Heading'] ?? mb_substr($abstract, 0, 80),
                'summary' => $abstract,
                'url' => $abstractUrl,
            ]);
        }

        $sources = $data['Results'] ?? [];
        foreach ($sources as $r) {
            if (!is_array($r)) continue;
            $text = trim($r['Text'] ?? '');
            $firstUrl = $r['FirstURL'] ?? '';
            if ($text === '' || $firstUrl === '') continue;
            $items[] = $this->normalizeItem([
                'title' => mb_substr($text, 0, 80),
                'summary' => $text,
                'url' => $firstUrl,
            ]);
        }

        $topics = $data['RelatedTopics'] ?? [];
        foreach ($topics as $t) {
            if (!is_array($t) || isset($t['Topics'])) {
                continue;
            }

            $text = $t['Text'] ?? '';
            $firstUrl = $t['FirstURL'] ?? '';

            if ($text === '') {
                continue;
            }

            $items[] = $this->normalizeItem([
                'title' => mb_substr($text, 0, 80),
                'summary' => $text,
                'url' => $firstUrl,
            ]);
        }

        $this->cacheSet($providerId, $topic, $items);
        $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'ok', 'Fetched DuckDuckGo results', count($items));

        return $items;
    }
}
