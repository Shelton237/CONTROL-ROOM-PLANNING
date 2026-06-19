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

Stack : MySQL + backend (nginx + php-fpm dans une seule image) + frontend
(Next.js en sortie "standalone") + un conteneur `gateway` (nginx) qui route
`/planning` vers le frontend et `/planning/api` vers le backend.

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
