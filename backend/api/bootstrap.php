<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/middleware.php';
require_once __DIR__ . '/../services/NLPEngine.php';
require_once __DIR__ . '/../services/MatchingEngine.php';
require_once __DIR__ . '/../services/PromptBuilder.php';
require_once __DIR__ . '/../services/ClaudeClient.php';
require_once __DIR__ . '/../services/IAOrchestrator.php';
require_once __DIR__ . '/../routes/auth.php';
require_once __DIR__ . '/../routes/profile.php';
require_once __DIR__ . '/../routes/plan.php';
require_once __DIR__ . '/../routes/other.php';
require_once __DIR__ . '/../routes/admin.php';
require_once __DIR__ . '/../services/InterviewAgent.php';
require_once __DIR__ . '/../routes/interview.php';
require_once __DIR__ . '/../routes/devices.php';

set_exception_handler(function(\Throwable $e): void {
    error_log('[UNCAUGHT] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>'server_error','message'=> APP_ENV!=='production' ? $e->getMessage() : 'Erreur serveur']);
    }
});
