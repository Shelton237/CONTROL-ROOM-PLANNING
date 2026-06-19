# syntax=docker/dockerfile:1

FROM node:20-alpine AS base
WORKDIR /app

# ---------------------------------------------------------------------------
# dev : next dev, code monté en volume (voir docker-compose.yml)
# ---------------------------------------------------------------------------
FROM base AS dev
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
EXPOSE 3000
CMD ["npm", "run", "dev"]

# ---------------------------------------------------------------------------
# deps : dépendances pour le build de prod
# ---------------------------------------------------------------------------
FROM base AS deps
COPY package.json package-lock.json ./
RUN npm ci

# ---------------------------------------------------------------------------
# build : next build (sortie "standalone"). NEXT_PUBLIC_API_URL et
# NEXT_BASE_PATH doivent être fixés ici : ce sont des valeurs figées au build,
# pas des variables d'environnement runtime.
# ---------------------------------------------------------------------------
FROM base AS build
ARG NEXT_PUBLIC_API_URL
ARG NEXT_BASE_PATH=""
ENV NEXT_PUBLIC_API_URL=${NEXT_PUBLIC_API_URL}
ENV NEXT_BASE_PATH=${NEXT_BASE_PATH}
COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN npm run build

# ---------------------------------------------------------------------------
# prod : runtime minimal (sortie standalone de Next.js)
# ---------------------------------------------------------------------------
FROM base AS prod
ENV NODE_ENV=production
COPY --from=build /app/public ./public
COPY --from=build /app/.next/standalone ./
COPY --from=build /app/.next/static ./.next/static
EXPOSE 3000
CMD ["node", "server.js"]
