# CONTRACT — Thara Planning Control Room

Ce document est la source de vérité partagée entre les 3 lots de travail (backend, frontend manager, frontend agent). Toute personne qui en dévie doit mettre ce fichier à jour en même temps que son code, pas après.

Référence métier/UX : [`docs/legacy-prototype.html`](./legacy-prototype.html) — prototype vanilla JS fourni par le client. Toute la logique métier qu'il contient (cycle J-N-R, règle des 48h, couverture, renfort inter-salles) doit être reproduite fidèlement côté backend.

## Stack

- Backend : Laravel 11, API stateless, auth par token Bearer (Laravel Sanctum, *personal access tokens*, pas le mode SPA cookie — plus simple à intégrer avec un frontend Next.js servi séparément en dev).
- Frontend : Next.js (App Router, TypeScript, Tailwind), dans `frontend/`.
- Dossiers : `backend/` (Laravel), `frontend/` (Next.js), `docs/` (ce contrat + référence).

## Domaine métier (rappel)

- **Room** (salle) : un centre 24/7. `mode` figé à `quart` pour l'instant.
- **Employee** : appartient à une room. Deux types :
  - `rotation` : suit le cycle continu J→N→R, ancré sur `REF = 2026-01-01`. `offset` (0/1/2) déterminé par l'ordre de création au sein de la room, par binômes de 2 : `offset = floor(index/2) % 3`, `binome = offset + 1`.
  - `fixed_day` : `day_spec` = tableau de 7 valeurs (`on`/`off`/`alt`) indexées Lundi=0..Dimanche=6. `alt` = travaille seulement les semaines de parité `alt_parity` (0=paires, 1=impaires), selon le numéro de semaine ISO.
- **Absence** : déclarée par le manager, effective immédiatement, statut toujours `enregistree`. Remplace la case du planning par `ABS`.
- **Permission** : statut `enum('enregistree','refusee','en_attente')`.
  - Saisie par le **manager** directement : règle des 48h appliquée immédiatement (pas d'étape d'approbation, le manager est déjà le validateur). Si `heures jusqu'au début < 48`, statut = `refusee` (n'impacte pas le planning, reste visible dans l'historique). Sinon statut = `enregistree`, équivaut à une absence pour le planning.
  - Soumise par l'**agent** lui-même : la règle des 48h ne fait que refuser d'office si déjà trop tard (`refusee`, définitif). Sinon statut = `en_attente` — n'impacte pas encore le planning, tant que le manager n'a pas validé (`POST /absences/{id}/approve` → `enregistree`) ou rejeté (`POST /absences/{id}/reject` → `refusee`) explicitement.
- **Cellule de planning** : valeur effective = `ABS` si absence enregistrée ce jour > sinon override manuel saisi par le manager > sinon statut auto calculé (cycle J/N/R ou jour fixe).
- **Renfort inter-salles** : un agent d'une autre room peut être "prêté" pour une semaine donnée dans une room cible ; il n'a alors aucun statut auto, seulement les valeurs que le manager lui assigne case par case.
- **Couverture** : par jour, nombre d'agents en `J` et nombre en `N` dans la room. C'est un **plafond strict, jamais un minimum** : une room ne doit jamais avoir plus de 2 agents simultanément en J, ni plus de 2 en N (alerte si < 2 également, sous-effectif).
- **Chaque room doit avoir un agent `fixed_day`** ("contrôle"). C'est une contrainte métier, pas encore imposée techniquement par une validation bloquante — à respecter lors de la saisie des employés.
- **Format type d'une room "quart 24/7"** : 3 binômes rotation (6 agents) + 1 agent `fixed_day` ("contrôle"). Logique (`PlanningEngine::autoStatusesForRotation`), cycle **quotidien** (chaque agent suit individuellement J→N→R→J→N→R, jamais de bloc figé) :
  - Avec exactement 3 binômes, les offsets 0/1/2 garantissent qu'à tout moment 1 seul binôme est sur chaque phase : 2J + 2N + 2R par construction, tous les jours.
  - Le jour où le binôme naturellement en J coïncide avec le contrôle (qui est toujours J quand il travaille) : un seul des 2 membres de ce binôme garde son J (rejoint le contrôle, 2 en J au total), l'autre passe en R **ce jour précis seulement** (jamais de saut direct N→J, son jour précédent était déjà R). Lequel des 2 membres perd son J alterne tous les 3 jours (équité), sans désynchroniser leur cycle réel.
  - Toute autre configuration (room sans agent fixe, ou nombre de binômes ≠ 3) : cycle indépendant historique par employé (`PlanningEngine::autoStatus`), sans garantie de plafond à 2 — réservé aux salles qui ne suivent pas encore ce format cible.

## Schéma de données (à affiner par l'agent backend, mais respecter ces entités/contraintes)

```
users
  id, name, email (unique), password, role enum('manager','agent'),
  employee_id nullable FK -> employees.id (uniquement pour role=agent)

rooms
  id, name, mode default 'quart'

employees
  id, room_id FK, name, email nullable,
  type enum('rotation','fixed_day'),
  offset nullable int (rotation), binome nullable int (rotation),
  day_spec nullable json (fixed_day, array de 7 'on'|'off'|'alt'),
  alt_parity nullable int (0|1)

absences
  id, employee_id FK, start_date, end_date,
  type enum('absence','permission'),
  reason nullable text,
  status enum('enregistree','refusee'),
  created_at, updated_at

schedule_overrides
  id, room_id FK, week_start date (lundi ISO), employee_id FK,
  day_index tinyint (0-6), value enum('J','N','R',''),
  unique(room_id, week_start, employee_id, day_index)

room_week_loans   -- marque qu'un employé externe est "prêté" à une room pour une semaine
  id, room_id FK, week_start date, employee_id FK,
  unique(room_id, week_start, employee_id)
```

## API (préfixe `/api`)

Auth :
- `POST /login` `{email, password}` → `{token, user:{id,name,email,role,employee_id}}`
- `POST /logout` (Bearer requis)
- `GET /me` → user courant + `employee` chargé si role=agent

Manager (role=manager requis, middleware) :
- `GET /rooms` / `POST /rooms` `{name}` / `PATCH /rooms/{id}` / `DELETE /rooms/{id}`
- `GET /employees` (filtrable `?room_id=`) / `POST /employees` / `PATCH /employees/{id}` / `DELETE /employees/{id}`
- `GET /rooms/{room}/schedule?week=YYYY-MM-DD` → `{dates:[...7 ISO], roster:[...employee avec champ cross:bool], grid:{employee_id:[7 valeurs]}, coverage:{J:[7],N:[7]}}`
- `PATCH /rooms/{room}/schedule` `{week, employee_id, day_index, value}` → upsert une cellule (cycle côté frontend : `'' → J → N → R → ''`)
- `POST /rooms/{room}/schedule/reset` `{week}` → supprime tous les overrides de cette semaine pour cette room
- `POST /rooms/{room}/schedule/loans` `{week, employee_id}` → ajoute un prêt
- `DELETE /rooms/{room}/schedule/loans` `{week, employee_id}` → retire un prêt
- `GET /absences` (toutes, triées par date desc) / `POST /absences` `{employee_id, start_date, end_date, reason}` (manager → toujours `enregistree`) / `DELETE /absences/{id}`
- `POST /absences/{id}/approve` → valide une demande `en_attente` (statut → `enregistree`) ; 422 si elle n'est plus `en_attente`
- `POST /absences/{id}/reject` → rejette une demande `en_attente` (statut → `refusee`) ; 422 si elle n'est plus `en_attente`
- `POST /permissions` `{employee_id, start_date, end_date, reason}` → saisie manager, applique la règle des 48h immédiatement, retourne le statut résultant (`enregistree`/`refusee`)
- `GET /rooms/{room}/diffusion?week=` → `[{employee_id, name, email, subject, body}]` (texte généré, même format que `emailBody()` du prototype)
- `POST /rooms/{room}/diffusion/send` `{week}` → envoie réellement les e-mails (Laravel Mail), best-effort, retourne `{sent: [...], failed: [...]}` (`DiffusionService::sendForRoom`, partagé avec l'envoi automatique)

**Diffusion automatique** : commande `planning:send-weekly-diffusion` (planifiée tous les jours à 08h00, voir `routes/console.php` et le service `scheduler` de `docker-compose.prod.yaml`) — n'agit que si "demain" est un lundi (J-1 du début de semaine), et envoie alors la diffusion à toutes les salles pour la semaine qui commence le lendemain. `--force` + `--week=` pour rejouer manuellement.

Agent (role=agent requis, scope = `employee_id` du user connecté, jamais un paramètre d'URL libre) :
- `GET /me/schedule?week=` → même forme que ci-dessus mais limité à l'employé courant
- `GET /me/absences`
- `POST /me/permissions` `{start_date, end_date, reason}` → règle des 48h : `refusee` direct si trop tard, sinon `en_attente` (validation/rejet manager requis avant d'impacter le planning)

## Découpage du travail (pour éviter les conflits de fichiers)

- **Agent Backend** : tout `backend/` (migrations, models, controllers, policies, tests Pest/PHPUnit). Référence : ce contrat + `legacy-prototype.html` pour la logique exacte (cycle, 48h, tri du roster, etc.).
- **Agent Frontend Manager** : `frontend/src/app/manager/**` uniquement (Planning, Mois, Employés, Salles, Absences & demandes, Diffusion). Consomme `src/lib/api.ts` et `src/lib/auth.tsx` (déjà fournis, ne pas les réécrire — les étendre si besoin en ajoutant des fonctions, pas en changeant leur signature).
- **Agent Frontend Agent** : `frontend/src/app/agent/**`. Mêmes règles de partage du socle. Ne pas toucher à `frontend/src/app/login/page.tsx` ni `frontend/src/app/page.tsx` (déjà fournis).

Si un agent a besoin d'un changement dans le socle partagé (`lib/api.ts`, `lib/auth.tsx`, layout racine, thème Tailwind), il documente le besoin ici plutôt que de le modifier en silence, pour éviter d'écraser le travail d'un autre agent.

## Charte graphique (à porter dans `tailwind.config`)

- `charcoal #1a1a1a`, `red #d32027` / `red-dark #a81820`
- `jour #1f9d55` (bg `#e6f5ec`), `nuit #2b3a67` (bg `#e6e9f2`), `repos #9aa0a6` (bg `#f0f0f2`), `abs #d32027` (bg `#fdecec`)
- Logo : fichier client `frontend/public/thara-logo.png` ("Thara Services", texte noir + bouclier rouge/noir). Sur le header (fond charcoal), affiché dans un badge blanc arrondi pour le contraste ; sur la page de login (fond clair), affiché directement sans badge. Header charcoal, liseré rouge en bas du header.

## Déploiement

App accessible en prod sur `http://crm.thara-services.mg/planning`, derrière le reverse proxy déjà existant sur ce serveur pour ce domaine (TLS + autres apps gérés ailleurs, hors de ce repo). Conteneurisé via Docker (dev : `docker-compose.yml` ; prod : `docker-compose.prod.yaml` + MySQL + gateway nginx interne sur `/planning`). Voir `docs/DEPLOYMENT.md` pour la procédure complète et le snippet à ajouter côté reverse proxy existant.

## Notes d'implémentation backend (Agent Backend)

- Stack de tests : PHPUnit (pas de plugin Pest installé dans `composer.json` fourni au scaffold) ; `phpunit.xml` force `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` pour les tests, indépendamment de la sqlite de dev (`database/database.sqlite`).
- Logique métier portée fidèlement dans `app/Support/PlanningEngine.php` (cycle J/N/R ancré REF=2026-01-01, jours fixes + parité ISO week, couverture, format `emailBody()`), `app/Support/ScheduleService.php` (roster/grid/coverage par semaine, résolution de cellule effective ABS > override > auto, renfort inter-salles via `room_week_loans`), et `app/Support/AbsenceService.php` (règle des 48h).
- Auth : Sanctum *personal access tokens* uniquement (pas de guard `web` stateful), middleware applicatif `role:manager|agent` (alias enregistré dans `bootstrap/app.php`) pour scinder les routes manager/agent. Les routes agent ne prennent jamais d'`employee_id` en paramètre : elles dérivent toujours l'employé depuis `$request->user()->employee`.
- `routes/api.php` n'existait pas dans le scaffold ; créé et enregistré via `withRouting(api: ...)` dans `bootstrap/app.php`.
- Écart mineur : pas de `Policies` Laravel dédiées (au sens `App\Policies`) — le contrôle de rôle se fait via le middleware `role` + scope explicite dans les controllers agent, ce qui couvre le besoin du contrat sans complexité additionnelle.
