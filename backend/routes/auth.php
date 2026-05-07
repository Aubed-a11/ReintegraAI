<?php
declare(strict_types=1);

class AuthController {

    public static function send_otp(): never {
        $body = get_body();
        $v = (new Validator($body))->required('phone')->phone('phone');
        if ($v->fails()) response_error(422, 'validation_error', 'Données invalides', $v->errors());

        $phone = preg_replace('/[\s\-()]/', '', $body['phone']);
        check_rate_limit("otp:{$phone}", RATE_LIMIT_AUTH, 900);

        $phone_hash = password_hash($phone, PASSWORD_BCRYPT, ['cost' => 12]);
        $otp        = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires    = date('Y-m-d H:i:s', time() + 600);

        DB::query("
            INSERT INTO users (phone_hash, otp_code, otp_expires_at, otp_attempts)
            VALUES (?, ?, ?, 0)
            ON CONFLICT (phone_hash) DO UPDATE
            SET otp_code=EXCLUDED.otp_code, otp_expires_at=EXCLUDED.otp_expires_at, otp_attempts=0, updated_at=datetime('now')
        ", [$phone_hash, $otp, $expires]);

        self::send_sms($phone, "HorizonAI — Code OTP: {$otp} (valable 10 minutes)");
        DB::query("INSERT INTO audit_logs(action,metadata,ip_address) VALUES('USER_CREATED',?,?)",
            [json_encode(['phone_suffix'=>substr($phone,-4)]), $_SERVER['REMOTE_ADDR']??null]);

        $debug = APP_ENV !== 'production' ? ['otp_debug' => $otp] : [];
        response_success(array_merge(['sms_sent' => !empty(TWILIO_SID)], $debug), 'Code OTP envoyé');
    }

    public static function register(): never {
        $body = get_body();
        $v = (new Validator($body))
            ->required('email','password','phone')
            ->max('first_name',60)->max('last_name',60)->max('email',120)->max('ville_retour',100)->max('gender',40);
        if ($v->fails()) response_error(422, 'validation_error', 'Données invalides', $v->errors());
        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) response_error(422, 'validation_error', 'Email invalide', ['email' => 'Email invalide']);
        if (!preg_match('/^\+?[0-9]{8,15}$/', preg_replace('/[\s\-()]/', '', $body['phone']))) response_error(422, 'validation_error', 'Téléphone invalide', ['phone' => 'Téléphone invalide']);
        if (strlen($body['password']) < 8) response_error(422, 'validation_error', 'Mot de passe trop court', ['password' => '8 caractères minimum']);

        $email = strtolower(trim($body['email']));
        $phone = preg_replace('/[\s\-()]/', '', $body['phone']);
        if (DB::row("SELECT id FROM users WHERE LOWER(email)=LOWER(?)", [$email])) response_error(409, 'email_exists', 'Email déjà utilisé');
        if (DB::row("SELECT id FROM users WHERE phone=?", [$phone])) response_error(409, 'phone_exists', 'Téléphone déjà utilisé');

        $passwordHash = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $phoneHash = password_hash($phone, PASSWORD_BCRYPT, ['cost' => 12]);
        $lang = in_array($body['lang'] ?? 'fr', ['fr','en','ar','wo','bm','ha','ff','tzm'], true) ? $body['lang'] : 'fr';

        $userId = DB::transaction(function() use ($body, $email, $phone, $passwordHash, $phoneHash) {
            $userId = DB::insert('users', [
                'email' => $email,
                'password_hash' => $passwordHash,
                'phone' => $phone,
                'phone_hash' => $phoneHash,
                'first_name' => sanitize($body['first_name'] ?? ''),
                'last_name' => sanitize($body['last_name'] ?? ''),
                'gender' => sanitize($body['gender'] ?? ''),
                'age' => isset($body['age']) && is_numeric($body['age']) ? intval($body['age']) : null,
            ]);

            $competences = array_slice(array_map(fn($s) => substr(sanitize((string)$s), 0, 50), is_array($body['competences'] ?? null) ? $body['competences'] : []), 0, 15);
            $vulnerabilites = array_filter(is_array($body['vulnerabilites'] ?? null) ? $body['vulnerabilites'] : [], fn($v) => is_string($v) && $v !== '');
            $trancheAge = isset($body['age']) && is_numeric($body['age']) ? self::derive_age_range(intval($body['age'])) : '18-24';

            DB::insert('profiles', [
                'user_id' => $userId,
                'pays_origine' => sanitize($body['pays_origine'] ?? ''),
                'ville_retour' => sanitize($body['ville_retour'] ?? ''),
                'tranche_age' => $trancheAge,
                'niveau_etudes' => sanitize($body['niveau_etudes'] ?? ''),
                'annees_experience' => sanitize($body['annees_experience'] ?? ''),
                'situation_familiale' => sanitize($body['situation_familiale'] ?? ''),
                'competences' => json_encode($competences, JSON_UNESCAPED_UNICODE),
                'langue' => sanitize($body['langue'] ?? ''),
                'objectifs' => sanitize($body['objectifs'] ?? ''),
                'besoins' => sanitize($body['besoins'] ?? ''),
                'contraintes' => sanitize($body['contraintes'] ?? ''),
                'sante' => sanitize($body['sante'] ?? ''),
                'enfants' => isset($body['enfants']) && is_numeric($body['enfants']) ? intval($body['enfants']) : null,
                'vulnerabilites' => '{'.implode(',', array_values($vulnerabilites)).'}',
                'completion_pct' => 0,
            ]);

            return $userId;
        });

        $access = JWT::access($userId, 'MIGRANT', $lang);
        $refresh = JWT::refresh($userId);
        DB::query("UPDATE users SET refresh_token=?, last_login_at=datetime('now'), lang_pref=? WHERE id=?", [$refresh, $lang, $userId]);
        DB::query("INSERT INTO audit_logs(user_id,action,ip_address) VALUES(?,?,?)", [$userId, 'USER_REGISTER', $_SERVER['REMOTE_ADDR'] ?? null]);

        $user = DB::row("SELECT id,role,email,first_name,last_name,phone,age,gender,lang_pref,last_login_at,created_at FROM users WHERE id=?", [$userId]);
        response_success(['access_token' => $access, 'refresh_token' => $refresh, 'expires_in' => JWT_EXPIRY_ACCESS, 'user' => $user], 'Inscription réussie');
    }

    public static function login(): never {
        $body = get_body();
        $v = (new Validator($body))->required('email','password');
        if ($v->fails()) response_error(422, 'validation_error', 'Données invalides', $v->errors());
        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) response_error(422, 'validation_error', 'Email invalide', ['email' => 'Email invalide']);

        $user = DB::row("SELECT * FROM users WHERE LOWER(email)=LOWER(?)", [trim($body['email'])]);
        if (!$user || !password_verify($body['password'], $user['password_hash'] ?? '')) {
            response_error(401, 'credentials_invalid', 'Email ou mot de passe incorrect');
        }
        if (!$user['is_active']) response_error(403, 'account_disabled', 'Compte désactivé');

        $lang = in_array($body['lang'] ?? $user['lang_pref'] ?? 'fr', ['fr','en','ar','wo','bm','ha','ff','tzm'], true) ? ($body['lang'] ?? $user['lang_pref']) : 'fr';
        $access = JWT::access($user['id'], $user['role'], $lang);
        $refresh = JWT::refresh($user['id']);
        DB::query("UPDATE users SET refresh_token=?, last_login_at=datetime('now'), lang_pref=? WHERE id=?", [$refresh, $lang, $user['id']]);
        DB::query("INSERT INTO audit_logs(user_id,action,ip_address,user_agent) VALUES(?,?,?,?)", [$user['id'], 'USER_LOGIN', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);

        response_success(['access_token' => $access, 'refresh_token' => $refresh, 'expires_in' => JWT_EXPIRY_ACCESS, 'user' => ['id'=>$user['id'],'role'=>$user['role'],'lang'=>$lang,'email'=>$user['email'],'first_name'=>$user['first_name'],'last_name'=>$user['last_name'],'phone'=>$user['phone'],'age'=>$user['age'],'gender'=>$user['gender']]], 'Connexion réussie');
    }

    private static function derive_age_range(int $age): string {
        if ($age < 25) return '18-24';
        if ($age < 35) return '25-34';
        if ($age < 45) return '35-44';
        if ($age < 55) return '45-54';
        return '55+';
    }

    public static function verify_otp(): never {
        $body = get_body();
        $v = (new Validator($body))->required('phone','otp')->phone('phone')->max('otp',6);
        if ($v->fails()) response_error(422, 'validation_error', 'Données invalides', $v->errors());

        $phone = preg_replace('/[\s\-()]/', '', $body['phone']);
        $otp   = trim($body['otp']);
        $lang  = in_array($body['lang']??'fr', ['fr','en','ar','wo','bm','ha','ff','tzm']) ? $body['lang'] : 'fr';

        // Chercher l'utilisateur par comparaison bcrypt
        $users = DB::rows("SELECT * FROM users WHERE otp_expires_at > datetime('now') AND otp_attempts < 5");
        $user  = null;
        foreach ($users as $u) { if (password_verify($phone, $u['phone_hash'])) { $user = $u; break; } }

        if (!$user) response_error(401, 'otp_invalid', 'Code OTP invalide ou expiré');
        if (!$user['is_active']) response_error(403, 'account_disabled', 'Compte désactivé');

        DB::query("UPDATE users SET otp_attempts=otp_attempts+1 WHERE id=?", [$user['id']]);
        if (!hash_equals($user['otp_code'], $otp)) {
            $rem = 4 - (int)$user['otp_attempts'];
            response_error(401, 'otp_wrong', "Code incorrect. {$rem} tentative(s) restante(s)");
        }

        $access  = JWT::access($user['id'], $user['role'], $lang);
        $refresh = JWT::refresh($user['id']);
        DB::query("UPDATE users SET otp_code=NULL,otp_expires_at=NULL,otp_attempts=0,refresh_token=?,last_login_at=datetime('now'),lang_pref=? WHERE id=?",
            [$refresh, $lang, $user['id']]);
        DB::query("INSERT INTO audit_logs(user_id,action,ip_address,user_agent) VALUES(?,?,?,?)",
            [$user['id'],'USER_LOGIN',$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);

        response_success(['access_token'=>$access,'refresh_token'=>$refresh,'expires_in'=>JWT_EXPIRY_ACCESS,'user'=>['id'=>$user['id'],'role'=>$user['role'],'lang'=>$lang]], 'Connexion réussie');
    }

    public static function refresh(): never {
        $body = get_body();
        if (empty($body['refresh_token'])) response_error(400, 'missing_token', 'refresh_token requis');
        $payload = JWT::decode($body['refresh_token']);
        if (!$payload || ($payload['type']??'') !== 'refresh') response_error(401, 'token_invalid', 'Token invalide');
        $user = DB::row("SELECT * FROM users WHERE id=?", [$payload['sub']]);
        if (!$user || !$user['is_active']) response_error(401, 'not_found', 'Utilisateur introuvable');
        if (!hash_equals($user['refresh_token']??'', $body['refresh_token'])) response_error(401, 'token_mismatch', 'Token révoqué');
        $access  = JWT::access($user['id'], $user['role'], $user['lang_pref']);
        $refresh = JWT::refresh($user['id']);
        DB::query("UPDATE users SET refresh_token=? WHERE id=?", [$refresh, $user['id']]);
        response_success(['access_token'=>$access,'refresh_token'=>$refresh,'expires_in'=>JWT_EXPIRY_ACCESS]);
    }

    public static function logout(): never {
        $auth = require_auth();
        DB::query("UPDATE users SET refresh_token=NULL WHERE id=?", [$auth['sub']]);
        DB::query("INSERT INTO audit_logs(user_id,action,ip_address) VALUES(?,?,?)",[$auth['sub'],'USER_LOGOUT',$_SERVER['REMOTE_ADDR']??null]);
        response_success(null, 'Déconnecté');
    }

    public static function me(): never {
        $auth = require_auth();
        $user = DB::row("SELECT id,role,email,first_name,last_name,phone,age,gender,lang_pref,last_login_at,created_at FROM users WHERE id=?", [$auth['sub']]);
        if (!$user) response_error(404, 'not_found', 'Utilisateur introuvable');
        $profile = DB::row("SELECT id,pays_origine,ville_retour,tranche_age,situation_familiale,niveau_etudes,annees_experience,competences,langue,objectifs,besoins,contraintes,sante,enfants,vulnerabilites,completion_pct,is_validated FROM profiles WHERE user_id=?", [$auth['sub']]);
        response_success(['user'=>$user,'profile'=>$profile]);
    }

    private static function send_sms(string $to, string $msg): bool {
        if (!TWILIO_SID || !TWILIO_TOKEN) { error_log("[DEV SMS] To:{$to} — {$msg}"); return false; }
        $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/".TWILIO_SID."/Messages.json");
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_USERPWD=>TWILIO_SID.':'.TWILIO_TOKEN,CURLOPT_POSTFIELDS=>http_build_query(['To'=>$to,'From'=>TWILIO_FROM,'Body'=>$msg]),CURLOPT_TIMEOUT=>10]);
        curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        return $code === 201;
    }
}
