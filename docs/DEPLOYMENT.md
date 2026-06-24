# Déploiement

## Dev (local, conteneurisé)

Reproduit le fonctionnement local habituel (`php artisan serve` + `npm run
dev`, sqlite), juste dans des conteneurs. Code monté en volume (hot reload).
Utilise directement `backend/.env` et `frontend/.env.local` déjà présents sur
le disque.

```
docker compose up --build
```

- backend : http://localhost:8000
- frontend : http://localhost:3000

## Prod

Stack : MySQL + backend (nginx + php-fpm dans une seule image) + `scheduler`
(même image, lance `php artisan schedule:work` — nécessaire pour la diffusion
automatique des e-mails à J-1 du début de semaine, voir routes/console.php) +
frontend (Next.js en sortie "standalone") + un conteneur `gateway` (nginx) qui
route `/planning` vers le frontend et `/planning/api` vers le backend.

### 1. Préparer les secrets

```
cp .env.prod.example .env
```

Compléter `.env` (à la racine, à côté de `docker-compose.prod.yaml`) :

- `APP_KEY` : générer avec `php artisan key:generate --show` (depuis
  n'importe quel environnement PHP/Laravel, pas besoin que la stack tourne).
  Ne JAMAIS regénérer cette clé à chaque déploiement : ça invaliderait les
  données chiffrées existantes.
- `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` / `DB_ROOT_PASSWORD` : à
  choisir, MySQL les utilise pour s'initialiser au premier démarrage.
- `MAIL_*` : déjà fournis pour `contact@thara-services.mg` (voir
  `backend/.env` local) — à reporter ici aussi.
- `PUBLIC_PORT` : port exposé par le conteneur `gateway` sur l'hôte (8080 par
  défaut). C'est ce port que le reverse proxy existant du serveur doit
  relayer (voir étape 3).

### 2. Lancer la stack

```
docker compose -f docker-compose.prod.yaml up -d --build
```

Au démarrage, le conteneur backend attend que MySQL soit prêt, joue les
migrations (`php artisan migrate --force`), puis cache la config/les routes.

### 3. Côté serveur : relayer /planning vers le conteneur gateway

Le serveur héberge déjà `crm.thara-services.mg` (TLS, probablement d'autres
applications sur d'autres chemins) via un reverse proxy existant — **ce repo
ne gère pas ce vhost**. Il faut juste ajouter, dans la config de ce reverse
proxy existant, un bloc qui relaie `/planning` vers le port exposé par le
conteneur `gateway` (`PUBLIC_PORT`, 8080 par défaut). Exemple si ce proxy est
lui-même nginx :

```nginx
location /planning/ {
    proxy_pass http://127.0.0.1:8080/planning/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

Une fois ce bloc ajouté et le proxy rechargé, l'app est accessible sur
`http://crm.thara-services.mg/planning`.

### Mettre à jour un déploiement existant

```
docker compose -f docker-compose.prod.yaml up -d --build
```

Rejoue les migrations et reconstruit les images si le code a changé ; les
données MySQL persistent dans le volume nommé `mysql_data`.

## Hébergement mutualisé (sans Docker, sans Node)

Pas de Docker sur un mutualisé classique (cPanel). Le backend Laravel tourne
nativement sur PHP + MySQL ; le frontend est exporté en **HTML/CSS/JS
statiques** (`output: "export"`) — possible ici sans changement de code
puisque l'app est déjà 100% client-side (aucune route API Next, aucun
middleware).

### Frontend — export statique

```
NEXT_OUTPUT_MODE=export NEXT_PUBLIC_API_URL=/api npm run build:export
```

(PowerShell : `$env:NEXT_OUTPUT_MODE='export'; $env:NEXT_PUBLIC_API_URL='/api'; npm run build`)

- `NEXT_PUBLIC_API_URL` : chemin/URL vu depuis le navigateur pour joindre
  l'API Laravel. Relatif (`/api`) si front et back partagent le même domaine
  racine ; URL absolue (`https://api.domaine.mg/api`) sinon.
- `NEXT_BASE_PATH` : à fixer (ex. `/planning`) seulement si l'app doit vivre
  sous un sous-dossier d'un site existant plutôt qu'à la racine d'un
  (sous-)domaine dédié.

Résultat dans `frontend/out/` : à envoyer tel quel (FTP/SSH) dans le
`public_html` (ou sous-dossier) du domaine/sous-domaine cPanel. Chaque route
a son propre `index.html` (`trailingSlash` activé en mode export) : un
Apache standard la sert nativement, aucune règle de réécriture nécessaire.

### Backend — Laravel classique (FTP uniquement, sans terminal SSH)

**0. Vérifier d'abord si cPanel propose un "Terminal"** (icône dans le
panneau) — certains hébergeurs sans SSH classique l'activent quand même ;
si c'est le cas, ça permet de lancer `composer`/`artisan` directement et de
sauter les contournements ci-dessous (étapes 2 et 4).

1. **Construire `vendor/` en local** (pas de composer sur le serveur) :
   ```
   cd backend && composer install --no-dev --optimize-autoloader
   ```
   Puis envoyer **tout** `backend/` (avec `vendor/` cette fois) par FTP,
   sauf `.env` et `node_modules/`.
2. Dans cPanel, pointer le **Document Root** du domaine/sous-domaine de
   l'API vers `backend/public`. Si ce réglage n'est pas disponible, copier
   `public/index.php` et `public/.htaccess` à la racine réelle du site et
   adapter les chemins `require` qu'ils contiennent vers `../backend/...`.
3. Créer `.env` (copie de `.env.example`) **directement sur le serveur**
   via l'éditeur de fichiers cPanel (pas en FTP en clair si évitable) :
   compléter `APP_KEY` (le générer en local avec
   `php artisan key:generate --show`, copier juste la valeur), `DB_*`,
   `MAIL_*`, `APP_URL`.
4. **Migrations sans terminal** : dans ce même `.env`, définir
   `DEPLOY_MIGRATE_TOKEN` avec une valeur longue et aléatoire, puis visiter
   une fois dans le navigateur :
   ```
   https://ton-domaine/deploy-migrate.php?token=LA_VALEUR_CHOISIE
   ```
   Ce script (`backend/public/deploy-migrate.php`) joue les migrations et
   affiche le résultat. **Supprimer ce fichier par FTP immédiatement après**
   (sécurité — sans lui, n'importe qui connaissant le token pourrait
   rejouer les migrations).
5. **Cron** (remplace le service `scheduler` du Docker) — dans "Tâches Cron"
   du panneau (généralement disponible même sans terminal SSH), toutes les
   minutes :
   ```
   * * * * * php /chemin/vers/backend/artisan schedule:run >> /dev/null 2>&1
   ```
   C'est ce qui déclenche `planning:send-weekly-diffusion` à J-1 de chaque
   semaine (voir `routes/console.php`). Le chemin exact de `php` (souvent
   une version précise comme `/opt/cpanel/ea-php82/root/usr/bin/php`) et le
   chemin du projet sont à adapter — l'outil "Tâches Cron" de cPanel les
   suggère généralement.

   Si même les Tâches Cron sont indisponibles : un service externe gratuit
   (ex. cron-job.org) peut appeler `https://ton-domaine/api/...` à
   intervalle régulier pour déclencher un endpoint équivalent — solution de
   repli, à ne mettre en place que si la case précédente est impossible.

### CORS

Si le frontend et l'API ne partagent pas le même domaine racine (origines
différentes), vérifier que `config/cors.php` (ou les valeurs par défaut du
framework si ce fichier n'existe pas) autorise l'origine du frontend pour les
routes `api/*`.
