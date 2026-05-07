<?php
// ================================================================
// HorizonAI — Middlewares
// JWT · CORS · Rate Limiting · Validation · Réponses
// ================================================================
declare(strict_types=1);

// ── CORS ──────────────────────────────────────────────────────
function cors_middleware(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, CORS_ORIGINS, true)) {
        header("Access-Control-Allow-Origin: {$origin}");
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Request-ID');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

// ── JWT ────────────────────────────────────────────────────────
class JWT {
    public static function encode(array $payload): string {
        $h = self::b64(json_encode(['alg'=>'HS256','typ'=>'JWT']));
        $p = self::b64(json_encode($payload));
        $s = self::b64(hash_hmac('sha256', "{$h}.{$p}", JWT_SECRET, true));
        return "{$h}.{$p}.{$s}";
    }

    public static function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$h, $p, $s] = $parts;
        if (!hash_equals(self::b64(hash_hmac('sha256', "{$h}.{$p}", JWT_SECRET, true)), $s)) return null;
        $data = json_decode(self::b64d($p), true);
        if (!$data || (isset($data['exp']) && $data['exp'] < time())) return null;
        return $data;
    }

    public static function access(string $uid, string $role, string $lang): string {
        return self::encode(['sub'=>$uid,'role'=>$role,'lang'=>$lang,'iat'=>time(),'exp'=>time()+JWT_EXPIRY_ACCESS,'type'=>'access']);
    }

    public static function refresh(string $uid): string {
        return self::encode(['sub'=>$uid,'iat'=>time(),'exp'=>time()+JWT_EXPIRY_REFRESH,'type'=>'refresh']);
    }

    private static function b64(string $d): string { return rtrim(strtr(base64_encode($d),'+/','-_'),'='); }
    private static function b64d(string $d): string { return base64_decode(strtr($d,'-_','+/')); }
}


function require_auth(array $roles = []): array {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($h, 'Bearer ')) response_error(401, 'auth_required', 'Token manquant');
    $payload = JWT::decode(substr($h, 7));
    if (!$payload || ($payload['type'] ?? '') !== 'access') response_error(401, 'token_invalid', 'Token invalide ou expiré');
    if ($roles && !in_array($payload['role'], $roles, true)) response_error(403, 'forbidden', 'Accès non autorisé');
    
    try {
        DB::query("SELECT set_config('app.current_user_id', ?, false)", [$payload['sub']]);
        DB::query("SELECT set_config('app.current_user_role', ?, false)", [$payload['role']]);
    } catch (\Throwable) {}
    return $payload;
}

// ── Rate limiting (table PG) ───────────────────────────────────
function check_rate_limit(string $key, int $max, int $window = 60): void {
    try {
        DB::query("
            INSERT INTO rate_limit_cache (key, count, window_start) VALUES (?, 1, datetime('now'))
            ON CONFLICT (key) DO UPDATE SET
                count        = CASE WHEN rate_limit_cache.window_start < datetime('now', '-' || ? || ' seconds') THEN 1 ELSE rate_limit_cache.count + 1 END,
                window_start = CASE WHEN rate_limit_cache.window_start < datetime('now', '-' || ? || ' seconds') THEN datetime('now') ELSE rate_limit_cache.window_start END
        ", [$key, $window, $window]);
        $row = DB::row("SELECT count FROM rate_limit_cache WHERE key = ?", [$key]);
        if ($row && (int)$row['count'] > $max) response_error(429, 'rate_limit', "Trop de requêtes. Réessayez dans {$window}s");
    } catch (\Throwable) { /* silencieux si table absente */ }
}

// ── Validator ─────────────────────────────────────────────────
class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) { $this->data = $data; }

    public function required(string ...$fields): static {
        foreach ($fields as $f) {
            if (!isset($this->data[$f]) || $this->data[$f] === '' || $this->data[$f] === null) {
                $this->errors[$f] = "'{$f}' est obligatoire";
            }
        }
        return $this;
    }

    public function phone(string $field): static {
        $v = preg_replace('/[\s\-()]/', '', $this->data[$field] ?? '');
        if ($v && !preg_match('/^\+?[0-9]{8,15}$/', $v)) $this->errors[$field] = 'Numéro invalide';
        return $this;
    }

    public function in(string $field, array $allowed): static {
        $v = $this->data[$field] ?? null;
        if ($v !== null && !in_array($v, $allowed, true)) $this->errors[$field] = "Valeur non autorisée";
        return $this;
    }

    public function max(string $field, int $max): static {
        $v = $this->data[$field] ?? null;
        if ($v !== null && mb_strlen((string)$v) > $max) $this->errors[$field] = "Maximum {$max} caractères";
        return $this;
    }

    public function fails(): bool { return !empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function validated(): array { return $this->data; }
}

// ── Réponses JSON ──────────────────────────────────────────────
function response_json(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function response_success(mixed $data, string $msg = 'OK', int $code = 200): never {
    response_json(['success' => true, 'message' => $msg, 'data' => $data], $code);
}

function response_error(int $code, string $error, string $msg, array $details = []): never {
    $b = ['success' => false, 'error' => $error, 'message' => $msg];
    if ($details) $b['details'] = $details;
    response_json($b, $code);
}

function get_body(): array {
    $d = json_decode(file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}

function sanitize(string $s): string {
    return htmlspecialchars(strip_tags(trim($s)), ENT_QUOTES, 'UTF-8');
}
