"use client";

// Wrapper utilisé par chaque page d'onglet manager (Planning, Mois, Employés,
// Salles, Absences & demandes, Diffusion). On ne touche pas à manager/layout.tsx
// (fourni, contrat l'interdit) donc le contexte partagé + la nav d'onglets sont
// posés ici, importés par chaque page.tsx du dossier manager/**.

import { PlanningProvider } from "./PlanningContext";
import { TabNav } from "./TabNav";

export function ManagerShell({ children }: { children: React.ReactNode }) {
  return (
    <PlanningProvider>
      <TabNav />
      {children}
    </PlanningProvider>
  );
}
