"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const TABS: [string, string][] = [
  ["/manager", "Planning"],
  ["/manager/mois", "Mois"],
  ["/manager/employes", "Employés"],
  ["/manager/absences", "Absences & demandes"],
  ["/manager/diffusion", "Diffusion"],
  ["/manager/salles", "Salles"],
];

export function TabNav() {
  const pathname = usePathname();
  return (
    <nav className="flex w-full bg-panel border-b border-line px-5 flex-wrap">
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
