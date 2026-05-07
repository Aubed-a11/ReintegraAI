<?php
declare(strict_types=1);

class AdminController {

    // GET /admin/migrants → Liste des migrants inscrits
    public static function list_migrants(): never {
        require_auth(['ADMIN','SUPERVISEUR']);
        
        $migrants = DB::rows("
            SELECT 
                u.id, u.email, u.first_name, u.last_name, u.phone, u.role, u.created_at,
                p.pays_origine, p.ville_retour, p.completion_pct, p.tranche_age, p.niveau_etudes,
                p.competences, p.vulnerabilites,
                CASE WHEN pl.id IS NOT NULL THEN 1 ELSE 0 END AS has_plan,
                pl.statut AS plan_status, pl.score_ia, pl.created_at AS plan_created_at
            FROM users u
            LEFT JOIN profiles p ON u.id = p.user_id
            LEFT JOIN plans pl ON p.id = pl.profile_id AND pl.statut IN ('PENDING','VALIDATED')
            WHERE u.role = 'MIGRANT'
            ORDER BY u.created_at DESC
        ");

        // Formater les données
        foreach ($migrants as &$m) {
            $m['competences'] = json_decode($m['competences'] ?? '[]', true) ?: [];
            $m['vulnerabilites'] = array_values(array_filter(explode(',', trim($m['vulnerabilites'] ?? '{}', '{}'))));
            $m['has_plan'] = (bool)$m['has_plan'];
        }

        response_success($migrants);
    }

    // GET /admin/plans → Liste des plans générés
    public static function list_plans(): never {
        require_auth(['ADMIN','SUPERVISEUR']);
        
        $plans = DB::rows("
            SELECT 
                pl.id, pl.score_ia, pl.statut, pl.created_at, pl.resume_global,
                u.first_name, u.last_name, u.email,
                p.pays_origine, p.ville_retour, p.completion_pct,
                COUNT(po.id) AS opportunities_count
            FROM plans pl
            JOIN profiles p ON pl.profile_id = p.id
            JOIN users u ON p.user_id = u.id
            LEFT JOIN plan_opportunities po ON pl.id = po.plan_id
            GROUP BY pl.id, pl.score_ia, pl.statut, pl.created_at, pl.resume_global, 
                     u.first_name, u.last_name, u.email, p.pays_origine, p.ville_retour, p.completion_pct
            ORDER BY pl.created_at DESC
        ");

        response_success($plans);
    }

    // GET /admin/dashboard → Statistiques pour le dashboard admin
    public static function dashboard_stats(): never {
        require_auth(['ADMIN','SUPERVISEUR']);
        
        // Statistiques générales
        $general = DB::row("
            SELECT 
                COUNT(DISTINCT u.id) AS total_migrants,
                COUNT(DISTINCT CASE WHEN pl.id IS NOT NULL THEN u.id END) AS migrants_with_plans,
                COUNT(DISTINCT pl.id) AS total_plans,
                COUNT(DISTINCT CASE WHEN pl.statut = 'VALIDATED' THEN pl.id END) AS validated_plans,
                COUNT(DISTINCT CASE WHEN pl.statut = 'PENDING' THEN pl.id END) AS pending_plans,
                ROUND(AVG(pl.score_ia), 1) AS avg_score,
                COUNT(DISTINCT CASE WHEN ia.id IS NOT NULL THEN ia.id END) AS total_sessions
            FROM users u
            LEFT JOIN profiles p ON u.id = p.user_id
            LEFT JOIN plans pl ON p.id = pl.profile_id
            LEFT JOIN ia_sessions ia ON ia.user_id = u.id
            WHERE u.role = 'MIGRANT'
        ");

        // Plans par statut
        $plans_by_status = DB::rows("
            SELECT statut, COUNT(*) AS count
            FROM plans
            GROUP BY statut
            ORDER BY count DESC
        ");

        // Migrants par pays
        $migrants_by_country = DB::rows("
            SELECT p.pays_origine, COUNT(*) AS count
            FROM profiles p
            JOIN users u ON p.user_id = u.id
            WHERE u.role = 'MIGRANT'
            GROUP BY p.pays_origine
            ORDER BY count DESC
            LIMIT 10
        ");

        // Sessions IA aujourd'hui
        $today_sessions = DB::row("
            SELECT COUNT(*) AS count
            FROM ia_sessions
            WHERE date(created_at) = date('now')
        ");

        // Plans générés cette semaine
        $weekly_plans = DB::row("
            SELECT COUNT(*) AS count
            FROM plans
            WHERE created_at >= datetime('now', '-7 days')
        ");

        // Taux de completion des profils
        $completion_stats = DB::rows("
            SELECT 
                CASE 
                    WHEN completion_pct >= 80 THEN '80-100%'
                    WHEN completion_pct >= 60 THEN '60-79%'
                    WHEN completion_pct >= 40 THEN '40-59%'
                    WHEN completion_pct >= 20 THEN '20-39%'
                    ELSE '0-19%'
                END AS range,
                COUNT(*) AS count
            FROM profiles
            GROUP BY 
                CASE 
                    WHEN completion_pct >= 80 THEN '80-100%'
                    WHEN completion_pct >= 60 THEN '60-79%'
                    WHEN completion_pct >= 40 THEN '40-59%'
                    WHEN completion_pct >= 20 THEN '20-39%'
                    ELSE '0-19%'
                END
            ORDER BY range
        ");

        response_success([
            'general' => $general,
            'plans_by_status' => $plans_by_status,
            'migrants_by_country' => $migrants_by_country,
            'today_sessions' => $today_sessions['count'] ?? 0,
            'weekly_plans' => $weekly_plans['count'] ?? 0,
            'completion_stats' => $completion_stats,
        ]);
    }
}