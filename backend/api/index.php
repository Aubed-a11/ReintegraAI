<?php
// ================================================================
// HorizonAI — Router principal
// Toutes les requêtes entrent ici via .htaccess
// ================================================================
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-API-Version: ' . APP_VERSION);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
if (APP_ENV === 'production') header('Strict-Transport-Security: max-age=31536000');

cors_middleware();

// ── Parser URL ────────────────────────────────────────────────
$method   = $_SERVER['REQUEST_METHOD'];
$uri      = rtrim(preg_replace('#^/api#', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/') ?: '/';

// ── Servir les fichiers statiques (PDFs générés) ──────────────
if ($method === 'GET' && str_starts_with($uri, '/storage/')) {
    $filePath = __DIR__ . $uri;
    if (file_exists($filePath) && is_file($filePath)) {
        $ext  = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = $ext === 'pdf' ? 'application/pdf' : 'application/octet-stream';
        header("Content-Type: {$mime}");
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        readfile($filePath);
        exit;
    }
}
$segments = array_values(array_filter(explode('/', $uri)));

$s0 = $segments[0] ?? '';
$s1 = $segments[1] ?? '';
$s2 = $segments[2] ?? '';

$uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
$s1id = (bool)preg_match($uuid, $s1);

try {

    // ── AUTH ──────────────────────────────────────────────────
    if ($s0 === 'auth') {
        match("{$method}:{$s1}") {
            'POST:send-otp'   => AuthController::send_otp(),
            'POST:verify-otp' => AuthController::verify_otp(),
            'POST:register'   => AuthController::register(),
            'POST:login'      => AuthController::login(),
            'POST:refresh'    => AuthController::refresh(),
            'DELETE:logout'   => AuthController::logout(),
            'GET:me'          => AuthController::me(),
            'GET:'            => response_success(['endpoints' => [
                'POST /api/auth/send-otp',
                'POST /api/auth/verify-otp',
                'POST /api/auth/register',
                'POST /api/auth/login',
                'POST /api/auth/refresh',
                'DELETE /api/auth/logout',
                'GET /api/auth/me',
            ]], 'Auth API routes'),
            default           => not_found($method, $uri),
        };
    }

    // ── PROFIL ────────────────────────────────────────────────
    elseif ($s0 === 'profile') {
        match($method) {
            'POST'         => ProfileController::create(),
            'GET'          => ProfileController::get(),
            'PUT','PATCH'  => ProfileController::update(),
            'DELETE'       => ProfileController::delete(),
            default        => not_found($method, $uri),
        };
    }

    // ── PLAN ─────────────────────────────────────────────────
    // POST /plan/generate → IAOrchestrator::generate_plan() → NLP+Match+Prompt+Claude+DB
    elseif ($s0 === 'plan') {
        if ($s1 === 'generate' && $method === 'POST')            PlanController::generate();
        elseif (empty($s1) && $method === 'GET')                 PlanController::get_mine();
        elseif ($s1id && $s2 === 'validate' && $method === 'PUT') PlanController::validate($s1);
        elseif ($s1id && $s2 === 'reject'   && $method === 'PUT') PlanController::reject($s1);
        elseif ($s1id && $s2 === 'pdf'      && $method === 'GET') PlanController::export_pdf($s1);
        elseif ($s1id && empty($s2)         && $method === 'GET') PlanController::get_by_id($s1);
        else not_found($method, $uri);
    }

    // ── PLANS LIST ────────────────────────────────────────────
    elseif ($s0 === 'plans' && $method === 'GET') PlanController::list_pending();

    // ── CHAT IA ───────────────────────────────────────────────
    // POST /chat → IAOrchestrator::chat() → NLP+Claude+DB
    elseif ($s0 === 'chat' && $method === 'POST') {
        $auth = require_auth(['MIGRANT','AGENT','SUPERVISEUR']);
        $body = get_body();
        if (empty($body['message'])) response_error(400, 'missing_message', '"message" requis');
        check_rate_limit("chat:{$auth['sub']}", 30, 3600);

        $history = array_slice(is_array($body['history'] ?? null) ? $body['history'] : [], -10);
        $result  = IAOrchestrator::chat($auth['sub'], trim($body['message']), $history, $body['lang'] ?? null);

        if (!$result['success']) response_error(502, $result['error'], $result['message'] ?? 'Erreur IA');
        response_success(['message'=>$result['message'],'lang'=>$result['lang'],'latence_ms'=>$result['latence_ms']]);
    }

    // ── OPPORTUNITÉS ──────────────────────────────────────────
    elseif ($s0 === 'opportunities') {
        if ($s1 === 'countries' && $method === 'GET') OpportunityController::countries();
        elseif (empty($s1) && $method === 'GET')      OpportunityController::list();
        elseif (empty($s1) && $method === 'POST')     OpportunityController::create();
        elseif ($s1id && $method === 'PUT')           OpportunityController::update($s1);
        elseif ($s1id && $method === 'DELETE')        OpportunityController::delete($s1);
        else not_found($method, $uri);
    }

    // ── STATS ─────────────────────────────────────────────────
    elseif ($s0 === 'stats') {
        match("{$method}:{$s1}") {
            'GET:global' => StatsController::global(),
            'GET:agent'  => StatsController::agent(),
            default      => not_found($method, $uri),
        };
    }

    // ── NOTIFICATIONS ─────────────────────────────────────────
    elseif ($s0 === 'notifications') {
        if (empty($s1) && $method === 'GET')                          NotificationController::list();
        elseif ($s1 === 'read-all' && $method === 'PUT')              NotificationController::read_all();
        elseif ($s1id && $s2 === 'read' && $method === 'PUT')         NotificationController::mark_read($s1);
        else not_found($method, $uri);
    }

    // ── INTERVIEW KIOSQUE ─────────────────────────────────────────
    elseif ($s0 === 'interview') {
        if ($s1 === 'start' && $method === 'POST')                InterviewController::start();
        elseif ($s1 === 'sync'   && $method === 'POST')           InterviewController::sync();
        elseif ($s1id && $s2 === 'step'    && $method === 'POST') InterviewController::step($s1);
        elseif ($s1id && $s2 === 'abandon' && $method === 'POST') InterviewController::abandon($s1);
        elseif ($s1id && $s2 === 'rdv'     && $method === 'GET')  InterviewController::rdv($s1);
        elseif ($s1id && empty($s2)        && $method === 'GET')  InterviewController::get($s1);
        else not_found($method, $uri);
    }

    // ── ADMIN ─────────────────────────────────────────────────
    elseif ($s0 === 'admin') {
        $auth = require_auth(['ADMIN','SUPERVISEUR']);
        match("{$method}:{$s1}") {
            'GET:migrants'     => AdminController::list_migrants(),
            'GET:plans'        => AdminController::list_plans(),
            'GET:dashboard'    => AdminController::dashboard_stats(),
            default            => not_found($method, $uri),
        };
    }

    // ── DEVICES (bornes kiosque) ───────────────────────────────
    elseif ($s0 === 'devices') {
        if (empty($s1) && $method === 'GET')                            DeviceController::list();
        elseif (empty($s1) && $method === 'POST')                       DeviceController::create();
        elseif ($s1 === 'ping'  && $method === 'POST')                  DeviceController::ping();
        elseif ($s1 === 'stats' && $method === 'GET')                   DeviceController::stats();
        elseif ($s1id && empty($s2)           && $method === 'GET')     DeviceController::get($s1);
        elseif ($s1id && empty($s2)           && $method === 'PUT')     DeviceController::update($s1);
        elseif ($s1id && empty($s2)           && $method === 'DELETE')  DeviceController::delete($s1);
        elseif ($s1id && $s2 === 'rotate-token' && $method === 'POST')  DeviceController::rotate_token($s1);
        else not_found($method, $uri);
    }

    // ── FOLLOW-UP ─────────────────────────────────────────────
    elseif ($s0 === 'follow-up') {
        if (empty($s1) && $method === 'POST')        FollowUpController::create();
        elseif ($s1id && empty($s2) && $method === 'GET') FollowUpController::list_by_plan($s1);
        else not_found($method, $uri);
    }

    // ── HEALTH ────────────────────────────────────────────────
    elseif ($s0 === 'health' && $method === 'GET') {
        response_success([
            'status'   => 'ok',
            'version'  => APP_VERSION,
            'env'      => APP_ENV,
            'db'       => DB::ping(),
            'ia'       => !empty(CLAUDE_API_KEY),
            'sms'      => !empty(TWILIO_SID),
            'time'     => date('c'),
            'services' => [
                'NLPEngine'      => class_exists('NLPEngine'),
                'MatchingEngine' => class_exists('MatchingEngine'),
                'PromptBuilder'  => class_exists('PromptBuilder'),
                'ClaudeClient'   => class_exists('ClaudeClient'),
                'IAOrchestrator' => class_exists('IAOrchestrator'),
            ],
        ], 'HorizonAI opérationnel');
    }

    // ── ROOT ──────────────────────────────────────────────────
    elseif ($s0 === '' && $method === 'GET') {
        response_success(['api'=>APP_NAME,'version'=>APP_VERSION,'endpoints'=>[
            'POST /api/auth/send-otp','POST /api/auth/verify-otp','POST /api/auth/register','POST /api/auth/login','POST /api/auth/refresh','DELETE /api/auth/logout','GET /api/auth/me','GET /api/auth',
            'POST/GET/PUT/DELETE /api/profile',
            'POST /api/plan/generate  ← IAOrchestrator → NLP+Match+Prompt+Claude+DB',
            'GET /api/plan','GET /api/plan/{id}','GET /api/plans/pending',
            'PUT /api/plan/{id}/validate','PUT /api/plan/{id}/reject','GET /api/plan/{id}/pdf',
            'POST /api/chat  ← IAOrchestrator → NLP+Claude+DB',
            'GET /api/opportunities','GET /api/opportunities/countries',
            'GET /api/stats/global','GET /api/stats/agent',
            'GET /api/notifications','PUT /api/notifications/read-all',
            'POST /api/follow-up','GET /api/follow-up/{plan_id}',
            'GET /api/health',
        ]], 'HorizonAI API — OIM Maroc × BAIC');
    }

    else not_found($method, $uri);

} catch (\PDOException $e) {
    error_log('[DB] ' . $e->getMessage());
    $message = APP_ENV !== 'production'
        ? 'Base de données indisponible. ' . $e->getMessage()
        : 'Base de données indisponible. Vérifiez la configuration SQLite.';
    response_error(503, 'database_error', $message);
} catch (\Throwable $e) {
    error_log('[SERVER] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    response_error(500, 'server_error', APP_ENV !== 'production' ? $e->getMessage() : 'Erreur serveur interne');
}

function not_found(string $m, string $u): never {
    response_error(404, 'not_found', "Route {$m} {$u} introuvable", ['hint' => 'GET /api pour la liste des routes']);
}
