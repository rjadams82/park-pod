<?php

require_once __DIR__ . '/base.php';

class ProviderOpenLibrary extends ProviderBase {

    public function fetch(int $providerId, array $keywords, string $endpoint, int $ttl, $apiKey, ?string $providerName = null, ?string $providerType = null, ?string $host = null): array {

        $providerName = $providerName ?? 'OpenLibrary';
        $providerType = $providerType ?? 'openlibrary';
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
        $url = $endpoint . $query . '&limit=10';

        $ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: ParkPod/1.0\r\n"]]);
        $json = @file_get_contents($url, false, $ctx);
        if (!$json) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'error', 'Unable to fetch OpenLibrary content', 0);
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['docs']) || !is_array($data['docs'])) {
            $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'error', 'OpenLibrary response was not in the expected format', 0);
            return [];
        }

        $items = [];

        foreach ($data['docs'] as $doc) {
            if (!is_array($doc)) {
                continue;
            }

            $title = $doc['title'] ?? '';
            $key = $doc['key'] ?? '';
            $year = $doc['first_publish_year'] ?? '';
            $authors = $doc['author_name'] ?? [];

            if ($title === '') {
                continue;
            }

            $authorStr = is_array($authors) ? implode(', ', array_slice($authors, 0, 3)) : '';
            $summary = $title;
            if ($authorStr !== '') {
                $summary .= ' by ' . $authorStr;
            }
            if ($year !== '') {
                $summary .= ' (' . $year . ')';
            }

            $items[] = $this->normalizeItem([
                'title' => $title,
                'summary' => $summary,
                'url' => $key !== '' ? 'https://openlibrary.org' . $key : '',
            ]);
        }

        $this->cacheSet($providerId, $topic, $items);
        $this->recordFetchLog($providerId, $providerName, $providerType, $host, $topic, $url, 'ok', 'Fetched OpenLibrary books', count($items));

        return $items;
    }
}
