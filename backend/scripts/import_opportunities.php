<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$path = $argv[1] ?? __DIR__ . '/../data/opportunities.json';
if (!file_exists($path)) {
    fwrite(STDERR, "Fichier JSON introuvable : {$path}\n");
    exit(1);
}

$json = json_decode(file_get_contents($path), true);
if (!is_array($json) || empty($json['pays'])) {
    fwrite(STDERR, "Format JSON invalide. Attendu un objet contenant une clé 'pays'.\n");
    exit(1);
}

function map_type(string $type): string {
    return match(mb_strtolower(trim($type))) {
        'emploi' => 'EMPLOI',
        'formation' => 'FORMATION',
        'micro-credit', 'micro credit', 'micro_credit' => 'MICRO_CREDIT',
        'logement' => 'LOGEMENT',
        'sante', 'santé' => 'SANTE',
        default => strtoupper(str_replace([' ', '-'], '_', $type)),
    };
}

function parse_cost(string $cost): ?float {
    if (preg_match('/([0-9]+[\.,]?[0-9]*)/', $cost, $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }
    return null;
}

function parse_weeks(string $duration): ?int {
    if (preg_match('/([0-9]+)\s*(semaine|semaines|sem|mois|jours)/i', $duration, $m)) {
        $value = (int)$m[1];
        $unit = mb_strtolower($m[2]);
        return match($unit) {
            'jour', 'jours' => max(1, (int)ceil($value / 7)),
            'mois' => $value * 4,
            default => $value,
        };
    }
    return null;
}

$inserted = 0;
$updated = 0;

foreach ($json['pays'] as $country) {
    $countryName = $country['nom_pays'] ?? ($country['code_pays'] ?? '');
    if (!$countryName) continue;

    foreach ($country['opportunites'] ?? [] as $opp) {
        $id = trim($opp['id'] ?? '');
        if ($id === '') continue;

        $tags = [];
        if (!empty($opp['profil_migrant_cible']) && is_array($opp['profil_migrant_cible'])) {
            foreach ($opp['profil_migrant_cible'] as $tag) {
                $tags[] = sanitize((string)$tag);
            }
        }
        if (!empty($opp['zones']) && is_array($opp['zones'])) {
            foreach ($opp['zones'] as $zone) {
                $tags[] = sanitize((string)$zone);
            }
        }
        if (!empty($opp['langues_service']) && is_array($opp['langues_service'])) {
            foreach ($opp['langues_service'] as $lang) {
                $tags[] = sanitize((string)$lang);
            }
        }
        $tags = array_values(array_filter(array_unique($tags)));

        $data = [
            'id' => $id,
            'pays' => $countryName,
            'ville' => is_array($opp['zones'] ?? null) ? ($opp['zones'][0] ?? null) : null,
            'type' => map_type($opp['type'] ?? ''),
            'titre' => $opp['nom'] ?? 'Opportunité',
            'description' => $opp['description'] ?? '',
            'organisme' => $opp['organisation'] ?? '',
            'contact_tel' => $opp['contact_telephone'] ?? null,
            'contact_web' => $opp['contact_web'] ?? null,
            'cout_estime' => isset($opp['cout']) ? parse_cost((string)$opp['cout']) : null,
            'devise' => $opp['monnaie'] ?? 'FCFA',
            'duree_semaines' => isset($opp['duree_programme']) ? parse_weeks((string)$opp['duree_programme']) : null,
            'tags' => json_encode($tags, JSON_UNESCAPED_UNICODE),
            'raw_data' => json_encode($opp, JSON_UNESCAPED_UNICODE),
            'is_active' => 1,
            'verifie_le' => date('Y-m-d'),
            'priorite' => 1,
        ];

        try {
            $existing = DB::row('SELECT id FROM opportunities WHERE id = ?', [$id]);
            if ($existing) {
                $sets = [];
                $params = [];
                foreach ($data as $key => $value) {
                    if ($key === 'id') continue;
                    $sets[] = "{$key}=?";
                    $params[] = $value;
                }
                $params[] = $id;
                DB::query('UPDATE opportunities SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
                $updated++;
            } else {
                DB::insert('opportunities', $data);
                $inserted++;
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "Erreur import {$id} : {$e->getMessage()}\n");
        }
    }
}

fwrite(STDOUT, "Import terminé : {$inserted} insérées, {$updated} mises à jour.\n");
