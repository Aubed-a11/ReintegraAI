# ReintegraAI — Projet Complet
## OIM Maroc × BAIC — Webinaire IA AVRR 2026 — Groupe 2

---

## Démarrage en 2 commandes (aucune installation de base de données requise)

La base de données SQLite se crée **automatiquement** au premier lancement.

### Terminal 1 — Backend PHP

```bash
cd backend
php -S localhost:8000 api/index.php
```

Vérification : ouvre `http://localhost:8000/api/health` dans le navigateur.
Tu dois voir `{"success":true,"data":{"status":"ok","db":true,...}}`

### Terminal 2 — Frontend React

```bash
cd frontend
npm install
npm run dev
```

Ouvre `http://localhost:3000` → l'application est prête.

---

## Prérequis (Windows)

| Logiciel | Installation | Vérification |
|----------|-------------|--------------|
| **PHP 8.2+** | [windows.php.net/download](https://windows.php.net/download) (Thread Safe x64) | `php -v` dans cmd |
| **Node.js 18+** | [nodejs.org](https://nodejs.org) | `node -v` dans cmd |

**PHP sur Windows** : extraire dans `C:\php`, ajouter `C:\php` au PATH Windows.
SQLite est inclus dans PHP — aucune installation séparée.

---

## Connexion Claude API (optionnel)

Sans clé : le projet fonctionne en mode démo avec un plan réaliste pré-construit.
Avec clé : ouvrir `.env` et ajouter `CLAUDE_API_KEY=sk-ant-api03-...`

---

## Structure

```
ReintegraAI_SQLITE/
├── .env                         Clé Claude API (optionnelle)
├── backend/
│   ├── api/
│   │   ├── bootstrap.php        Auto-loader
│   │   └── index.php            Router (toutes les routes API)
│   ├── config/
│   │   └── config.php           SQLite auto-créé + constantes
│   ├── database/
│   │   └── reintegraai.db       ← CRÉÉ AUTOMATIQUEMENT au 1er lancement
│   ├── middleware/              JWT · CORS · Rate Limiting
│   ├── services/                MOTEUR IA
│   │   ├── NLPEngine.php        Détection langue FR/EN/AR/WO
│   │   ├── MatchingEngine.php   Scoring opportunités × profil
│   │   ├── PromptBuilder.php    Prompts Claude optimisés
│   │   ├── ClaudeClient.php     Appel Anthropic API
│   │   └── IAOrchestrator.php   Pipeline complet
│   └── routes/                  auth · profile · plan · autres
└── frontend/                    React 18 + Vite
    └── src/pages/               Login · Profil · Plan · Chat · Dashboard OIM
```

---

*Groupe 2 : ADOGNIBO Hortice · TOURÉ Jaouja · DAGA Bienvenu · SOSSAH Graziella · ACCROMBESSY Francis*
