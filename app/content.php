<?php

require_once __DIR__ . '/providers/base.php';
foreach (glob(__DIR__ . '/providers/*.php') as $file) {
    require_once $file;
}


class Content {
    private App $app;

    public function __construct(App $app) {
        $this->app = $app;
    }

    /* ---------------------------------------------------------
       PROVIDER CRUD
    --------------------------------------------------------- */
    public function handleProviderPost(array $post): void
    {
        $db = $this->app->db;

        if (isset($post['edit_id'])) {
            $stmt = $db->prepare("
                UPDATE providers
                SET name = ?, type = ?, endpoint = ?, api_key = ?, ttl = ?, enabled = ?
                WHERE id = ?
            ");
            $stmt->execute([
                trim($post['name']),
                trim($post['type']),
                trim($post['endpoint']),
                trim($post['api_key'] ?? ''),
                intval($post['ttl']),
                intval($post['enabled']),
                intval($post['edit_id'])
            ]);
            header("Location: " . $this->app->adminUrl('providers'));
            exit;
        }

        if (isset($post['name'], $post['type'], $post['endpoint'])) {
            $stmt = $db->prepare("
                INSERT INTO providers (name, type, endpoint, api_key, ttl, enabled)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                trim($post['name']),
                trim($post['type']),
                trim($post['endpoint']),
                trim($post['api_key'] ?? ''),
                intval($post['ttl'] ?? 3600)
            ]);
            header("Location: " . $this->app->adminUrl('providers'));
            exit;
        }

        if (isset($post['delete_id'])) {
            $stmt = $db->prepare("DELETE FROM providers WHERE id = ?");
            $stmt->execute([intval($post['delete_id'])]);
            header("Location: " . $this->app->adminUrl('providers'));
            exit;
        }
    }

    public function getProviders(): array
    {
        $stmt = $this->app->db->query("SELECT * FROM providers WHERE enabled = 1 ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllProviders(): array
    {
        $stmt = $this->app->db->query("SELECT * FROM providers ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProviderById(int $id): ?array
    {
        $stmt = $this->app->db->prepare("SELECT * FROM providers WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /* ---------------------------------------------------------
       PROVIDER FACTORY + FETCHING
    --------------------------------------------------------- */
    public function providerFactory(string $type)
    {
        $db = $this->app->db;
        $type = strtolower($type);

        switch ($type) {
            case 'rss':
                return new ProviderRSS($db);
            case 'wikipedia':
                return new ProviderWikipedia($db);
            case 'reddit':
                return new ProviderReddit($db);
            case 'duckduckgo':
                return new ProviderDuckDuckGo($db);
            case 'openlibrary':
                return new ProviderOpenLibrary($db);
            case 'tinyfish':
                return new ProviderTinyFish($db);
            default:
                return null;
        }
    }

    public function getDynamicContent(array $keywords, ?string $host = null, ?string $typeFilter = null): array
    {
        $db = $this->app->db;
        $providers = $this->getProviders();
        $results = [];
        $keywords = $this->normalizeContentTopics($keywords);

        $queryKeywords = [$keywords[0]];
        $extras = array_slice($keywords, 1);
        if ($extras !== []) {
            shuffle($extras);
            $queryKeywords = array_merge($queryKeywords, array_slice($extras, 0, 2));
        }

        foreach ($providers as $p) {
            if ($typeFilter !== null && strtolower($p['type']) !== $typeFilter) {
                continue;
            }
            $providerId = (int) $p['id'];
            $type       = $p['type'];
            $endpoint   = $p['endpoint'];
            $ttl        = (int) $p['ttl'];
            $apiKey     = $p['api_key'];

            $provider = $this->providerFactory($type);
            if (!$provider) {
                $stmt = $db->prepare("
                    INSERT INTO provider_fetch_logs (provider_id, provider_name, provider_type, host, topic, endpoint, status, message, item_count, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$providerId, $p['name'], $type, $host, implode(', ', $queryKeywords), $endpoint, 'error', 'Unknown provider type: ' . $type, 0, time()]);
                continue;
            }

            $items = $provider->fetch(
                providerId: $providerId,
                keywords: $queryKeywords,
                endpoint: $endpoint,
                ttl: $ttl,
                apiKey: $apiKey,
                providerName: $p['name'],
                providerType: $p['type'],
                host: $host
            );

            foreach ($items as $item) {
                $results[] = [
                    'provider' => $p['name'],
                    'query'    => implode(', ', $queryKeywords),
                    'title'    => $item['title'] ?? '',
                    'summary'  => $item['summary'] ?? '',
                    'url'      => $item['url'] ?? '',
                ];
            }
        }

        return array_slice($results, 0, 20);
    }

    /* ---------------------------------------------------------
       FETCH LOGS
    --------------------------------------------------------- */
    public function getFetchLogs(int $limit = 50, ?int $providerId = null, ?string $host = null, ?string $status = null): array
    {
        $db = $this->app->db;
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

        if ($status !== null && $status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $sql = "SELECT * FROM provider_fetch_logs";
        if ($where !== []) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY created_at DESC, id DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllFetchLogHosts(): array
    {
        $stmt = $this->app->db->query("SELECT DISTINCT host FROM provider_fetch_logs WHERE host IS NOT NULL AND host != '' ORDER BY host ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /* ---------------------------------------------------------
       PARKED DOMAINS
    --------------------------------------------------------- */
    public function getParkedDomainConfig(?string $host): ?array
    {
        if ($host === null || $host === '') {
            return null;
        }

        $parts = explode('.', strtolower(trim($host)));
        $rootDomain = implode('.', array_slice($parts, -2));

        $stmt = $this->app->db->prepare("SELECT * FROM parked_domains WHERE enabled = 1 AND host = ? LIMIT 1");
        $stmt->execute([$rootDomain]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'host' => $row['host'],
            'category' => $row['category'] ?? '',
            'subject_tags' => array_values(array_filter(array_map('trim', preg_split('/[,|\n]+/', (string) $row['subject_tags'])))),
        ];
    }

    /* ---------------------------------------------------------
       TOPIC / KEYWORD HELPERS
    --------------------------------------------------------- */
    public function normalizeContentTopics($topics): array
    {
        if (is_string($topics)) {
            $topics = [$topics];
        }

        if (!is_array($topics)) {
            return ['General Information'];
        }

        $normalized = [];
        $seen = [];

        foreach ($topics as $topic) {
            if (!is_string($topic)) {
                continue;
            }

            $topic = trim($topic);
            if ($topic === '') {
                continue;
            }

            $key = strtolower(preg_replace('/\s+/', ' ', $topic));
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $topic;
        }

        return $normalized !== [] ? $normalized : ['General Information'];
    }

    public function extractDomainKeywords(string $domain): array
    {
        $domain = strtolower($domain);
        $domain = preg_replace('/\.[a-z]{2,}$/', '', $domain);
        $domain = str_replace(['-', '_'], ' ', $domain);
        $parts = preg_split('/\s+/', $domain);
        $parts = array_filter($parts, fn($p) => strlen($p) > 2);
        return array_values($parts);
    }

    public function normalizeTopicTag(string $tag): string
    {
        $tag = strtolower(trim($tag));
        $tag = preg_replace('/[^a-z0-9]+/', ' ', $tag);
        $tag = preg_replace('/\s+/', ' ', $tag);
        return trim($tag);
    }

    public function buildContentQueryPhrases(?string $domain = null, ?string $referrer = null, ?array $parkedConfig = null): array
    {
        $config = $this->app->config;
        $phrases = [];
        $seen = [];

        $parkedCategory = trim((string) ($parkedConfig['category'] ?? ''));
        $parkedTags = [];

        if (is_array($parkedConfig['subject_tags'] ?? null)) {
            $parkedTags = array_values(array_filter(array_map('trim', $parkedConfig['subject_tags'])));
        } elseif (is_string($parkedConfig['subject_tags'] ?? null)) {
            $parkedTags = array_values(array_filter(array_map('trim', preg_split('/[,|\n]+/', (string) $parkedConfig['subject_tags']))));
        }

        $primaryTopic = $parkedCategory !== '' ? $parkedCategory : trim((string) ($config['site']['topic'] ?? ''));
        if ($primaryTopic === '') {
            $primaryTopic = 'General Information';
        }

        $addPhrase = function (string $phrase) use (&$phrases, &$seen): void {
            $phrase = trim($phrase);
            if ($phrase === '') {
                return;
            }

            $normalized = strtolower(preg_replace('/\s+/', ' ', $phrase));
            if ($normalized === '' || isset($seen[$normalized])) {
                return;
            }

            $seen[$normalized] = true;
            $phrases[] = $phrase;
        };

        $addPhrase($primaryTopic);

        foreach ($parkedTags as $tag) {
            $addPhrase(trim((string) $tag));
        }

        if ($domain !== null && $domain !== '') {
            foreach ($this->extractDomainKeywords($domain) as $keyword) {
                $addPhrase($keyword);
            }
        }

        if ($referrer !== null && $referrer !== '') {
            $referrerHost = parse_url($referrer, PHP_URL_HOST) ?: '';
            foreach ($this->extractDomainKeywords($referrerHost) as $keyword) {
                $addPhrase($keyword);
            }
        }

        if (empty($phrases)) {
            $addPhrase('General Information');
        }

        return array_slice(array_values($phrases), 0, 8);
    }

    public function mapKeywordsToTopic(array $keywords): string
    {
        $map = [
            'hiking'   => 'Outdoor Recreation',
            'boots'    => 'Footwear & Gear',
            'property' => 'Real Estate',
            'home'     => 'Real Estate',
            'house'    => 'Real Estate',
            'finance'  => 'Financial Information',
            'loan'     => 'Financial Information',
            'tech'     => 'Technology',
            'ai'       => 'Artificial Intelligence',
            'car'      => 'Automotive',
            'auto'     => 'Automotive',
            'health'   => 'Health & Wellness',
            'food'     => 'Food & Cooking',
            'travel'   => 'Travel & Tourism',
            'lisbon'   => 'Local Information: Lisbon, CT',
            'ct'       => 'Local Information: Connecticut',
            'real'     => 'Real Estate',
            'estate'   => 'Real Estate',
            'motor'    => 'Automotive',
            'vehicle'  => 'Automotive',
            'rent'     => 'Real Estate',
            'sale'     => 'Real Estate',
            'listing'  => 'Real Estate',
            'list'     => 'Real Estate',
            'repair'   => 'Automotive',
            'shop'     => 'Automotive',
            'sound'    => 'Audio Production',
            'audio'    => 'Audio Production',
            'music'    => 'Audio Production',
            'kabul'    => 'Current Events & Humanitarian Relief',
            'escape'   => 'Current Events & Humanitarian Relief',
            'child'    => 'Family & Community Support',
            'family'   => 'Family & Community Support',
            'bread'    => 'Food & Cooking',
            'sweet'    => 'Food & Cooking',
            'site'     => 'Website Development',
            'website'  => 'Website Development',
            'build'    => 'Website Development',
            'rebuild'  => 'Website Development',
            'my'       => 'Personal Services',
            'care'     => 'Personal Services',
        ];

        foreach ($keywords as $k) {
            $normalized = $this->normalizeTopicTag($k);
            if ($normalized === '') {
                continue;
            }
            if (isset($map[$normalized])) {
                return $map[$normalized];
            }
        }

        return 'General Information';
    }

    public function inferTopicFromRequest(string $domain, ?string $referrer = null): string
    {
        $config = $this->app->config;
        $tags = [];

        if (!empty($config['site']['subject_tags'] ?? [])) {
            $tags = array_merge($tags, array_values($config['site']['subject_tags']));
        }

        if (!empty($config['site']['topic'])) {
            $tags[] = $config['site']['topic'];
        }

        foreach ($this->extractDomainKeywords($domain) as $keyword) {
            $tags[] = $keyword;
        }

        if ($referrer) {
            $referrerHost = parse_url($referrer, PHP_URL_HOST) ?: '';
            foreach ($this->extractDomainKeywords($referrerHost) as $keyword) {
                $tags[] = $keyword;
            }
        }

        $topic = $this->mapKeywordsToTopic($tags);
        if ($topic !== 'General Information') {
            return $topic;
        }

        return $this->autoTopicFromDomain($domain);
    }

    public function autoTopicFromDomain(string $domain): string
    {
        return $this->mapKeywordsToTopic($this->extractDomainKeywords($domain));
    }

    /* ---------------------------------------------------------
       SEO GENERATION
    --------------------------------------------------------- */
    public function generateSEOBlock(string $topic, string $type): string
    {
        $topic = htmlspecialchars($topic);

        switch ($type) {
            case 'overview':
                return "
                    <p>{$topic} plays an important role in today's digital landscape. 
                    This domain provides curated information, insights, and topic‑driven 
                    content designed to help visitors explore key ideas and learn more 
                    about the subject.</p>
                ";

            case 'guides':
                return "
                    <ul>
                        <li>Beginner introduction to {$topic}</li>
                        <li>Advanced concepts and strategies</li>
                        <li>Common challenges and how to solve them</li>
                        <li>Expert tips for improving your understanding</li>
                        <li>How {$topic} impacts related industries</li>
                    </ul>
                ";

            case 'trending':
                return "
                    <p>Current trends in {$topic} include:</p>
                    <ul>
                        <li>New developments shaping the future</li>
                        <li>Popular discussions among professionals</li>
                        <li>Emerging tools and technologies</li>
                        <li>Growing interest from consumers and businesses</li>
                    </ul>
                ";

            case 'resources':
                return "
                    <p>Helpful resources for learning more about {$topic}:</p>
                    <ul>
                        <li>Topic‑focused articles and guides</li>
                        <li>Community discussions and forums</li>
                        <li>Educational videos and tutorials</li>
                        <li>Industry reports and research summaries</li>
                    </ul>
                ";

            default:
                return "<p>Additional content coming soon.</p>";
        }
    }

    public function generateTopicOverview(string $topic): string
    {
        return "This domain focuses on {$topic}, offering curated information, resources, and insights tailored to visitors seeking reliable content in this niche.";
    }

    public function generateTopicList(string $topic): string
    {
        $items = [
            "Latest trends in {$topic}",
            "Beginner guides for {$topic}",
            "Advanced strategies in {$topic}",
            "Top resources for {$topic}",
            "Common questions about {$topic}"
        ];

        return "<ul><li>" . implode("</li><li>", $items) . "</li></ul>";
    }
}
