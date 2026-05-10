<?php
declare(strict_types=1);

$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}

define('APP_ENV',     getenv('APP_ENV') ?: 'development');
define('APP_VERSION', '1.1.0');
define('APP_NAME',    'HorizonAI API');
define('DB_FILE',     __DIR__ . '/../database/reintegraai.db');
define('JWT_SECRET',  getenv('JWT_SECRET') ?: 'reintegraai_dev_secret_2026_baic_oim_xk9p');
define('JWT_EXPIRY_ACCESS',  900);
define('JWT_EXPIRY_REFRESH', 604800);
define('CLAUDE_API_KEY',    getenv('CLAUDE_API_KEY') ?: '');
define('CLAUDE_MODEL',      'claude-sonnet-4-6');
define('CLAUDE_MAX_TOKENS', 2048);
define('CLAUDE_API_URL',    'https://api.anthropic.com/v1/messages');
define('TWILIO_SID',   getenv('TWILIO_SID')   ?: '');
define('TWILIO_TOKEN', getenv('TWILIO_TOKEN') ?: '');
define('TWILIO_FROM',  getenv('TWILIO_FROM')  ?: '+212600000000');
define('CORS_ORIGINS', ['http://localhost:3000','http://localhost:5173','http://127.0.0.1:3000']);
define('RATE_LIMIT_AUTH', 5);
define('RATE_LIMIT_API',  60);
define('RATE_LIMIT_IA',   10);

class DB {
    private static ?PDO $pdo = null;

    public static function conn(): PDO {
        if (self::$pdo === null) {
            $dir = dirname(DB_FILE);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            self::$pdo = new PDO('sqlite:' . DB_FILE, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec("PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA synchronous=NORMAL;");
            self::init_schema();
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $p = []): PDOStatement {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($p);
        return $stmt;
    }

    public static function row(string $sql, array $p = []): ?array {
        $r = self::query($sql, $p)->fetch();
        return $r ?: null;
    }

    public static function rows(string $sql, array $p = []): array {
        return self::query($sql, $p)->fetchAll();
    }

    public static function insert(string $table, array $data): string {
        $id   = self::uuid();
        $data = array_merge(['id' => $id], $data);
        $cols = implode(',', array_keys($data));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        self::query("INSERT INTO {$table} ({$cols}) VALUES ({$phs})", array_values($data));
        return $id;
    }

    public static function transaction(callable $fn): mixed {
        self::conn()->beginTransaction();
        try { $r = $fn(); self::conn()->commit(); return $r; }
        catch (\Throwable $e) { self::conn()->rollBack(); throw $e; }
    }

    public static function ping(): bool {
        try { return (bool)self::row("SELECT 1 AS ok"); } catch (\Throwable) { return false; }
    }

    private static function seed_country_profiles(): void {
        if ((int)(self::row("SELECT COUNT(*) AS n FROM country_profiles")['n'] ?? 0) > 0) return;
        $pays = [
            'Sénégal' => [
                'secteurs' => [['secteur'=>'Agriculture/Maraîchage','sans_qualification'=>true],['secteur'=>'Pêche','sans_qualification'=>true],['secteur'=>'Commerce informel','sans_qualification'=>true],['secteur'=>'Artisanat/Couture','sans_qualification'=>false],['secteur'=>'BTP/Maçonnerie','sans_qualification'=>true]],
                'informalite'=>97,'pib'=>1600,'chomage'=>17,
                'micro'  =>[['nom'=>'BNDE','type'=>'Banque','plafond'=>500000,'contact'=>'+221338203000'],['nom'=>'CMS','type'=>'Mutuelle','plafond'=>200000,'contact'=>'+221338220800'],['nom'=>'MEC Sahel','type'=>'Coopérative','plafond'=>150000,'contact'=>'+221338415422']],
                'alpha'  =>[['nom'=>'ADEF','gratuit'=>true,'zones'=>'Dakar, Thiès'],['nom'=>'Tostan','gratuit'=>true,'zones'=>'Zones rurales'],['nom'=>'Centres OIM','gratuit'=>true,'zones'=>'Dakar']],
                'infopps'=>[['titre'=>'Maraîchage péri-urbain','secteur'=>'Agriculture'],['titre'=>'Vente de produits locaux au marché','secteur'=>'Commerce'],['titre'=>'Aide-maçon / manœuvre BTP','secteur'=>'BTP']],
                'oim'    =>['bureau'=>'OIM Dakar','tel'=>'+221338251012','adresse'=>'Rue Carnot, Dakar'],
            ],
            "Côte d'Ivoire" => [
                'secteurs'=>[['secteur'=>'Commerce/Petit commerce','sans_qualification'=>true],['secteur'=>'Agriculture/Cacao','sans_qualification'=>true],['secteur'=>'Transport mototaxi','sans_qualification'=>true],['secteur'=>'Restauration/Street food','sans_qualification'=>true],['secteur'=>'Artisanat','sans_qualification'=>false]],
                'informalite'=>88,'pib'=>2300,'chomage'=>9,
                'micro'  =>[['nom'=>'BACI Microfinance','type'=>'IMF','plafond'=>300000,'contact'=>'+22520312020'],['nom'=>'SIB','type'=>'Banque','plafond'=>500000,'contact'=>'+22520311000'],['nom'=>'ADVANS CI','type'=>'IMF','plafond'=>250000,'contact'=>'+22527246400']],
                'alpha'  =>[['nom'=>'DNAE','gratuit'=>true,'zones'=>'Abidjan, Bouaké'],['nom'=>'OIM Abidjan','gratuit'=>true,'zones'=>'Abidjan']],
                'infopps'=>[['titre'=>'Commerce ambulant denrées','secteur'=>'Commerce'],['titre'=>'Plantation familiale cacao','secteur'=>'Agriculture'],['titre'=>'Mototaxi (Woro-Woro)','secteur'=>'Transport']],
                'oim'    =>['bureau'=>'OIM Abidjan','tel'=>'+22520259727','adresse'=>'Cocody, Abidjan'],
            ],
            'Niger' => [
                'secteurs'=>[['secteur'=>'Agriculture/Élevage','sans_qualification'=>true],['secteur'=>'Commerce transfrontalier','sans_qualification'=>true],['secteur'=>'Artisanat cuir/bijoux','sans_qualification'=>false],['secteur'=>'Transport chamelier','sans_qualification'=>true]],
                'informalite'=>96,'pib'=>590,'chomage'=>13,
                'micro'  =>[['nom'=>'ASUSU','type'=>'Mutuelle','plafond'=>100000,'contact'=>'+22720724444'],['nom'=>'KOKARI','type'=>'IMF','plafond'=>80000,'contact'=>'+22720733610']],
                'alpha'  =>[['nom'=>'MEC Niger','gratuit'=>true,'zones'=>'Niamey'],['nom'=>'OIM Niamey','gratuit'=>true,'zones'=>'Niamey, Agadez']],
                'infopps'=>[['titre'=>'Élevage de petits ruminants','secteur'=>'Agriculture'],['titre'=>'Commerce céréales/mil','secteur'=>'Commerce']],
                'oim'    =>['bureau'=>'OIM Niamey','tel'=>'+22720736969','adresse'=>'Plateau, Niamey'],
            ],
            'Mali' => [
                'secteurs'=>[['secteur'=>'Agriculture/Élevage','sans_qualification'=>true],['secteur'=>'Artisanat/Couture/Bogolan','sans_qualification'=>false],['secteur'=>'Commerce','sans_qualification'=>true],['secteur'=>'Mines artisanales (orpaillage)','sans_qualification'=>true]],
                'informalite'=>93,'pib'=>850,'chomage'=>10,
                'micro'  =>[['nom'=>'Kafo Jiginew','type'=>'Coopérative','plafond'=>200000,'contact'=>'+22320229090'],['nom'=>'BNDA','type'=>'Banque','plafond'=>500000,'contact'=>'+22320229700']],
                'alpha'  =>[['nom'=>'DNAFLA','gratuit'=>true,'zones'=>'Bamako, Mopti'],['nom'=>'OIM Mali','gratuit'=>true,'zones'=>'Bamako']],
                'infopps'=>[['titre'=>'Maraîchage bord fleuve','secteur'=>'Agriculture'],['titre'=>'Couture/Broderie bogolan','secteur'=>'Artisanat']],
                'oim'    =>['bureau'=>'OIM Bamako','tel'=>'+22320213356','adresse'=>'Hamdallaye, Bamako'],
            ],
            'Guinée' => [
                'secteurs'=>[['secteur'=>'Agriculture/Maraîchage','sans_qualification'=>true],['secteur'=>'Commerce','sans_qualification'=>true],['secteur'=>'Mines (main-d\'œuvre)','sans_qualification'=>true],['secteur'=>'Pêche','sans_qualification'=>true]],
                'informalite'=>92,'pib'=>1050,'chomage'=>6,
                'micro'  =>[['nom'=>'Crédit Rural Guinée','type'=>'Réseau','plafond'=>150000,'contact'=>'+224621000000'],['nom'=>'PRIDE Finance','type'=>'IMF','plafond'=>200000,'contact'=>'+224622000000']],
                'alpha'  =>[['nom'=>'DNAE Guinée','gratuit'=>true,'zones'=>'Conakry, Kindia'],['nom'=>'OIM Conakry','gratuit'=>true,'zones'=>'Conakry']],
                'infopps'=>[['titre'=>'Maraîchage familial','secteur'=>'Agriculture'],['titre'=>'Collecte/revente produits forêt','secteur'=>'Commerce']],
                'oim'    =>['bureau'=>'OIM Conakry','tel'=>'+224622199199','adresse'=>'Kaloum, Conakry'],
            ],
            'Burkina Faso' => [
                'secteurs'=>[['secteur'=>'Agriculture/Coton','sans_qualification'=>true],['secteur'=>'Artisanat bronze/cuir','sans_qualification'=>false],['secteur'=>'Commerce informel','sans_qualification'=>true],['secteur'=>'Mines artisanales','sans_qualification'=>true]],
                'informalite'=>95,'pib'=>820,'chomage'=>5,
                'micro'  =>[['nom'=>'RCPB','type'=>'Réseau','plafond'=>300000,'contact'=>'+22625340400'],['nom'=>'Faîtière UMSGF','type'=>'Union','plafond'=>150000,'contact'=>'+22625310000']],
                'alpha'  =>[['nom'=>'DGEFTP','gratuit'=>true,'zones'=>'Ouagadougou, Bobo'],['nom'=>'OIM Ouaga','gratuit'=>true,'zones'=>'Ouagadougou']],
                'infopps'=>[['titre'=>'Élevage volailles','secteur'=>'Agriculture'],['titre'=>'Transformation karité/noix','secteur'=>'Artisanat']],
                'oim'    =>['bureau'=>'OIM Ouagadougou','tel'=>'+22625300400','adresse'=>'Ouaga 2000, Burkina'],
            ],
            'Cameroun' => [
                'secteurs'=>[['secteur'=>'Agriculture/Cacao-Café','sans_qualification'=>true],['secteur'=>'Commerce','sans_qualification'=>true],['secteur'=>'BTP','sans_qualification'=>true],['secteur'=>'Restauration','sans_qualification'=>true]],
                'informalite'=>85,'pib'=>1700,'chomage'=>4,
                'micro'  =>[['nom'=>'CamCCUL','type'=>'Coopérative','plafond'=>500000,'contact'=>'+237222221919'],['nom'=>'MC2','type'=>'Mutuelle','plafond'=>300000,'contact'=>'+237222311000']],
                'alpha'  =>[['nom'=>'MINESEC','gratuit'=>true,'zones'=>'Douala, Yaoundé'],['nom'=>'OIM Yaoundé','gratuit'=>true,'zones'=>'Yaoundé']],
                'infopps'=>[['titre'=>'Culture maraîchère','secteur'=>'Agriculture'],['titre'=>'Vente produits transformés','secteur'=>'Commerce']],
                'oim'    =>['bureau'=>'OIM Yaoundé','tel'=>'+237222204500','adresse'=>'Bastos, Yaoundé'],
            ],
            'Togo' => [
                'secteurs'=>[['secteur'=>'Commerce/Port de Lomé','sans_qualification'=>true],['secteur'=>'Agriculture','sans_qualification'=>true],['secteur'=>'Artisanat','sans_qualification'=>false],['secteur'=>'Phosphate (manœuvre)','sans_qualification'=>true]],
                'informalite'=>90,'pib'=>950,'chomage'=>7,
                'micro'  =>[['nom'=>'FUCEC Togo','type'=>'Réseau','plafond'=>200000,'contact'=>'+228222126969'],['nom'=>'WAGES','type'=>'IMF','plafond'=>150000,'contact'=>'+228222125151']],
                'alpha'  =>[['nom'=>'DAPEP','gratuit'=>true,'zones'=>'Lomé, Kara'],['nom'=>'OIM Lomé','gratuit'=>true,'zones'=>'Lomé']],
                'infopps'=>[['titre'=>'Commerce transfrontalier','secteur'=>'Commerce'],['titre'=>'Élevage porcin/avicole','secteur'=>'Agriculture']],
                'oim'    =>['bureau'=>'OIM Lomé','tel'=>'+228222101010','adresse'=>'Lomé, Togo'],
            ],
            'Bénin' => [
                'secteurs'=>[['secteur'=>'Commerce/Marché','sans_qualification'=>true],['secteur'=>'Agriculture/Coton','sans_qualification'=>true],['secteur'=>'Transport','sans_qualification'=>true],['secteur'=>'Artisanat','sans_qualification'=>false]],
                'informalite'=>91,'pib'=>1400,'chomage'=>2,
                'micro'  =>[['nom'=>'CLCAM','type'=>'Coopérative','plafond'=>300000,'contact'=>'+22921313131'],['nom'=>'PAPME','type'=>'Agence','plafond'=>500000,'contact'=>'+22921303030']],
                'alpha'  =>[['nom'=>'DDCMP','gratuit'=>true,'zones'=>'Cotonou, Porto-Novo'],['nom'=>'OIM Cotonou','gratuit'=>true,'zones'=>'Cotonou']],
                'infopps'=>[['titre'=>'Mototaxi Zémidjan','secteur'=>'Transport'],['titre'=>'Mareyage/vente poisson','secteur'=>'Commerce']],
                'oim'    =>['bureau'=>'OIM Cotonou','tel'=>'+22921310909','adresse'=>'Cotonou, Bénin'],
            ],
            'Mauritanie' => [
                'secteurs'=>[['secteur'=>'Pêche','sans_qualification'=>true],['secteur'=>'Élevage nomade','sans_qualification'=>true],['secteur'=>'Commerce','sans_qualification'=>true],['secteur'=>'BTP','sans_qualification'=>true]],
                'informalite'=>80,'pib'=>1800,'chomage'=>10,
                'micro'  =>[['nom'=>'PROCAPEC','type'=>'Réseau','plafond'=>200000,'contact'=>'+22245291919'],['nom'=>'GAFAT','type'=>'Mutuelle','plafond'=>100000,'contact'=>'+22245290000']],
                'alpha'  =>[['nom'=>'ONS Alphabétisation','gratuit'=>true,'zones'=>'Nouakchott, Nouadhibou'],['nom'=>'OIM Nouakchott','gratuit'=>true,'zones'=>'Nouakchott']],
                'infopps'=>[['titre'=>'Pêche artisanale côtière','secteur'=>'Pêche'],['titre'=>'Commerce bétail','secteur'=>'Élevage']],
                'oim'    =>['bureau'=>'OIM Nouakchott','tel'=>'+22245251515','adresse'=>'Tevragh-Zeina, Nouakchott'],
            ],
            'Maroc' => [
                'secteurs'=>[['secteur'=>'BTP/Maçonnerie','sans_qualification'=>true],['secteur'=>'Agriculture saisonnière','sans_qualification'=>true],['secteur'=>'Commerce informel','sans_qualification'=>true],['secteur'=>'Artisanat/Zellige','sans_qualification'=>false],['secteur'=>'Tourisme (guide, porteur)','sans_qualification'=>true]],
                'informalite'=>65,'pib'=>3700,'chomage'=>12,
                'micro'  =>[['nom'=>'Al Amana','type'=>'IMF','plafond'=>50000,'contact'=>'+212537686868'],['nom'=>'Fondation Banque Populaire','type'=>'Fondation','plafond'=>30000,'contact'=>'+212522200000'],['nom'=>'INMAA','type'=>'IMF','plafond'=>40000,'contact'=>'+212522810000']],
                'alpha'  =>[['nom'=>'Département Alphabétisation','gratuit'=>true,'zones'=>'Tout le Maroc'],['nom'=>'AREF','gratuit'=>true,'zones'=>'Régions'],['nom'=>'OIM Rabat','gratuit'=>true,'zones'=>'Rabat, Casablanca']],
                'infopps'=>[['titre'=>'Aide-maçon chantier','secteur'=>'BTP'],['titre'=>'Cueillette saisonnière olives/agrumes','secteur'=>'Agriculture'],['titre'=>'Artisan zellige/poterie Fès','secteur'=>'Artisanat']],
                'oim'    =>['bureau'=>'OIM Rabat','tel'=>'+212537657272','adresse'=>'Hay Riad, Rabat'],
            ],
            'Algérie' => [
                'secteurs'=>[['secteur'=>'BTP','sans_qualification'=>true],['secteur'=>'Agriculture','sans_qualification'=>true],['secteur'=>'Commerce','sans_qualification'=>true],['secteur'=>'Mécanique auto (apprenti)','sans_qualification'=>true]],
                'informalite'=>55,'pib'=>4200,'chomage'=>12,
                'micro'  =>[['nom'=>'ANGEM','type'=>'Agence','plafond'=>100000,'contact'=>'+213021739393'],['nom'=>'ANSEJ','type'=>'Agence','plafond'=>500000,'contact'=>'+213021630000']],
                'alpha'  =>[['nom'=>'ONALFA','gratuit'=>true,'zones'=>'Tout le territoire'],['nom'=>'OIM Alger','gratuit'=>true,'zones'=>'Alger, Oran']],
                'infopps'=>[['titre'=>'Manœuvre chantier BTP','secteur'=>'BTP'],['titre'=>'Agriculture saharienne (palmeraie)','secteur'=>'Agriculture']],
                'oim'    =>['bureau'=>'OIM Alger','tel'=>'+213021691600','adresse'=>'El Biar, Alger'],
            ],
        ];
        foreach ($pays as $nom => $d) {
            self::$pdo->prepare(
                'INSERT OR IGNORE INTO country_profiles (pays,secteurs_porteurs,taux_informalite,pib_par_habitant,taux_chomage,micro_finance,structures_alpha,opportunites_informelles,ressources_oim) VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([
                $nom,
                json_encode($d['secteurs'], JSON_UNESCAPED_UNICODE),
                $d['informalite'],
                $d['pib'],
                $d['chomage'],
                json_encode($d['micro'], JSON_UNESCAPED_UNICODE),
                json_encode($d['alpha'], JSON_UNESCAPED_UNICODE),
                json_encode($d['infopps'], JSON_UNESCAPED_UNICODE),
                json_encode($d['oim'], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public static function uuid(): string {
        $d = random_bytes(16);
        $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
        $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    private static function init_schema(): void {
        self::$pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY, phone_hash TEXT UNIQUE,
            email TEXT UNIQUE, password_hash TEXT,
            phone TEXT,
            first_name TEXT, last_name TEXT,
            gender TEXT, age INTEGER,
            role TEXT NOT NULL DEFAULT 'MIGRANT', lang_pref TEXT NOT NULL DEFAULT 'fr',
            is_active INTEGER NOT NULL DEFAULT 1,
            otp_code TEXT, otp_expires_at TEXT, otp_attempts INTEGER NOT NULL DEFAULT 0,
            refresh_token TEXT, last_login_at TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS profiles (
            id TEXT PRIMARY KEY, user_id TEXT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
            pays_origine TEXT NOT NULL, ville_retour TEXT NOT NULL,
            tranche_age TEXT NOT NULL, situation_familiale TEXT,
            niveau_etudes TEXT NOT NULL, annees_experience TEXT,
            competences TEXT NOT NULL DEFAULT '[]',
            langue TEXT,
            objectifs TEXT,
            besoins TEXT,
            contraintes TEXT,
            sante TEXT,
            enfants INTEGER,
            vulnerabilites TEXT NOT NULL DEFAULT '[]',
            completion_pct INTEGER NOT NULL DEFAULT 0, is_validated INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS opportunities (
            id TEXT PRIMARY KEY, pays TEXT NOT NULL, ville TEXT, type TEXT NOT NULL,
            titre TEXT NOT NULL, description TEXT NOT NULL, organisme TEXT,
            contact_tel TEXT, contact_web TEXT, cout_estime REAL, devise TEXT DEFAULT 'FCFA',
            duree_semaines INTEGER, tags TEXT DEFAULT '[]', raw_data TEXT DEFAULT '{}',
            is_active INTEGER NOT NULL DEFAULT 1, verifie_le TEXT, priorite INTEGER DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS plans (
            id TEXT PRIMARY KEY, profile_id TEXT REFERENCES profiles(id) ON DELETE SET NULL,
            axe_emploi TEXT NOT NULL DEFAULT '{}', axe_logement TEXT NOT NULL DEFAULT '{}',
            axe_finance TEXT NOT NULL DEFAULT '{}', axe_sante TEXT NOT NULL DEFAULT '{}',
            resume_global TEXT, score_ia INTEGER,
            model_version TEXT DEFAULT 'claude-sonnet-4-6', prompt_version TEXT DEFAULT '1.0',
            tokens_input INTEGER DEFAULT 0, tokens_output INTEGER DEFAULT 0,
            latence_ms INTEGER DEFAULT 0, statut TEXT NOT NULL DEFAULT 'PENDING',
            agent_id TEXT REFERENCES users(id), notes_agent TEXT,
            modifications_agent TEXT DEFAULT '{}', validated_at TEXT, rejected_reason TEXT,
            pdf_url TEXT, pdf_generated_at TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS plan_opportunities (
            id TEXT PRIMARY KEY DEFAULT (lower(hex(randomblob(16)))), plan_id TEXT NOT NULL REFERENCES plans(id) ON DELETE CASCADE,
            opportunity_id TEXT NOT NULL, axe TEXT NOT NULL, priorite INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS ia_sessions (
            id TEXT PRIMARY KEY, plan_id TEXT REFERENCES plans(id) ON DELETE SET NULL,
            user_id TEXT REFERENCES users(id) ON DELETE SET NULL,
            endpoint TEXT, model TEXT, tokens_input INTEGER DEFAULT 0,
            tokens_output INTEGER DEFAULT 0, cout_usd REAL DEFAULT 0,
            latence_ms INTEGER DEFAULT 0, success INTEGER NOT NULL DEFAULT 1, error_msg TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS notifications (
            id TEXT PRIMARY KEY, user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            channel TEXT NOT NULL DEFAULT 'IN_APP', title TEXT NOT NULL, body TEXT NOT NULL,
            data TEXT DEFAULT '{}', statut TEXT NOT NULL DEFAULT 'PENDING',
            read_at TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS audit_logs (
            id TEXT PRIMARY KEY, user_id TEXT REFERENCES users(id) ON DELETE SET NULL,
            action TEXT NOT NULL, entity_type TEXT, entity_id TEXT, ip_address TEXT, user_agent TEXT,
            metadata TEXT DEFAULT '{}', created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS follow_up (
            id TEXT PRIMARY KEY, plan_id TEXT NOT NULL REFERENCES plans(id) ON DELETE CASCADE,
            agent_id TEXT REFERENCES users(id), date_contact TEXT NOT NULL DEFAULT (date('now')),
            avancement_pct INTEGER, emploi_trouve INTEGER, logement_stable INTEGER, sante_ok INTEGER,
            commentaire TEXT, prochaine_action TEXT, prochaine_date TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS plan_cache (
            key TEXT PRIMARY KEY, value TEXT NOT NULL, expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS rate_limit_cache (
            key TEXT PRIMARY KEY, count INTEGER NOT NULL DEFAULT 1,
            window_start TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS country_profiles (
            pays TEXT PRIMARY KEY,
            secteurs_porteurs TEXT NOT NULL DEFAULT '[]',
            taux_informalite REAL NOT NULL DEFAULT 0,
            pib_par_habitant REAL NOT NULL DEFAULT 0,
            taux_chomage REAL NOT NULL DEFAULT 0,
            micro_finance TEXT NOT NULL DEFAULT '[]',
            structures_alpha TEXT NOT NULL DEFAULT '[]',
            opportunites_informelles TEXT NOT NULL DEFAULT '[]',
            ressources_oim TEXT NOT NULL DEFAULT '{}',
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS interview_sessions (
            id TEXT PRIMARY KEY,
            device_id TEXT,
            user_id TEXT REFERENCES users(id) ON DELETE SET NULL,
            lang TEXT NOT NULL DEFAULT 'fr',
            statut TEXT NOT NULL DEFAULT 'IN_PROGRESS',
            etape TEXT NOT NULL DEFAULT 'BIENVENUE',
            conversation TEXT NOT NULL DEFAULT '[]',
            profile_draft TEXT NOT NULL DEFAULT '{}',
            rdv_date TEXT,
            rdv_lieu TEXT,
            plan_id TEXT REFERENCES plans(id) ON DELETE SET NULL,
            synced INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS kiosk_devices (
            id TEXT PRIMARY KEY,
            device_token TEXT UNIQUE NOT NULL,
            nom TEXT,
            lieu TEXT,
            is_active INTEGER NOT NULL DEFAULT 1,
            last_ping TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        ");
        self::migrate_schema();
        self::seed();
    }

    private static function migrate_schema(): void {
        $tables = [
            'users' => [
                'email TEXT', 'password_hash TEXT', 'phone TEXT', 'first_name TEXT', 'last_name TEXT',
                'gender TEXT', 'age INTEGER',
            ],
            'profiles' => [
                'langue TEXT', 'objectifs TEXT', 'besoins TEXT', 'contraintes TEXT', 'sante TEXT', 'enfants INTEGER',
                "alphabetisation TEXT NOT NULL DEFAULT 'OUI'",
                'competences_informelles TEXT NOT NULL DEFAULT \'\'',
                "pays_accueil TEXT NOT NULL DEFAULT ''",
            ],
            'plans' => [
                'pdf_generated_at TEXT',
            ],
            'opportunities' => [
                'contact_web TEXT', 'raw_data TEXT',
            ],
            'audit_logs' => [
                'user_agent TEXT',
            ],
        ];

        foreach ($tables as $table => $cols) {
            $existing = array_map(fn($c) => $c['name'], self::query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC));
            foreach ($cols as $col) {
                $colName = preg_split('/\s+/', trim($col))[0];
                if (!in_array($colName, $existing, true)) {
                    self::$pdo->exec("ALTER TABLE {$table} ADD COLUMN {$col}");
                }
            }
        }
    }

    private static function seed(): void {
        self::seed_country_profiles();
        if ((int)(self::row("SELECT COUNT(*) AS n FROM opportunities")['n'] ?? 0) > 0) return;
        $opps = [
            ['Sénégal','Dakar','FORMATION','Formation agri-business 3 mois','Gestion agricole, marchés locaux. 70% pris en charge OIM.','CFP Dakar',45000,'FCFA',12,'["agriculture","commerce"]'],
            ['Sénégal','Dakar','MICRO_CREDIT','Micro-crédit BNDE','Taux 5% sur 24 mois, sans garantie.','BNDE',500000,'FCFA',null,'["finance"]'],
            ['Sénégal','Dakar','LOGEMENT','Accueil temporaire UNHCR','14 jours, repas inclus — Centre Almadies.','UNHCR Dakar',0,'FCFA',2,'["logement","urgence"]'],
            ['Sénégal','Dakar','SANTE','Bilan médical OIM J+3','Consultation complète, gratuit OIM.','Hôpital Principal Dakar',0,'FCFA',null,'["sante"]'],
            ['Sénégal','Dakar','EMPLOI','Mise en relation ANPEM','3 offres emploi commerce. Aide CV et entretiens.','ANPEM Sénégal',0,'FCFA',null,'["emploi"]'],
            ['Côte d\'Ivoire','Abidjan','FORMATION','Formation numérique USAID','Marketing digital et e-commerce.','USAID CI',25000,'FCFA',8,'["informatique"]'],
            ['Côte d\'Ivoire','Abidjan','MICRO_CREDIT','Microcrédit BACI','300 000 FCFA, taux 6% sur 18 mois.','BACI Microfinance',300000,'FCFA',null,'["finance"]'],
            ['Niger','Niamey','EMPLOI','Réseau emploi ANPE Niger','Agriculture, BTP, transport.','ANPE Niger',0,'XOF',null,'["emploi"]'],
            ['Niger','Niamey','LOGEMENT','Centre accueil OIM Niamey','10 jours, repas et accompagnement.','OIM Niger',0,'XOF',null,'["logement"]'],
            ['Mali','Bamako','FORMATION','Formation couture artisanat','Couture, broderie, maroquinerie.','Centre Artisanal Bamako',30000,'FCFA',16,'["artisanat"]'],
            ['Mali','Bamako','MICRO_CREDIT','Kafo Jiginew microfinance','200 000 FCFA, 12 mois.','Kafo Jiginew',200000,'FCFA',null,'["finance"]'],
        ];
        foreach ($opps as $o) {
            self::insert('opportunities', [
                'pays'=>$o[0],'ville'=>$o[1],'type'=>$o[2],'titre'=>$o[3],
                'description'=>$o[4],'organisme'=>$o[5],'cout_estime'=>$o[6],
                'devise'=>$o[7],'duree_semaines'=>$o[8],'tags'=>$o[9],
                'is_active'=>1,'verifie_le'=>date('Y-m-d'),
            ]);
        }
    }
}
