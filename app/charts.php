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

        $stmt = $this->db->prepare("
            SELECT DATE(created_at, 'unixepoch') as day, COUNT(*) as hits
            FROM access_logs
            {$where}
            GROUP BY day
            ORDER BY day ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        foreach ($data as $i => $row) {
            $x = $padding['left'] + ($chartWidth / max($count - 1, 1)) * $i;
            $y = $padding['top'] + $chartHeight - ($row[$valueKey] / $maxValue * $chartHeight);
            $points[] = "{$x},{$y}";

            if ($count <= 10 || $i % max(floor($count / 8), 1) == 0) {
                $labels[] = ['x' => $x, 'y' => $height - 10, 'text' => substr($row[$labelKey], 5)];
            }
        }

        $polyline = implode(' ', $points);

        $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="chart-svg">';
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
            $svg .= '<text x="' . $l['x'] . '" y="' . $l['y'] . '" text-anchor="middle" class="chart-label">' . htmlspecialchars($l['text']) . '</text>';
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

        $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="chart-svg">';
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

        $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="chart-svg">';
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