"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const TABS: [string, string][] = [
  ["/manager", "Planning"],
  ["/manager/mois", "Mois"],
  ["/manager/employes", "Employés"],
  ["/manager/salles", "Salles"],
  ["/manager/absences", "Absences & demandes"],
  ["/manager/diffusion", "Diffusion"],
];

export function TabNav() {
  const pathname = usePathname();
  return (
    <nav className="flex bg-panel border-b border-line px-3 flex-wrap -mx-5 mb-5">
      {TABS.map(([href, label]) => {
        const active = pathname === href;
        return (
          <Link
            key={href}
            href={href}
            className={`px-4 py-3 text-sm border-b-[3px] ${
              active
                ? "text-charcoal border-red font-semibold"
                : "text-muted border-transparent hover:text-charcoal"
            }`}
          >
            {label}
          </Link>
        );
      })}
    </nav>
  );
}
