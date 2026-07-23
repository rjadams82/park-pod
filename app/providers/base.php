<?php

class ProviderBase {

    protected PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    protected function cacheGet(int $providerId, string $topic, int $ttl): ?array {
        $stmt = $this->db->prepare("
            SELECT data, fetched_at 
            FROM content_cache 
            WHERE provider_id = ? AND topic = ?
            ORDER BY fetched_at DESC
            LIMIT 1
        ");
        $stmt->execute([$providerId, $topic]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;
        if (time() - (int) $row['fetched_at'] > $ttl) return null;

        $decoded = json_decode($row['data'], true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function cacheSet(int $providerId, string $topic, array $data): void {
        if (empty($data)) return;

        $this->db->prepare("
            DELETE FROM content_cache WHERE provider_id = ? AND topic = ?
        ")->execute([$providerId, $topic]);

        $stmt = $this->db->prepare("
            INSERT INTO content_cache (provider_id, topic, data, fetched_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$providerId, $topic, json_encode($data), time()]);
    }

    protected function recordFetchLog(?int $providerId, string $providerName, string $providerType, ?string $host, string $topic, string $endpoint, string $status, string $message, int $itemCount = 0): void {
        $stmt = $this->db->prepare("
            INSERT INTO provider_fetch_logs (provider_id, provider_name, provider_type, host, topic, endpoint, status, message, item_count, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$providerId, $providerName, $providerType, $host, $topic, $endpoint, $status, $message, $itemCount, time()]);
    }

    protected function getRecentFetchLogs(int $limit = 50, ?int $providerId = null, ?string $host = null): array {
        $where = [];
        $params = [];

        if ($providerId !== null) {
            $where[] = 'provider_id = ?';
            $params[] = $providerId;
        }

        if ($host !== null && $host !== '') {
            $where[] = 'host = ?';
            $params[] = $host;
        }

        $sql = "SELECT * FROM provider_fetch_logs";
        if ($where !== []) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY created_at DESC, id DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function buildCacheKey(array $keywords): string {
        $normalized = array_map(fn($k) => strtolower(trim($k)), $keywords);
        $normalized = array_filter($normalized, fn($k) => $k !== '');
        sort($normalized);
        return implode(', ', $normalized);
    }

    protected function normalizeItem(array $item): array {
        return [
            'title' => (string) ($item['title'] ?? ''),
            'summary' => (string) ($item['summary'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
        ];
    }
}

