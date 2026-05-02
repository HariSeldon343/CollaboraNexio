<?php
/**
 * OpenAI Client Helper (minimal, safe)
 *
 * - Uses constants from config.php / config.production.php:
 *   - OPENAI_API_KEY (string)
 *   - OPENAI_MODEL (string)
 *   - OPENAI_TIMEOUT_SECONDS (int)
 *   - OPENAI_API_BASE (string)
 *
 * Security notes:
 * - Never logs API key or full prompt.
 * - Caller should avoid logging user-provided sensitive content.
 */

declare(strict_types=1);

/**
 * Convert chat-style messages into a single text prompt (best-effort).
 * Keeps role markers to preserve intent without relying on chat endpoints.
 */
function cnx_openai_messages_to_text(array $messages): string {
    $out = [];
    foreach ($messages as $m) {
        if (!is_array($m)) continue;
        $role = trim((string)($m['role'] ?? 'user'));
        $content = (string)($m['content'] ?? '');
        $role = $role !== '' ? strtoupper($role) : 'USER';
        $out[] = $role . ":\n" . $content;
    }
    return implode("\n\n", $out);
}

/**
 * Extract output text from Responses API payload (best-effort across variants).
 */
function cnx_openai_extract_responses_text(array $decoded): string {
    if (!empty($decoded['output_text']) && is_string($decoded['output_text'])) {
        return (string)$decoded['output_text'];
    }
    // Typical structure: output[0].content[0].text
    if (isset($decoded['output']) && is_array($decoded['output'])) {
        foreach ($decoded['output'] as $o) {
            if (!is_array($o)) continue;
            $content = $o['content'] ?? null;
            if (!is_array($content)) continue;
            foreach ($content as $c) {
                if (!is_array($c)) continue;
                if (isset($c['text']) && is_string($c['text'])) return (string)$c['text'];
                if (isset($c['output_text']) && is_string($c['output_text'])) return (string)$c['output_text'];
            }
        }
    }
    // Fallback: some variants use choices/message/content even for responses-like wrappers
    return (string)($decoded['choices'][0]['message']['content'] ?? '');
}

/**
 * @return array{ok:bool,data?:array,error?:string,debug?:array}
 */
function cnx_openai_chat_json(array $messages, array $jsonSchema = null, array $opts = []): array {
    $apiKey = defined('OPENAI_API_KEY') ? (string)OPENAI_API_KEY : '';
    if (trim($apiKey) === '') {
        return [
            'ok' => false,
            'error' => 'OpenAI non configurato (OPENAI_API_KEY mancante)',
            'debug' => ['kind' => 'missing_key'],
        ];
    }

    $base = defined('OPENAI_API_BASE') ? rtrim((string)OPENAI_API_BASE, '/') : 'https://api.openai.com';
    $urlChat = $base . '/v1/chat/completions';
    $urlResponses = $base . '/v1/responses';
    $fallbackModel = 'gpt-4o-mini';
    $model = defined('OPENAI_MODEL') ? (string)OPENAI_MODEL : $fallbackModel;
    // Allow per-call model override (used by AI Hub multi-provider selector).
    if (isset($opts['model']) && trim((string)$opts['model']) !== '') {
        $model = (string)$opts['model'];
    }
    $timeout = isset($opts['timeout_seconds'])
        ? (int)$opts['timeout_seconds']
        : (defined('OPENAI_TIMEOUT_SECONDS') ? (int)OPENAI_TIMEOUT_SECONDS : 20);
    $temperature = isset($opts['temperature']) ? (float)$opts['temperature'] : 0.2;
    $maxTokens = isset($opts['max_tokens']) ? (int)$opts['max_tokens'] : 1200;
    $maxRetries = isset($opts['max_retries']) ? max(0, (int)$opts['max_retries']) : 0;

    // Build chat payload (default path)
    $payloadChat = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
    ];

    // Build responses payload (fallback for non-chat models)
    $payloadResponses = [
        'model' => $model,
        'input' => cnx_openai_messages_to_text($messages),
        'max_output_tokens' => $maxTokens,
    ];

    // Prefer JSON schema when provided (best-effort; older models may ignore)
    if (is_array($jsonSchema) && !empty($jsonSchema)) {
        // Chat Completions: response_format (legacy)
        $payloadChat['response_format'] = [
            'type' => 'json_schema',
            'json_schema' => $jsonSchema,
        ];

        // Responses API: text.format (current)
        $schemaName = trim((string)($jsonSchema['name'] ?? 'response_schema'));
        if ($schemaName === '') $schemaName = 'response_schema';
        $schemaObj = $jsonSchema['schema'] ?? $jsonSchema;
        if (!is_array($schemaObj)) $schemaObj = [];
        $payloadResponses['text'] = [
            'format' => [
                'type' => 'json_schema',
                'name' => $schemaName,
                'schema' => $schemaObj,
                'strict' => true,
            ],
        ];
    } else {
        // Chat Completions: older JSON mode
        $payloadChat['response_format'] = ['type' => 'json_object'];

        // Responses API: json_object format
        $payloadResponses['text'] = [
            'format' => ['type' => 'json_object'],
        ];
    }

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    $attempt = 0;
    $lastDebug = null;

    // Decide initial mode:
    // - Prefer /v1/responses for gpt-5* and o* models (many are not chat-compatible)
    // - Allow explicit override via opts['force_mode'] = 'chat'|'responses'
    $forceMode = isset($opts['force_mode']) ? strtolower(trim((string)$opts['force_mode'])) : '';
    $modelLc = strtolower($model);
    $preferResponses = ($forceMode === 'responses')
        || ($forceMode === '' && (str_starts_with($modelLc, 'gpt-5') || str_starts_with($modelLc, 'o')));
    $preferChat = ($forceMode === 'chat');

    $mode = ($preferChat || !$preferResponses) ? 'chat' : 'responses'; // chat | responses
    $url = ($mode === 'responses') ? $urlResponses : $urlChat;
    $payload = ($mode === 'responses') ? $payloadResponses : $payloadChat;
    while (true) {
        $attempt++;

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'OpenAI client non disponibile (curl_init)'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Transport errors (network, DNS, timeout)
        if ($raw === false || $errno) {
            $lastDebug = ['kind' => 'transport', 'http' => $http, 'errno' => $errno, 'attempt' => $attempt, 'timeout_seconds' => $timeout, 'mode' => $mode];
            error_log('[OPENAI] transport error http=' . $http . ' errno=' . $errno . ' attempt=' . $attempt . ' err=' . $err);
            if ($attempt <= ($maxRetries + 1)) {
                // Exponential-ish backoff (best-effort)
                usleep((int)min(800000, 200000 * $attempt));
                if ($attempt <= $maxRetries) continue;
            }
            $msg = 'Errore comunicazione OpenAI';
            if ($errno === 28) { // CURLE_OPERATION_TIMEDOUT
                $msg = 'Timeout OpenAI (aumentare OPENAI_TIMEOUT_SECONDS o timeout_seconds)';
            }
            return [
                'ok' => false,
                'error' => $msg,
                'debug' => $lastDebug,
            ];
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            $lastDebug = ['kind' => 'non_json', 'http' => $http, 'attempt' => $attempt];
            error_log('[OPENAI] non-JSON response http=' . $http . ' attempt=' . $attempt);
            if ($attempt <= $maxRetries && $http >= 500) {
                usleep((int)min(800000, 200000 * $attempt));
                continue;
            }
            return [
                'ok' => false,
                'error' => 'Risposta OpenAI non valida',
                'debug' => $lastDebug,
            ];
        }

        // HTTP errors
        if ($http < 200 || $http >= 300) {
            $msg = (string)($decoded['error']['message'] ?? 'OpenAI error');
            $lastDebug = ['kind' => 'http_error', 'http' => $http, 'attempt' => $attempt];
            error_log('[OPENAI] http=' . $http . ' attempt=' . $attempt . ' message=' . $msg);

            // If model is not a chat model, switch endpoint to /v1/responses (best-effort)
            if ($mode === 'chat' && $http >= 400 && $http < 500) {
                $low = mb_strtolower($msg, 'UTF-8');
                if (str_contains($low, 'not a chat model') || (str_contains($low, 'chat model') && str_contains($low, 'not supported'))) {
                    $mode = 'responses';
                    $url = $urlResponses;
                    $payload = $payloadResponses;
                    $lastDebug = array_merge($lastDebug, ['switched_to' => 'responses']);
                    // Reset attempts so max_retries applies to the real (responses) call too
                    $attempt = 0;
                    // retry immediately once
                    continue;
                }
            }

            // If model is invalid/unavailable, retry once with fallback model (best-effort)
            if ($http >= 400 && $http < 500 && $model !== $fallbackModel) {
                $low = mb_strtolower($msg, 'UTF-8');
                if (str_contains($low, 'model') && (str_contains($low, 'not found') || str_contains($low, 'does not exist') || str_contains($low, 'invalid'))) {
                    $model = $fallbackModel;
                    $payloadChat['model'] = $model;
                    $payloadResponses['model'] = $model;
                    $payload['model'] = $model;
                    $lastDebug = array_merge($lastDebug, ['fallback_model' => $fallbackModel, 'fallback_used' => true]);
                    // retry immediately once
                    if ($attempt <= ($maxRetries + 1)) {
                        continue;
                    }
                }
            }
            // Retry only on server-side errors
            if ($attempt <= $maxRetries && $http >= 500) {
                usleep((int)min(800000, 200000 * $attempt));
                continue;
            }
            return [
                'ok' => false,
                'error' => 'OpenAI: ' . $msg,
                'debug' => $lastDebug,
            ];
        }

        // Success path
        break;
    }

    $content = $mode === 'responses'
        ? cnx_openai_extract_responses_text($decoded)
        : (string)($decoded['choices'][0]['message']['content'] ?? '');
    if ($content === '') {
        return ['ok' => false, 'error' => 'OpenAI: contenuto vuoto', 'debug' => $lastDebug ?: ['kind' => 'empty_content'] ];
    }

    $json = json_decode($content, true);
    if (!is_array($json)) {
        // Some models may wrap JSON in text; attempt to extract first JSON object
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $maybe = substr($content, $start, $end - $start + 1);
            $json = json_decode($maybe, true);
        }
    }

    if (!is_array($json)) {
        return [
            'ok' => false,
            'error' => 'OpenAI: JSON non parsabile',
            'debug' => ['kind' => 'parse_error', 'http' => $http],
        ];
    }

    return ['ok' => true, 'data' => $json];
}

/**
 * Normalize text for cache hashing: trim, collapse whitespace, lowercase (UTF-8).
 * Deterministic across calls; used as scope-key for the embedding cache.
 */
function cnx_openai_embed_normalize_text(string $text): string {
    $collapsed = preg_replace('/\s+/u', ' ', trim($text));
    if (!is_string($collapsed)) {
        $collapsed = trim($text);
    }
    return mb_strtolower($collapsed, 'UTF-8');
}

/**
 * Append a JSON-line entry to logs/embedding_cache.log (non-blocking).
 */
function cnx_openai_embed_cache_log(array $entry): void {
    try {
        $dir = defined('LOG_PATH') ? (string)LOG_PATH : (__DIR__ . '/../logs');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) return;
        @file_put_contents($dir . '/embedding_cache.log', $line . "\n", FILE_APPEND | LOCK_EX);
    } catch (\Throwable $e) {
        // best-effort, swallow
    }
}

/**
 * Lookup a single embedding in cache. Returns float vector or null on miss.
 * Non-blocking: any DB error is logged and treated as a miss.
 */
function cnx_openai_embed_cache_lookup(string $hash, string $model, ?int &$dimOut = null): ?array {
    if (!class_exists('Database')) return null;
    try {
        $db = \Database::getInstance();
        $row = $db->fetchOne(
            'SELECT vec, dim FROM embedding_cache WHERE text_hash = ? AND model = ? LIMIT 1',
            [$hash, $model]
        );
        if (!is_array($row) || !isset($row['vec'])) return null;
        $expectedDim = (int)($row['dim'] ?? 0);
        $unpacked = @unpack('g*', (string)$row['vec']);
        if (!is_array($unpacked) || empty($unpacked)) {
            error_log('[EMBED_CACHE] unpack failed for hash=' . substr($hash, 0, 12));
            return null;
        }
        $vec = array_values($unpacked);
        if ($expectedDim > 0 && count($vec) !== $expectedDim) {
            error_log('[EMBED_CACHE] dim mismatch hash=' . substr($hash, 0, 12) . ' expected=' . $expectedDim . ' got=' . count($vec));
            return null;
        }
        $dimOut = $expectedDim > 0 ? $expectedDim : count($vec);
        // Update last_hit_at + hit_count (non-blocking, ignore errors)
        try {
            $db->query(
                'UPDATE embedding_cache SET hit_count = hit_count + 1, last_hit_at = NOW() WHERE text_hash = ? AND model = ?',
                [$hash, $model]
            );
        } catch (\Throwable $e) {
            // ignore: stats update is best-effort
        }
        return $vec;
    } catch (\Throwable $e) {
        error_log('[EMBED_CACHE] lookup error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Store embedding in cache. Uses INSERT IGNORE because race between parallel
 * processes computing the same hash is benign.
 */
function cnx_openai_embed_cache_store(string $hash, string $model, array $vec, ?int $tokenCount = null): void {
    if (!class_exists('Database')) return;
    if (empty($vec)) return;
    try {
        $packed = pack('g*', ...array_map('floatval', $vec));
        $dim = count($vec);
        $db = \Database::getInstance();
        $db->query(
            'INSERT IGNORE INTO embedding_cache (text_hash, model, dim, vec, token_count, hit_count, last_hit_at) VALUES (?, ?, ?, ?, ?, 0, NULL)',
            [$hash, $model, $dim, $packed, $tokenCount]
        );
    } catch (\Throwable $e) {
        error_log('[EMBED_CACHE] store error: ' . $e->getMessage());
    }
}

/**
 * OpenAI embeddings (best-effort).
 *
 * Uses persistent DB cache (table `embedding_cache`) to avoid re-calling the
 * OpenAI API for previously-seen (model, normalized_text) pairs. Cache is
 * global by design (deterministic, no PII risk beyond what was already sent
 * to OpenAI). Toggle with constant RAG_EMBEDDING_CACHE_ENABLED in config.php.
 *
 * @param string[] $texts
 * @return array{ok:bool,embeddings?:array<int,array<float>>,error?:string}
 */
function cnx_openai_embed_texts(array $texts, array $opts = []): array {
    $apiKey = defined('OPENAI_API_KEY') ? (string)OPENAI_API_KEY : '';
    if (trim($apiKey) === '') {
        return ['ok' => false, 'error' => 'OpenAI non configurato (OPENAI_API_KEY mancante)'];
    }
    $base = defined('OPENAI_API_BASE') ? rtrim((string)OPENAI_API_BASE, '/') : 'https://api.openai.com';
    $url = $base . '/v1/embeddings';
    $model = defined('OPENAI_EMBEDDING_MODEL') ? (string)OPENAI_EMBEDDING_MODEL : 'text-embedding-3-small';
    if (isset($opts['model']) && trim((string)$opts['model']) !== '') {
        $model = (string)$opts['model'];
    }
    $timeout = isset($opts['timeout_seconds'])
        ? (int)$opts['timeout_seconds']
        : (defined('OPENAI_TIMEOUT_SECONDS') ? (int)OPENAI_TIMEOUT_SECONDS : 20);

    $cacheEnabled = !defined('RAG_EMBEDDING_CACHE_ENABLED') || (bool)RAG_EMBEDDING_CACHE_ENABLED;
    $startedAt = microtime(true);

    $inputs = array_values($texts);
    $inputCount = count($inputs);
    $output = array_fill(0, $inputCount, null);
    $hashes = [];
    $missIndices = [];
    $hits = 0;

    // 1) Cache lookup per input (skip empty strings: pass-through to API behavior)
    foreach ($inputs as $i => $text) {
        $textStr = (string)$text;
        if ($textStr === '') {
            $missIndices[] = $i;
            $hashes[$i] = null;
            continue;
        }
        if (!$cacheEnabled) {
            $missIndices[] = $i;
            $hashes[$i] = null;
            continue;
        }
        $normalized = cnx_openai_embed_normalize_text($textStr);
        $hash = hash('sha256', $normalized);
        $hashes[$i] = $hash;
        $vec = cnx_openai_embed_cache_lookup($hash, $model);
        if ($vec !== null) {
            $output[$i] = $vec;
            $hits++;
        } else {
            $missIndices[] = $i;
        }
    }

    $apiCallMade = 0;

    // 2) Call OpenAI only for misses
    if (!empty($missIndices)) {
        $missTexts = [];
        foreach ($missIndices as $idx) {
            $missTexts[] = (string)$inputs[$idx];
        }
        $payload = [
            'model' => $model,
            'input' => $missTexts,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $apiCallMade = 1;

        if ($resp === false) {
            cnx_openai_embed_cache_log([
                'ts' => date('c'),
                'input_count' => $inputCount,
                'hits' => $hits,
                'misses' => count($missIndices),
                'api_calls' => $apiCallMade,
                'model' => $model,
                'ms' => (int)round((microtime(true) - $startedAt) * 1000),
                'error' => 'curl_error',
            ]);
            return ['ok' => false, 'error' => 'Errore cURL: ' . $err];
        }
        $decoded = json_decode($resp, true);
        if ($code < 200 || $code >= 300) {
            $msg = is_array($decoded) ? (string)($decoded['error']['message'] ?? $decoded['message'] ?? '') : '';
            if ($msg === '') $msg = "HTTP {$code}";
            cnx_openai_embed_cache_log([
                'ts' => date('c'),
                'input_count' => $inputCount,
                'hits' => $hits,
                'misses' => count($missIndices),
                'api_calls' => $apiCallMade,
                'model' => $model,
                'ms' => (int)round((microtime(true) - $startedAt) * 1000),
                'error' => 'http_' . $code,
            ]);
            return ['ok' => false, 'error' => $msg];
        }
        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            cnx_openai_embed_cache_log([
                'ts' => date('c'),
                'input_count' => $inputCount,
                'hits' => $hits,
                'misses' => count($missIndices),
                'api_calls' => $apiCallMade,
                'model' => $model,
                'ms' => (int)round((microtime(true) - $startedAt) * 1000),
                'error' => 'invalid_response',
            ]);
            return ['ok' => false, 'error' => 'Risposta embeddings non valida'];
        }

        // Token count (best-effort, applied uniformly to misses if available)
        $totalTokens = (int)($decoded['usage']['total_tokens'] ?? 0);
        $perItemTokens = ($totalTokens > 0 && count($missIndices) > 0)
            ? (int)round($totalTokens / count($missIndices))
            : null;

        // OpenAI returns embeddings in input order; map back via $missIndices
        foreach ($data as $rowIdx => $row) {
            $emb = $row['embedding'] ?? null;
            if (!is_array($emb)) continue;
            $vec = array_map('floatval', $emb);
            if (!isset($missIndices[$rowIdx])) continue;
            $origIdx = $missIndices[$rowIdx];
            $output[$origIdx] = $vec;
            // Store in cache if we computed a hash for this index
            if ($cacheEnabled && !empty($hashes[$origIdx])) {
                cnx_openai_embed_cache_store($hashes[$origIdx], $model, $vec, $perItemTokens);
            }
        }
    }

    // 3) Validate output: any null entry means missing embedding
    $finalOut = [];
    foreach ($output as $vec) {
        if (!is_array($vec)) {
            cnx_openai_embed_cache_log([
                'ts' => date('c'),
                'input_count' => $inputCount,
                'hits' => $hits,
                'misses' => count($missIndices),
                'api_calls' => $apiCallMade,
                'model' => $model,
                'ms' => (int)round((microtime(true) - $startedAt) * 1000),
                'error' => 'incomplete_output',
            ]);
            return ['ok' => false, 'error' => 'Embeddings vuote'];
        }
        $finalOut[] = $vec;
    }
    if (empty($finalOut)) {
        cnx_openai_embed_cache_log([
            'ts' => date('c'),
            'input_count' => $inputCount,
            'hits' => $hits,
            'misses' => count($missIndices),
            'api_calls' => $apiCallMade,
            'model' => $model,
            'ms' => (int)round((microtime(true) - $startedAt) * 1000),
            'error' => 'empty',
        ]);
        return ['ok' => false, 'error' => 'Embeddings vuote'];
    }

    cnx_openai_embed_cache_log([
        'ts' => date('c'),
        'input_count' => $inputCount,
        'hits' => $hits,
        'misses' => count($missIndices),
        'api_calls' => $apiCallMade,
        'model' => $model,
        'ms' => (int)round((microtime(true) - $startedAt) * 1000),
    ]);

    return ['ok' => true, 'embeddings' => $finalOut];
}
