<?php
declare(strict_types=1);

class ProfileController {
    // Pays d'origine : pays natal du migrant (vers lequel il retourne)
    private static array $PAYS  = ['Sénégal','Côte d\'Ivoire','Niger','Mali','Guinée','Burkina Faso','Cameroun','Togo','Bénin','Mauritanie','Maroc','Algérie'];
    // Pays d'accueil : pays où la migration a été effectuée (depuis lequel il repart)
    private static array $PAYS_ACCUEIL = [
        'Maroc','Algérie','Libye','Tunisie','Mauritanie','Niger',
        'France','Espagne','Italie','Belgique','Allemagne','Portugal',
        'Turquie','Égypte','Autre',
    ];
    private static array $AGES  = ['18-24','25-34','35-44','45-54','55+'];
    private static array $VULNS = ['SANTE_CHRONIQUE','FEMME_ENCEINTE','MINEUR_NON_ACCOMPAGNE','VICTIME_TRAITE','HANDICAP','SANTE_URGENTE','AUCUNE'];
    private static array $ALPHA = ['OUI','NON','PARTIEL'];

    public static function create(): never {
        $auth = require_auth(['MIGRANT']);
        $body = get_body();
        if (DB::row("SELECT id FROM profiles WHERE user_id=?",[$auth['sub']])) response_error(409,'profile_exists','Profil existant. Utilisez PUT.');
        $v=(new Validator($body))->required('pays_origine','ville_retour','tranche_age','niveau_etudes')->in('tranche_age',self::$AGES)->max('ville_retour',100)->max('langue',100);
        if($v->fails()) response_error(422,'validation_error','Données invalides',$v->errors());

        $comps = array_slice(array_map(fn($s)=>substr(sanitize((string)$s),0,50), is_array($body['competences']??null)?$body['competences']:[]), 0, 15);
        $vulns = array_filter(is_array($body['vulnerabilites']??null)?$body['vulnerabilites']:[], fn($v)=>in_array($v,self::$VULNS));
        $pct   = self::calc_completion($body);

        $alpha = in_array($body['alphabetisation'] ?? 'OUI', self::$ALPHA) ? $body['alphabetisation'] : 'OUI';
        $infop = substr(sanitize($body['competences_informelles'] ?? ''), 0, 500);

        $pays_accueil = in_array($body['pays_accueil'] ?? '', self::$PAYS_ACCUEIL) ? $body['pays_accueil'] : sanitize($body['pays_accueil'] ?? '');

        $id = DB::insert('profiles', [
            'user_id'=>$auth['sub'], 'pays_origine'=>sanitize($body['pays_origine']), 'pays_accueil'=>$pays_accueil,
            'ville_retour'=>sanitize($body['ville_retour']),
            'tranche_age'=>$body['tranche_age'], 'niveau_etudes'=>sanitize($body['niveau_etudes']),
            'annees_experience'=>sanitize($body['annees_experience']??''), 'situation_familiale'=>sanitize($body['situation_familiale']??''),
            'competences'=>json_encode($comps,JSON_UNESCAPED_UNICODE),
            'alphabetisation'=>$alpha, 'competences_informelles'=>$infop,
            'langue'=>sanitize($body['langue']??''), 'objectifs'=>sanitize($body['objectifs']??''),
            'besoins'=>sanitize($body['besoins']??''), 'contraintes'=>sanitize($body['contraintes']??''),
            'sante'=>sanitize($body['sante']??''), 'enfants'=>isset($body['enfants']) ? intval($body['enfants']) : null,
            'vulnerabilites'=>'{'.implode(',',array_values($vulns)).'}',
            'completion_pct'=>$pct,
        ]);
        DB::query("INSERT INTO audit_logs(user_id,action,entity_type,entity_id) VALUES(?,?,?,?)",[$auth['sub'],'PROFILE_CREATED','profiles',$id]);
        response_success(self::fmt(DB::row("SELECT * FROM profiles WHERE id=?",[$id])), 'Profil créé', 201);
    }

    public static function get(): never {
        $auth = require_auth();
        $uid  = (in_array($auth['role'],['AGENT','SUPERVISEUR','ADMIN']) && isset($_GET['user_id'])) ? $_GET['user_id'] : $auth['sub'];
        $p    = DB::row("SELECT * FROM profiles WHERE user_id=?",[$uid]);
        if(!$p) response_error(404,'not_found','Profil introuvable');
        response_success(self::fmt($p));
    }

    public static function update(): never {
        $auth = require_auth(['MIGRANT']);
        $body = get_body();
        $p    = DB::row("SELECT id FROM profiles WHERE user_id=?",[$auth['sub']]);
        if(!$p) response_error(404,'not_found','Profil introuvable');

        $sets=[]; $params=[];
        foreach(['pays_origine','pays_accueil','ville_retour','niveau_etudes','annees_experience','situation_familiale'] as $f) {
            if(isset($body[$f])){$sets[]="{$f}=?";$params[]=sanitize($body[$f]);}
        }
        if(isset($body['tranche_age'])&&in_array($body['tranche_age'],self::$AGES)){$sets[]="tranche_age=?";$params[]=$body['tranche_age'];}
        if(isset($body['competences'])&&is_array($body['competences'])){$sets[]="competences=?";$params[]=json_encode(array_slice(array_map('sanitize',$body['competences']),0,15),JSON_UNESCAPED_UNICODE);}
        if(isset($body['langue'])){$sets[]="langue=?";$params[]=sanitize($body['langue']);}
        if(isset($body['objectifs'])){$sets[]="objectifs=?";$params[]=sanitize($body['objectifs']);}
        if(isset($body['besoins'])){$sets[]="besoins=?";$params[]=sanitize($body['besoins']);}
        if(isset($body['contraintes'])){$sets[]="contraintes=?";$params[]=sanitize($body['contraintes']);}
        if(isset($body['sante'])){$sets[]="sante=?";$params[]=sanitize($body['sante']);}
        if(isset($body['enfants'])){$sets[]="enfants=?";$params[]=intval($body['enfants']);}
        if(isset($body['vulnerabilites'])&&is_array($body['vulnerabilites'])){$vulns=array_filter($body['vulnerabilites'],fn($v)=>in_array($v,self::$VULNS));$sets[]="vulnerabilites=?";$params[]='{'.implode(',',array_values($vulns)).'}';}
        if(isset($body['alphabetisation'])&&in_array($body['alphabetisation'],self::$ALPHA)){$sets[]="alphabetisation=?";$params[]=$body['alphabetisation'];}
        if(isset($body['competences_informelles'])){$sets[]="competences_informelles=?";$params[]=substr(sanitize($body['competences_informelles']),0,500);}
        if(empty($sets)) response_error(400,'no_changes','Rien à mettre à jour');

        $merged = array_merge(DB::row("SELECT * FROM profiles WHERE id=?",[$p['id']])??[], $body);
        $sets[]="completion_pct=?"; $params[]=self::calc_completion($merged);
        $params[]=$p['id'];
        DB::query("UPDATE profiles SET ".implode(',',$sets)." WHERE id=?", $params);
        DB::query("INSERT INTO audit_logs(user_id,action,entity_type,entity_id) VALUES(?,?,?,?)",[$auth['sub'],'PROFILE_UPDATED','profiles',$p['id']]);
        response_success(self::fmt(DB::row("SELECT * FROM profiles WHERE id=?",[$p['id']])), 'Profil mis à jour');
    }

    public static function delete(): never {
        $auth = require_auth(['MIGRANT']);
        $p    = DB::row("SELECT id FROM profiles WHERE user_id=?",[$auth['sub']]);
        if(!$p) response_error(404,'not_found','Profil introuvable');
        DB::transaction(function() use($auth,$p){
            DB::query("UPDATE plans SET profile_id=NULL WHERE profile_id=?",[$p['id']]);
            DB::query("DELETE FROM profiles WHERE id=?",[$p['id']]);
            DB::query("UPDATE users SET is_active=FALSE WHERE id=?",[$auth['sub']]);
            DB::query("INSERT INTO audit_logs(user_id,action,entity_type,entity_id) VALUES(?,?,?,?)",[$auth['sub'],'DATA_DELETED','profiles',$p['id']]);
        });
        response_success(null,'Données supprimées (RGPD)');
    }

    private static function calc_completion(array $d): int {
        $fields = [
            'pays_origine'       => 8,
            'pays_accueil'       => 8,
            'ville_retour'       => 8,
            'tranche_age'        => 8,
            'niveau_etudes'      => 8,
            'situation_familiale'=> 8,
            'annees_experience'  => 8,
            'langue'             => 8,
            'objectifs'          => 5,
            'besoins'            => 5,
            'contraintes'        => 5,
            'sante'              => 5,
            'enfants'            => 5,
        ];
        $total = 0;
        foreach ($fields as $f => $w) {
            $v = $d[$f] ?? null;
            if ($v && $v !== '[]' && $v !== '{}' && $v !== '') $total += $w;
        }
        // Compétences formelles OU informelles = 15%
        $comps   = $d['competences'] ?? null;
        $infopps = $d['competences_informelles'] ?? null;
        $has_comps = ($comps && $comps !== '[]' && $comps !== '') || ($infopps && $infopps !== '');
        if ($has_comps) $total += 15;
        return min(100, $total);
    }

    public static function fmt(array $p): array {
        if (isset($p['competences']) && is_string($p['competences'])) {
            $p['competences'] = json_decode($p['competences'], true) ?: [];
        }
        if (isset($p['vulnerabilites']) && is_string($p['vulnerabilites'])) {
            $p['vulnerabilites'] = array_values(array_filter(explode(',', trim($p['vulnerabilites'], '{}'))));
        }
        return $p;
    }
}
