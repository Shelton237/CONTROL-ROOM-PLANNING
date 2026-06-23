"use client";

import { useState } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useAuth, ApiError } from "@/lib/auth";

export default function LoginPage() {
  const { login } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const user = await login(email, password);
      router.push(user.role === "manager" ? "/manager" : "/agent");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Connexion impossible.");
    } finally {
      setSubmitting(false);
    }
  }

  const basePath = process.env.NEXT_PUBLIC_BASE_PATH ?? "";

  return (
    <main
      className="flex-1 flex items-center justify-center px-4 relative bg-cover bg-center"
      style={{ backgroundImage: `url(${basePath}/login-bg.jpg)` }}
    >
      <div className="absolute inset-0 bg-charcoal/70" />
      <form
        onSubmit={handleSubmit}
        className="relative z-10 w-full max-w-sm bg-panel border border-line rounded-lg p-6 shadow-xl"
      >
        <div className="mb-6 text-center">
          <Image
            src={`${basePath}/thara-logo.png`}
            alt="Thara Services"
            width={220}
            height={70}
            className="h-14 w-auto mx-auto"
            priority
            unoptimized
          />
          <p className="text-sm text-muted mt-1">Planning Control Room</p>
        </div>

        {error && (
          <div className="mb-4 text-sm text-red bg-abs-bg border border-red/30 rounded-md px-3 py-2">
            {error}
          </div>
        )}

        <label className="block text-xs font-semibold text-muted mb-1" htmlFor="email">
          E-mail
        </label>
        <input
          id="email"
          type="email"
          required
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="w-full border border-line rounded-md px-3 py-2 mb-4 focus:outline-none focus:border-red"
        />

        <label className="block text-xs font-semibold text-muted mb-1" htmlFor="password">
          Mot de passe
        </label>
        <input
          id="password"
          type="password"
          required
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className="w-full border border-line rounded-md px-3 py-2 mb-6 focus:outline-none focus:border-red"
        />

        <button
          type="submit"
          disabled={submitting}
          className="w-full bg-red hover:bg-red-dark text-white font-semibold rounded-md py-2 disabled:opacity-60"
        >
          {submitting ? "Connexion…" : "Se connecter"}
        </button>
      </form>
    </main>
  );
}
