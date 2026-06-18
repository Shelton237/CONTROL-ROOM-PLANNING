"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const TABS: [string, string][] = [
  ["/agent", "Ma semaine"],
  ["/agent/mois", "Mon mois"],
  ["/agent/demandes", "Mes demandes"],
];

export function AgentNav() {
  const pathname = usePathname();
  return (
    <nav className="flex bg-panel border border-line rounded-lg p-1 mb-5 gap-1 flex-wrap">
      {TABS.map(([href, label]) => {
        const active = pathname === href;
        return (
          <Link
            key={href}
            href={href}
            className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
              active ? "bg-charcoal text-white" : "text-muted hover:text-charcoal"
            }`}
          >
            {label}
          </Link>
        );
      })}
    </nav>
  );
}
