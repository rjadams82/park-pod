<?php

class Charts {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /* ---------------------------------------------------------
       TRAFFIC DATA QUERIES
    --------------------------------------------------------- */

    public function getTrafficTrend(string $range = '30d'): array {
        $days = $this->rangeToDays($range);
        $where = $days > 0 ? "WHERE created_at >= ?" : "";
        $params = $days > 0 ? [time() - ($days * 86400)] : [];

        if ($days === 0) {
            $spanRow = $this->db->query("SELECT MIN(created_at) as earliest, MAX(created_at) as latest FROM access_logs")->fetch(PDO::FETCH_ASSOC);
            if ($spanRow && $spanRow['earliest'] && $spanRow['latest']) {
                $days = max(1, (int) (($spanRow['latest'] - $spanRow['earliest']) / 86400));
            }
        }

        $granularity = $this->getGranularity($days);
        return $this->getTimeSeriesData($granularity, $where, $params, $days);
    }

    public static function getGranularity(int $days): string {
        if ($days <= 1) return 'hour';
        if ($days <= 30) return 'day';
        if ($days <= 1000) return 'month';
        return 'year';
    }

    public static function getGranularityLabel(string $granularity): string {
        return match($granularity) {
            'hour'  => 'Hourly',
            'day'   => 'Daily',
            'month' => 'Monthly',
            'year'  => 'Yearly',
            default => '',
        };
    }

    private function getTimeSeriesData(string $granularity, string $where, array $params, int $days): array {
        $col = match($granularity) {
            'hour'  => "strftime('%H:00', created_at, 'unixepoch')",
            'day'   => "DATE(created_at, 'unixepoch')",
            'month' => "strftime('%Y-%m', created_at, 'unixepoch')",
            'year'  => "strftime('%Y', created_at, 'unixepoch')",
        };
        $key = $granularity;

        $stmt = $this->db->prepare("
            SELECT {$col} as {$key}, COUNT(*) as hits
            FROM access_logs
            {$where}
            GROUP BY {$key}
            ORDER BY {$key} ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[$row[$key]] = (int) $row['hits'];
        }

        $filled = [];
        match($granularity) {
            'hour' => $this->fillHours($map, $filled, $key),
            'day'  => $this->fillDays($map, $filled, $key, $days),
            'month'=> $this->fillMonths($map, $filled, $key, $days),
            'year' => $this->fillYears($map, $filled, $key),
        };
        return $filled;
    }

    private function fillHours(array $map, array &$filled, string $key): void {
        $now = (int) date('G');
        for ($i = 0; $i < 24; $i++) {
            $h = ($now - 23 + $i + 24) % 24;
            $k = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $filled[] = [$key => $k, 'hits' => $map[$k] ?? 0];
        }
    }

    private function fillDays(array $map, array &$filled, string $key, int $days): void {
        $now = new DateTime('now');
        for ($i = min($days, 365) - 1; $i >= 0; $i--) {
            $dt = (clone $now)->modify("-{$i} days");
            $k = $dt->format('Y-m-d');
            $filled[] = [$key => $k, 'hits' => $map[$k] ?? 0];
        }
    }

    private function fillMonths(array $map, array &$filled, string $key, int $days): void {
        $months = (int) ceil($days / 30);
        $start = new DateTime('-' . $months . ' months');
        $end = new DateTime('now');
        $period = new DatePeriod($start, new DateInterval('P1M'), $end);
        foreach ($period as $dt) {
            $k = $dt->format('Y-m');
            $filled[] = [$key => $k, 'hits' => $map[$k] ?? 0];
        }
    }

    private function fillYears(array $map, array &$filled, string $key): void {
        $min = 9999;
        $max = 0;
        foreach ($map as $k => $v) {
            $y = (int) $k;
            if ($y < $min) $min = $y;
            if ($y > $max) $max = $y;
        }
        if ($max === 0) $max = (int) date('Y');
        for ($y = $min; $y <= $max; $y++) {
            $k = (string) $y;
            $filled[] = [$key => $k, 'hits' => $map[$k] ?? 0];
        }
    }

    public function getTopDomains(int $limit = 10, string $range = '30d'): array {
        $days = $this->rangeToDays($range);
        $where = $days > 0 ? "WHERE created_at >= ?" : "";
        $params = $days > 0 ? [time() - ($days * 86400)] : [];
        $params[] = $limit;

        $stmt = $this->db->prepare("
            SELECT domain, COUNT(*) as hits
            FROM access_logs
            {$where}
            GROUP BY domain
            ORDER BY hits DESC
            LIMIT ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReferrerSources(int $limit = 8, string $range = '30d'): array {
        $days = $this->rangeToDays($range);
        $where = $days > 0 ? "WHERE created_at >= ?" : "";
        $params = $days > 0 ? [time() - ($days * 86400)] : [];
        $params[] = $limit;

        $stmt = $this->db->prepare("
            SELECT
                CASE
                    WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                    ELSE SUBSTR(referrer, INSTR(referrer, '//') + 2)
                END as source,
                COUNT(*) as hits
            FROM access_logs
            {$where}
            GROUP BY source
            ORDER BY hits DESC
            LIMIT ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function rangeToDays(string $range): int {
        return match($range) {
            '1d' => 1,
            '7d' => 7,
            '30d' => 30,
            '6m' => 180,
            '1y' => 365,
            'all' => 0,
            default => 30,
        };
    }

    /* ---------------------------------------------------------
       SVG CHART RENDERERS
    --------------------------------------------------------- */

    public function renderLineChart(array $data, string $labelKey, string $valueKey, string $title, int $width = 500, int $height = 200): string {
        if (empty($data)) {
            return '<p class="chart-empty">No data available</p>';
        }

        $maxValue = max(array_column($data, $valueKey));
        if ($maxValue == 0) $maxValue = 1;

        $padding = ['top' => 30, 'right' => 20, 'bottom' => 40, 'left' => 50];
        $chartWidth = $width - $padding['left'] - $padding['right'];
        $chartHeight = $height - $padding['top'] - $padding['bottom'];

        $points = [];
        $labels = [];
        $count = count($data);
        $labelEvery = $count <= 12 ? 1 : max(floor($count / 8), 1);

        foreach ($data as $i => $row) {
            $x = $padding['left'] + ($chartWidth / max($count - 1, 1)) * $i;
            $y = $padding['top'] + $chartHeight - ($row[$valueKey] / $maxValue * $chartHeight);
            $points[] = "{$x},{$y}";

            if ($i % $labelEvery == 0) {
                $raw = $row[$labelKey] ?? '';
                $text = is_string($raw) && strlen($raw) > 5 ? substr($raw, 5) : $raw;
                $labels[] = ['x' => $x, 'y' => $height - 10, 'text' => $text];
            }
        }

        $polyline = implode(' ', $points);

        $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '" class="chart-svg">';
        $svg .= '<text x="' . ($width / 2) . '" y="18" text-anchor="middle" class="chart-title">' . htmlspecialchars($title) . '</text>';

        // Grid lines
        for ($i = 0; $i <= 4; $i++) {
            $y = $padding['top'] + ($chartHeight / 4) * $i;
            $val = round($maxValue - ($maxValue / 4) * $i);
            $svg .= '<line x1="' . $padding['left'] . '" y1="' . $y . '" x2="' . ($width - $padding['right']) . '" y2="' . $y . '" class="chart-grid"/>';
            $svg .= '<text x="' . ($padding['left'] - 8) . '" y="' . ($y + 4) . '" text-anchor="end" class="chart-label">' . $val . '</text>';
        }

        // Area fill
        $areaPoints = $padding['left'] . ',' . ($padding['top'] . $chartHeight) . ' ' . $polyline . ' ' . ($width - $padding['right']) . ',' . ($padding['top'] . $chartHeight);
        $svg .= '<polygon points="' . $areaPoints . '" class="chart-area"/>';

        // Line
        $svg .= '<polyline points="' . $polyline . '" class="chart-line"/>';

        // Dots
        foreach ($points as $p) {
            $svg .= '<circle cx="' . explode(',', $p)[0] . '" cy="' . explode(',', $p)[1] . '" r="3" class="chart-dot"/>';
        }

        // X labels
        foreach ($labels as $l) {
            $svg .= '<text x="' . $l['x'] . '" y="' . $l['y'] . '" text-anchor="middle" class="chart-label">' . htmlspecialchars((string) $l['text']) . '</text>';
        }

        $svg .= '</svg>';
        return $svg;
    }

    public function renderBarChart(array $data, string $labelKey, string $valueKey, string $title, int $width = 500, int $height = 200): string {
        if (empty($data)) {
            return '<p class="chart-empty">No data available</p>';
        }

        $maxValue = max(array_column($data, $valueKey));
        if ($maxValue == 0) $maxValue = 1;

        $padding = ['top' => 30, 'right' => 20, 'bottom' => 50, 'left' => 50];
        $chartWidth = $width - $padding['left'] - $padding['right'];
        $chartHeight = $height - $padding['top'] - $padding['bottom'];

        $barCount = count($data);
        $barGap = 8;
        $barWidth = max(($chartWidth - ($barCount - 1) * $barGap) / $barCount, 4);

        $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '" class="chart-svg">';
        $svg .= '<text x="' . ($width / 2) . '" y="18" text-anchor="middle" class="chart-title">' . htmlspecialchars($title) . '</text>';

        // Grid lines
        for ($i = 0; $i <= 4; $i++) {
            $y = $padding['top'] + ($chartHeight / 4) * $i;
            $val = round($maxValue - ($maxValue / 4) * $i);
            $svg .= '<line x1="' . $padding['left'] . '" y1="' . $y . '" x2="' . ($width - $padding['right']) . '" y2="' . $y . '" class="chart-grid"/>';
            $svg .= '<text x="' . ($padding['left'] - 8) . '" y="' . ($y + 4) . '" text-anchor="end" class="chart-label">' . $val . '</text>';
        }

        // Bars
        foreach ($data as $i => $row) {
            $x = $padding['left'] + $i * ($barWidth + $barGap);
            $barHeight = ($row[$valueKey] / $maxValue) * $chartHeight;
            $y = $padding['top'] + $chartHeight - $barHeight;

            $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $barWidth . '" height="' . $barHeight . '" class="chart-bar"/>';
            $svg .= '<text x="' . ($x + $barWidth / 2) . '" y="' . ($y - 5) . '" text-anchor="middle" class="chart-value">' . $row[$valueKey] . '</text>';

            $label = htmlspecialchars(substr($row[$labelKey], 0, 12));
            $svg .= '<text x="' . ($x + $barWidth / 2) . '" y="' . ($height - $padding['bottom'] + 15) . '" text-anchor="middle" class="chart-label" transform="rotate(45 ' . ($x + $barWidth / 2) . ' ' . ($height - $padding['bottom'] + 15) . ')">' . $label . '</text>';
        }

        $svg .= '</svg>';
        return $svg;
    }

    public function renderHorizontalBarChart(array $data, string $labelKey, string $valueKey, string $title, int $width = 500, int $height = 200): string {
        if (empty($data)) {
            return '<p class="chart-empty">No data available</p>';
        }

        $maxValue = max(array_column($data, $valueKey));
        if ($maxValue == 0) $maxValue = 1;

        $padding = ['top' => 30, 'right' => 60, 'bottom' => 20, 'left' => 100];
        $chartWidth = $width - $padding['left'] - $padding['right'];
        $chartHeight = $height - $padding['top'] - $padding['bottom'];

        $barCount = count($data);
        $barGap = 6;
        $barHeight = max(($chartHeight - ($barCount - 1) * $barGap) / $barCount, 4);

        $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '" class="chart-svg">';
        $svg .= '<text x="' . ($width / 2) . '" y="18" text-anchor="middle" class="chart-title">' . htmlspecialchars($title) . '</text>';

        foreach ($data as $i => $row) {
            $y = $padding['top'] + $i * ($barHeight + $barGap);
            $barW = ($row[$valueKey] / $maxValue) * $chartWidth;

            $svg .= '<text x="' . ($padding['left'] - 8) . '" y="' . ($y + $barHeight / 2 + 4) . '" text-anchor="end" class="chart-label">' . htmlspecialchars(substr($row[$labelKey], 0, 15)) . '</text>';
            $svg .= '<rect x="' . $padding['left'] . '" y="' . $y . '" width="' . $barW . '" height="' . $barHeight . '" class="chart-bar-h"/>';
            $svg .= '<text x="' . ($padding['left'] + $barW + 5) . '" y="' . ($y + $barHeight / 2 + 4) . '" class="chart-value">' . $row[$valueKey] . '</text>';
        }

        $svg .= '</svg>';
        return $svg;
    }
}