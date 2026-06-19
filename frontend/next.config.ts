import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // "standalone" est utilisé par le build de prod (voir frontend/Dockerfile) ;
  // sans effet sur `next dev`.
  output: "standalone",
  // En prod, l'app est servie sous /planning (voir docker-compose.prod.yaml +
  // docker/gateway.conf). Doit être fixé au build (next build), pas modifiable
  // au runtime.
  basePath: process.env.NEXT_BASE_PATH || undefined,
};

export default nextConfig;
