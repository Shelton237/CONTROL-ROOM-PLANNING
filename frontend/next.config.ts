import type { NextConfig } from "next";

// Deux cibles de build possibles, choisies au build via NEXT_OUTPUT_MODE :
// - "standalone" (par défaut) : serveur Node packagé, utilisé par le Docker
//   de prod (voir frontend/Dockerfile + docker-compose.prod.yaml).
// - "export" : HTML/CSS/JS 100% statiques (aucun Node requis), pour un
//   hébergement mutualisé classique (Apache/cPanel). L'app étant déjà
//   entièrement client-side (aucune route API Next, aucun middleware),
//   l'export statique ne nécessite aucun changement de code.
const isStaticExport = process.env.NEXT_OUTPUT_MODE === "export";

const nextConfig: NextConfig = {
  output: isStaticExport ? "export" : "standalone",
  // En export statique, chaque route doit correspondre à un vrai fichier
  // .../index.html servable tel quel par un Apache basique (sans réécriture
  // d'URL côté serveur) : /manager/employes -> /manager/employes/index.html.
  trailingSlash: isStaticExport,
  // Préfixe d'URL si l'app est servie sous un sous-chemin (ex. /planning).
  // Fixé au build (next build), pas modifiable au runtime. Laisser vide
  // (variable absente) si l'app est servie à la racine du domaine.
  basePath: process.env.NEXT_BASE_PATH || undefined,
};

export default nextConfig;
