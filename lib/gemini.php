<?php

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

function _gemini_http_call(string $apiKey, string $prompt): ?string {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . urlencode($apiKey);
    $body = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]);
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $body,
            'timeout'       => 10,
            'ignore_errors' => true,
        ],
    ]);
    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) {
        return null;
    }
    $data = json_decode($result, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
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
        return [_slug_fallback($title)];
    }

    $pii = looks_like_pii($title);
    $collected = [];
    $maxAttempts = 5;

    for ($attempt = 0; $attempt < $maxAttempts && count($collected) < 3; $attempt++) {
        $need = 3 - count($collected);
        $newRaw = [];

        if ($pii) {
            // Separate call per slug — title is never sent to Gemini
            for ($i = 0; $i < $need; $i++) {
                $prompt = 'Generate a URL-safe slug of 2–4 lowercase hyphen-separated random English words. Return only the slug itself, nothing else. Example: river-table-moon';
                $raw = _gemini_http_call($apiKey, $prompt);
                if ($raw !== null) {
                    $slug = _sanitize_slug(strtok(trim($raw), "\n"));
                    if ($slug !== '') {
                        $newRaw[] = $slug;
                    }
                }
            }
        } else {
            $prompt = "Generate {$need} URL-safe slug(s) for a document titled \"{$title}\". Each slug must be 2–4 lowercase hyphen-separated words. Return exactly {$need} slug(s), one per line, no numbering or extra text.";
            $raw = _gemini_http_call($apiKey, $prompt);
            if ($raw !== null) {
                foreach (explode("\n", $raw) as $line) {
                    $slug = _sanitize_slug(trim($line));
                    if ($slug !== '') {
                        $newRaw[] = $slug;
                    }
                }
            }
        }

        foreach ($newRaw as $slug) {
            if (!_slug_exists($slug) && !in_array($slug, $collected, true)) {
                $collected[] = $slug;
                if (count($collected) >= 3) {
                    break;
                }
            }
        }
    }

    // Fallback if Gemini produced nothing usable
    if (empty($collected)) {
        $collected[] = _slug_fallback($title);
    }

    return array_values($collected);
}
