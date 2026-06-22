// Types partagés pour le lot "Agent Frontend Agent".
// Reflète le CONTRACT.md (schéma de données + API /api, routes /me/...).

export type EmployeeType = "rotation" | "fixed_day";

export type DaySpecValue = "on" | "off" | "alt";

export type Employee = {
  id: number;
  room_id: number;
  name: string;
  email: string | null;
  type: EmployeeType;
  offset: number | null;
  binome: number | null;
  day_spec: DaySpecValue[] | null;
  alt_parity: 0 | 1 | null;
};

// Roster renvoyé par /me/schedule : même forme que côté manager, avec indicateur de prêt.
export type RosterEmployee = Employee & {
  cross: boolean;
};

export type CellValue = "J" | "N" | "R" | "" | "ABS";

export type RoomInfo = { id: number; name: string };

export type ScheduleResponse = {
  dates: string[]; // 7 dates ISO (lundi -> dimanche)
  roster: RosterEmployee[];
  grid: Record<string, CellValue[]>; // employee_id (clé) -> 7 valeurs
  coverage: {
    J: number[]; // 7 valeurs
    N: number[]; // 7 valeurs
  };
  // Salle d'où provient chaque jour (salle d'origine, ou salle de renfort si prêté ce
  // jour-là) — un agent peut être affecté à plusieurs salles différentes dans la semaine.
  rooms: RoomInfo[];
};

export type AbsenceType = "absence" | "permission";
export type AbsenceStatus = "enregistree" | "refusee" | "en_attente";

export type Absence = {
  id: number;
  employee_id: number;
  start_date: string;
  end_date: string;
  type: AbsenceType;
  reason: string | null;
  status: AbsenceStatus;
  created_at: string;
  updated_at: string;
};

export type PermissionRequestPayload = {
  start_date: string;
  end_date: string;
  reason: string;
};
