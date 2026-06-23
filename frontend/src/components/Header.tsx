"use client";

import Image from "next/image";
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
      <div className="bg-white rounded-md px-2 py-1 flex items-center">
        <Image
          src={`${process.env.NEXT_PUBLIC_BASE_PATH ?? ""}/thara-logo.png`}
          alt="Thara Services"
          width={120}
          height={40}
          className="h-8 w-auto"
          priority
          unoptimized
        />
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
