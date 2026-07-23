<?php

function getIPinfo(string $ip): ?array {
    if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
        return null;
    }

    $result = fetchIPinfoIpinfo($ip);
    if ($result !== null) return $result;

    logger("ipinfo.io failed for {$ip}", 'WARNING');
    $result = fetchIPinfoDetails($ip);
    if ($result !== null) return $result;

    logger("ipdetails.io failed for {$ip}", 'WARNING');
    return null;
}

function fetchIPinfoIpinfo(string $ip): ?array {
    $url = 'https://ipinfo.io/' . urlencode($ip) . '/json';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        logger("ipinfo.io request failed for {$ip}: {$error}", 'ERROR');
        return null;
    }
    if ($statusCode !== 200) {
        logger("ipinfo.io returned {$statusCode} for {$ip}: {$body}", 'WARNING');
        return null;
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        logger("ipinfo.io returned invalid JSON for {$ip}: {$body}", 'WARNING');
        return null;
    }

    $countryCode = strtoupper($json['country'] ?? '');
    if ($countryCode === '') {
        logger("ipinfo.io missing country for {$ip}", 'WARNING');
        return null;
    }

    return normalizeIPinfo($countryCode, [
        'country' => $json['country'] ?? '',
        'company' => $json['as']['name'] ?? $json['org'] ?? '',
    ]);
}

function fetchIPinfoDetails(string $ip): ?array {
    $url = 'https://api.ipdetails.io/?ip=' . urlencode($ip);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        logger("ipdetails.io request failed for {$ip}: {$error}", 'ERROR');
        return null;
    }
    if ($statusCode !== 200) {
        logger("ipdetails.io returned {$statusCode} for {$ip}: {$body}", 'WARNING');
        return null;
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        logger("ipdetails.io returned invalid JSON for {$ip}: {$body}", 'WARNING');
        return null;
    }

    $countryCode = strtoupper($json['country_code'] ?? $json['iso_code'] ?? '');
    if ($countryCode === '') {
        logger("ipdetails.io missing country_code for {$ip}", 'WARNING');
        return null;
    }

    return normalizeIPinfo($countryCode, [
        'country' => $json['country_name'] ?? $json['country'] ?? '',
        'company' => $json['company_name'] ?? $json['org'] ?? '',
    ]);
}

function normalizeIPinfo(string $countryCode, array $raw): array {
    return [
        'country_code' => $countryCode,
        'country'      => trim($raw['country'] ?? ''),
        'company'      => trim($raw['company'] ?? ''),
        //'flag'         => countryCodeToFlag($countryCode),
        'flag'         => countryFlagUrl($countryCode),
    ];
}

function countryCodeToFlag(string $code): string {
    if (strlen($code) !== 2) return '';
    $offset = 0x1F1E6 - ord('A');
    return mb_chr($offset + ord($code[0])) . mb_chr($offset + ord($code[1]));
}

function countryFlagUrl($cc) {
    $cc = strtoupper($cc);
    $points = [];
    for ($i = 0; $i < 2; $i++) {
        $points[] = dechex(0x1F1E6 + (ord($cc[$i]) - 65));
    }
    return "https://twemoji.maxcdn.com/v/latest/svg/" . implode('-', $points) . ".svg";
}
