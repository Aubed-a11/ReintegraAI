<?php
declare(strict_types=1);

// ── Opportunités ──────────────────────────────────────────────
class OpportunityController {
    public static function list(): never {
        require_auth();
        $where = "WHERE is_active=TRUE"; $params = [];
        if (!empty($_GET['pays'])) { $where.=" AND pays=?"; $params[]=sanitize($_GET['pays']); }
        if (!empty($_GET['type'])) { $where.=" AND type=?"; $params[]=$_GET['type']; }
        if (!empty($_GET['q']))    { $where.=" AND (titre LIKE ? OR description LIKE ?)"; $q='%'.sanitize($_GET['q']).'%'; $params[]=$q; $params[]=$q; }
        $limit=(int)min(50,max(1,(int)($_GET['limit']??20))); $offset=(max(1,(int)($_GET['page']??1))-1)*$limit;
        $rows=DB::rows("SELECT id,pays,ville,type,titre,description,organisme,contact_tel,cout_estime,devise,duree_semaines,tags FROM opportunities {$where} ORDER BY type,titre LIMIT ? OFFSET ?", array_merge($params,[$limit,$offset]));
        response_success($rows);
    }
    public static function countries(): never {
        require_auth();
        response_success(DB::rows("SELECT pays,COUNT(*) AS nb FROM opportunities WHERE is_active=TRUE GROUP BY pays ORDER BY pays"));
    }
    public static function create(): never {
        $auth=require_auth(['ADMIN','SUPERVISEUR']); $body=get_body();
        $v=(new Validator($body))->required('pays','type','titre','description','organisme');
        if($v->fails()) response_error(422,'validation_error','Données invalides',$v->errors());
        $id=DB::insert('opportunities',['pays'=>sanitize($body['pays']),'type'=>$body['type'],'titre'=>sanitize($body['titre']),'description'=>sanitize($body['description']),'organisme'=>sanitize($body['organisme']),'contact_tel'=>sanitize($body['contact_tel']??''),'cout_estime'=>isset($body['cout_estime'])?(float)$body['cout_estime']:null,'devise'=>sanitize($body['devise']??'FCFA'),'duree_semaines'=>isset($body['duree_semaines'])?(int)$body['duree_semaines']:null,'tags'=>'{'.implode(',',array_map('sanitize',$body['tags']??[])).'}','is_active'=>true]);
        response_success(DB::row("SELECT * FROM opportunities WHERE id=?",[$id]),'Créé',201);
    }
    public static function update(string $id): never {
        require_auth(['ADMIN','SUPERVISEUR']); $body=get_body();
        $allowed=['pays','type','titre','description','organisme','contact_tel','cout_estime','devise','duree_semaines','is_active'];
        $sets=[];$params=[];
        foreach($allowed as $f){if(array_key_exists($f,$body)){$sets[]="{$f}=?";$params[]=is_string($body[$f])?sanitize($body[$f]):$body[$f];}}
        if(empty($sets)) response_error(400,'no_changes','Rien à mettre à jour');
        $params[]=$id; DB::query("UPDATE opportunities SET ".implode(',',$sets)." WHERE id=?",$params);
        response_success(DB::row("SELECT * FROM opportunities WHERE id=?",[$id]),'Mis à jour');
    }
    public static function delete(string $id): never {
        require_auth(['ADMIN']);
        DB::query("UPDATE opportunities SET is_active=FALSE WHERE id=?",[$id]);
        response_success(null,'Désactivé');
    }
}

// ── Stats ─────────────────────────────────────────────────────
class StatsController {
    public static function global(): never {
        require_auth(['AGENT','SUPERVISEUR','ADMIN']);
        $global = DB::row("SELECT SUM(CASE WHEN statut='PENDING' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN statut='VALIDATED' THEN 1 ELSE 0 END) AS validated, SUM(CASE WHEN statut='REJECTED' THEN 1 ELSE 0 END) AS rejected, ROUND(AVG(score_ia),1) AS avg_score, COUNT(*) AS total FROM plans");
        $by_country = DB::rows("SELECT pr.pays_origine,COUNT(*) AS plans,ROUND(AVG(p.score_ia),1) AS avg_score,SUM(CASE WHEN p.statut='VALIDATED' THEN 1 ELSE 0 END) AS validated FROM plans p JOIN profiles pr ON p.profile_id=pr.id GROUP BY pr.pays_origine ORDER BY plans DESC");
        $ia = DB::row("SELECT COUNT(*) AS calls,SUM(tokens_input+tokens_output) AS tokens,ROUND(SUM(cout_usd),4) AS cost_usd,ROUND(AVG(latence_ms)) AS avg_latence FROM ia_sessions WHERE success=TRUE");
        response_success(['global'=>$global,'by_country'=>$by_country,'ia'=>$ia]);
    }
    public static function agent(): never {
        $auth=require_auth(['AGENT','SUPERVISEUR','ADMIN']);
        response_success(DB::row("SELECT SUM(CASE WHEN statut='VALIDATED' THEN 1 ELSE 0 END) AS validated, SUM(CASE WHEN statut='REJECTED' THEN 1 ELSE 0 END) AS rejected, ROUND(AVG(score_ia),1) AS avg_score, COUNT(*) AS total FROM plans WHERE agent_id=?",[$auth['sub']]));
    }
}

// ── Notifications ─────────────────────────────────────────────
class NotificationController {
    public static function list(): never {
        $auth=require_auth();
        $rows=DB::rows("SELECT id,channel,title,body,data,statut,sent_at,read_at,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50",[$auth['sub']]);
        $unread=(int)(DB::row("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id=? AND statut!='READ'",[$auth['sub']])['cnt']??0);
        response_success(['notifications'=>$rows,'unread'=>$unread]);
    }
    public static function mark_read(string $id): never {
        $auth=require_auth();
        DB::query("UPDATE notifications SET statut='READ',read_at=datetime('now') WHERE id=? AND user_id=?",[$id,$auth['sub']]);
        response_success(null,'Lu');
    }
    public static function read_all(): never {
        $auth=require_auth();
        DB::query("UPDATE notifications SET statut='READ',read_at=datetime('now') WHERE user_id=? AND statut!='READ'",[$auth['sub']]);
        response_success(null,'Toutes lues');
    }
}

// ── Follow-up post-retour ─────────────────────────────────────
class FollowUpController {
    public static function create(): never {
        $auth=require_auth(['AGENT','SUPERVISEUR']); $body=get_body();
        $v=(new Validator($body))->required('plan_id','avancement_pct');
        if($v->fails()) response_error(422,'validation_error','Invalide',$v->errors());
        if(!DB::row("SELECT id FROM plans WHERE id=?",[$body['plan_id']])) response_error(404,'plan_not_found','Plan introuvable');
        $id=DB::insert('follow_up',['plan_id'=>$body['plan_id'],'agent_id'=>$auth['sub'],'date_contact'=>$body['date_contact']??date('Y-m-d'),'avancement_pct'=>(int)$body['avancement_pct'],'emploi_trouve'=>isset($body['emploi_trouve'])?(bool)$body['emploi_trouve']:null,'logement_stable'=>isset($body['logement_stable'])?(bool)$body['logement_stable']:null,'sante_ok'=>isset($body['sante_ok'])?(bool)$body['sante_ok']:null,'commentaire'=>sanitize($body['commentaire']??''),'prochaine_action'=>sanitize($body['prochaine_action']??''),'prochaine_date'=>$body['prochaine_date']??null]);
        response_success(DB::row("SELECT * FROM follow_up WHERE id=?",[$id]),'Suivi enregistré',201);
    }
    public static function list_by_plan(string $plan_id): never {
        require_auth();
        response_success(DB::rows("SELECT f.*,u.role AS agent_role FROM follow_up f LEFT JOIN users u ON f.agent_id=u.id WHERE f.plan_id=? ORDER BY f.date_contact DESC",[$plan_id]));
    }
}
