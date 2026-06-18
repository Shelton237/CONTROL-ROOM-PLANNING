"use client";

// Contexte partagé entre les onglets Planning / Mois / Diffusion :
// salle sélectionnée + semaine/mois courants. Évite de re-sélectionner
// la salle à chaque changement d'onglet (comme dans legacy-prototype.html
// où state.ui.currentRoomId / weekStart / monthAnchor sont globaux).

import { createContext, useContext, useState, useMemo } from "react";
import { mondayISOof, todayISO } from "../date-utils";

type PlanningContextValue = {
  currentRoomId: number | null;
  setCurrentRoomId: (id: number | null) => void;
  weekStart: string;
  setWeekStart: (iso: string) => void;
  monthAnchor: string;
  setMonthAnchor: (iso: string) => void;
};

const PlanningContext = createContext<PlanningContextValue | null>(null);

export function PlanningProvider({ children }: { children: React.ReactNode }) {
  const [currentRoomId, setCurrentRoomId] = useState<number | null>(null);
  const [weekStart, setWeekStart] = useState<string>(() => mondayISOof(todayISO()));
  const [monthAnchor, setMonthAnchor] = useState<string>(() => todayISO());

  const value = useMemo(
    () => ({ currentRoomId, setCurrentRoomId, weekStart, setWeekStart, monthAnchor, setMonthAnchor }),
    [currentRoomId, weekStart, monthAnchor]
  );

  return <PlanningContext.Provider value={value}>{children}</PlanningContext.Provider>;
}

export function usePlanningContext() {
  const ctx = useContext(PlanningContext);
  if (!ctx) throw new Error("usePlanningContext must be used within PlanningProvider");
  return ctx;
}
