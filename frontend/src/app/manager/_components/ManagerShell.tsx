"use client";

// Wrapper utilisé par chaque page d'onglet manager (Planning, Mois, Employés,
// Salles, Absences & demandes, Diffusion) pour exposer le contexte partagé
// (salle/semaine sélectionnées). La nav d'onglets vit dans manager/layout.tsx
// (pleine largeur, au même niveau que le Header), pas ici.

import { DialogProvider } from "./DialogProvider";
import { PlanningProvider } from "./PlanningContext";

export function ManagerShell({ children }: { children: React.ReactNode }) {
  return (
    <DialogProvider>
      <PlanningProvider>{children}</PlanningProvider>
    </DialogProvider>
  );
}
