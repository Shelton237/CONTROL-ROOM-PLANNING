// Types partagés pour le lot "Agent Frontend Manager".
// Reflète le CONTRACT.md (schéma de données + API /api).

export type Room = {
  id: number;
  name: string;
  mode: string;
};

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

// Renvoyé uniquement par POST /employees : résultat de la création du
// compte de connexion agent (best-effort, voir CONTRACT.md).
export type EmployeeAccountResult = {
  created: boolean;
  email_sent: boolean;
  password: string | null;
  reason: "no_email" | "email_already_used" | null;
};

export type EmployeeWithAccount = Employee & { account: EmployeeAccountResult };

// Roster renvoyé par /rooms/{room}/schedule : un employé + indicateur de prêt
export type RosterEmployee = Employee & {
  cross: boolean;
};

export type CellValue = "J" | "N" | "R" | "" | "ABS";

export type ScheduleResponse = {
  dates: string[]; // 7 dates ISO (lundi -> dimanche)
  roster: RosterEmployee[];
  grid: Record<string, CellValue[]>; // employee_id (clé) -> 7 valeurs
  coverage: {
    J: number[]; // 7 valeurs
    N: number[]; // 7 valeurs
  };
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
  employee?: Employee | null;
};

export type DiffusionEmail = {
  employee_id: number;
  name: string;
  email: string | null;
  subject: string;
  body: string;
};

export type DiffusionSendResultItem = {
  employee_id: number;
  name: string;
  email: string | null;
  success: boolean;
  error?: string;
};

export type DiffusionSendResponse = {
  sent: DiffusionSendResultItem[];
  failed: DiffusionSendResultItem[];
};
