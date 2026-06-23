// Wrappers d'API spécifiques au lot manager, basés sur src/lib/api.ts (non modifié).
// Centralise tous les endpoints manager définis dans docs/CONTRACT.md.

import { api } from "@/lib/api";
import type {
  Absence,
  AbsenceType,
  CellValue,
  DiffusionEmail,
  DiffusionSendResponse,
  Employee,
  EmployeeType,
  EmployeeWithAccount,
  DaySpecValue,
  Room,
  ScheduleResponse,
} from "./types";

// ---------- Rooms ----------
export function listRooms() {
  return api.get<Room[]>("/rooms");
}
export function createRoom(name: string) {
  return api.post<Room>("/rooms", { name });
}
export function updateRoom(id: number, name: string) {
  return api.patch<Room>(`/rooms/${id}`, { name });
}
export function deleteRoom(id: number) {
  return api.del<void>(`/rooms/${id}`);
}

// ---------- Employees ----------
export function listEmployees(roomId?: number) {
  const qs = roomId ? `?room_id=${roomId}` : "";
  return api.get<Employee[]>(`/employees${qs}`);
}

export type EmployeePayload = {
  room_id: number;
  name: string;
  email?: string | null;
  type: EmployeeType;
  day_spec?: DaySpecValue[] | null;
  alt_parity?: 0 | 1 | null;
};

export function createEmployee(payload: EmployeePayload) {
  return api.post<EmployeeWithAccount>("/employees", payload);
}
export function updateEmployee(id: number, payload: Partial<EmployeePayload>) {
  return api.patch<Employee>(`/employees/${id}`, payload);
}
export function deleteEmployee(id: number) {
  return api.del<void>(`/employees/${id}`);
}

// ---------- Schedule ----------
export function getRoomSchedule(roomId: number, week: string) {
  return api.get<ScheduleResponse>(`/rooms/${roomId}/schedule?week=${week}`);
}

export function patchScheduleCell(
  roomId: number,
  week: string,
  employeeId: number,
  dayIndex: number,
  value: CellValue
) {
  return api.patch<ScheduleResponse>(`/rooms/${roomId}/schedule`, {
    week,
    employee_id: employeeId,
    day_index: dayIndex,
    value,
  });
}

export function resetWeek(roomId: number, week: string) {
  return api.post<void>(`/rooms/${roomId}/schedule/reset`, { week });
}

export function addLoan(roomId: number, week: string, employeeId: number) {
  return api.post<void>(`/rooms/${roomId}/schedule/loans`, { week, employee_id: employeeId });
}

export function removeLoan(roomId: number, week: string, employeeId: number) {
  return api.del<void>(`/rooms/${roomId}/schedule/loans`, { week, employee_id: employeeId });
}

// ---------- Absences & permissions ----------
export function listAbsences() {
  return api.get<Absence[]>("/absences");
}

export function createAbsence(payload: {
  employee_id: number;
  start_date: string;
  end_date: string;
  reason?: string;
}) {
  return api.post<Absence>("/absences", payload);
}

export function deleteAbsence(id: number) {
  return api.del<void>(`/absences/${id}`);
}

export function approveAbsence(id: number) {
  return api.post<Absence>(`/absences/${id}/approve`);
}

export function rejectAbsence(id: number) {
  return api.post<Absence>(`/absences/${id}/reject`);
}

export function createPermission(payload: {
  employee_id: number;
  start_date: string;
  end_date: string;
  reason?: string;
}) {
  return api.post<Absence>("/permissions", payload);
}

export type { AbsenceType };

// ---------- Diffusion ----------
export function getDiffusionPreview(roomId: number, week: string) {
  return api.get<DiffusionEmail[]>(`/rooms/${roomId}/diffusion?week=${week}`);
}

export function sendDiffusion(roomId: number, week: string) {
  return api.post<DiffusionSendResponse>(`/rooms/${roomId}/diffusion/send`, { week });
}
