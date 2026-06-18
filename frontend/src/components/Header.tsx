"use client";

import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";

export function Header() {
  const { user, logout } = useAuth();
  const router = useRouter();

  async function handleLogout() {
    await logout();
    router.push("/login");
  }

  return (
    <header className="bg-charcoal text-white px-5 py-3 flex items-center gap-4 border-b-4 border-red flex-wrap">
      <div className="font-bold text-xl tracking-wide">
        THARA<span className="text-red">·</span>CR
      </div>
      <div className="text-sm text-white/80">Planning Control Room</div>
      {user && (
        <div className="ml-auto flex items-center gap-3 text-sm">
          <span className="text-white/80">
            {user.name} · {user.role === "manager" ? "Responsable" : "Agent"}
          </span>
          <button
            onClick={handleLogout}
            className="border border-white/30 rounded-md px-3 py-1 hover:border-white/60"
          >
            Déconnexion
          </button>
        </div>
      )}
    </header>
  );
}
