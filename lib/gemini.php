<?php

// Set to a callable(string $apiKey, string $prompt): ?string to override HTTP in tests.
$_gemini_http_caller = null;

function looks_like_pii(string $title): bool {
    $patterns = [
        '/\S+@\S+\.\S+/',                           // email address
        '/\b\d{3}[-.\s]\d{3}[-.\s]\d{4}\b/',        // US phone number
        '/\b\d{3}-\d{2}-\d{4}\b/',                  // SSN
        '/\b\d{4}[- ]\d{4}[- ]\d{4}[- ]\d{4}\b/',  // credit card number
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $title)) {
            return true;
        }
    }
    return false;
}

function _gemini_log(string $level, string $msg): void {
    error_log("[gemini][$level] $msg");
}

function _gemini_http_call(string $apiKey, string $prompt): ?string {
    global $_gemini_http_caller;
    if (is_callable($_gemini_http_caller)) {
        return call_user_func($_gemini_http_caller, $apiKey, $prompt);
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($apiKey);
    $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]);
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $payload,
            'timeout'       => 10,
            'ignore_errors' => true,
        ],
    ]);

    error_clear_last();
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        $err = error_get_last();
        _gemini_log('error', 'HTTP request failed: ' . ($err['message'] ?? 'unknown — check SSL/network'));
        return null;
    }

    $data = json_decode($raw, true);
    if (isset($data['error'])) {
        _gemini_log('error', 'Gemini API error ' . ($data['error']['code'] ?? '?') . ': ' . ($data['error']['message'] ?? ''));
        return null;
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($text === null) {
        _gemini_log('warn', 'Unexpected Gemini response shape: ' . substr($raw, 0, 300));
    }
    return $text;
}

function _sanitize_slug(string $raw): string {
    $slug = strtolower(trim($raw));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = preg_replace('/-{2,}/', '-', $slug);
    return trim($slug, '-');
}

function _slug_exists(string $slug): bool {
    $stmt = db()->prepare('SELECT 1 FROM documents WHERE slug = ?');
    $stmt->execute([$slug]);
    return (bool) $stmt->fetch();
}

function _slug_fallback(string $title): string {
    $base = _sanitize_slug(preg_replace('/[^a-z0-9]+/i', '-', $title));
    if ($base === '') {
        $base = 'document';
    }
    $slug = $base;
    while (_slug_exists($slug)) {
        $slug = $base . '-' . rand(1000, 9999);
    }
    return $slug;
}

function gemini_suggest_slugs(string $title): array {
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        _gemini_log('warn', 'GEMINI_API_KEY not set — using title-based fallback slug');
        return [_slug_fallback($title)];
    }

    $pii = looks_like_pii($title);
    if ($pii) {
        _gemini_log('info', 'Title flagged as PII — requesting random-word slugs (title withheld from Gemini)');
    }

    $collected = [];
    $anySuccess = false;
    $maxAttempts = 5;

    for ($attempt = 0; $attempt < $maxAttempts && count($collected) < 3; $attempt++) {
        $need = 3 - count($collected);
        $batch = [];

        if ($pii) {
            for ($i = 0; $i < $need; $i++) {
                $prompt = 'Generate a URL-safe slug of 2–4 lowercase hyphen-separated random English words. Return only the slug itself, nothing else. Example: river-table-moon';
                $text = _gemini_http_call($apiKey, $prompt);
                if ($text !== null) {
                    $anySuccess = true;
                    $slug = _sanitize_slug(strtok(trim($text), "\n"));
                    if ($slug !== '') {
                        $batch[] = $slug;
                    }
                }
            }
        } else {
            $noun = $need === 1 ? 'slug' : 'slugs';
            $prompt = "Generate $need URL-safe $noun for a document titled \"$title\". Each slug must be 2–4 lowercase hyphen-separated words. Return exactly $need $noun, one per line, no numbering or extra text.";
            $text = _gemini_http_call($apiKey, $prompt);
            if ($text !== null) {
                $anySuccess = true;
                foreach (explode("\n", $text) as $line) {
                    $slug = _sanitize_slug(trim($line));
                    if ($slug !== '') {
                        $batch[] = $slug;
                    }
                }
            }
        }

        foreach ($batch as $slug) {
            if (!_slug_exists($slug) && !in_array($slug, $collected, true)) {
                $collected[] = $slug;
                if (count($collected) >= 3) {
                    break;
                }
            }
        }
    }

    if (empty($collected)) {
        $reason = $anySuccess ? 'all suggestions were already taken' : 'all API calls failed';
        _gemini_log('error', "No usable slugs from Gemini ($reason) — falling back to title-based slug");
        $collected[] = _slug_fallback($title);
    } else {
        _gemini_log('info', 'Returning ' . count($collected) . ' slug(s): ' . implode(', ', $collected));
    }

    return array_values($collected);
}
