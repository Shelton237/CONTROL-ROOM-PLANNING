"use client";

import { useCallback, useEffect, useState } from "react";
import { usePlanningContext } from "./_components/PlanningContext";
import { ManagerShell } from "./_components/ManagerShell";
import { useDialog } from "./_components/DialogProvider";
import { useRooms } from "./_components/useRooms";
import { RoomSelect } from "./_components/RoomSelect";
import { WeekNav } from "./_components/WeekNav";
import { Legend } from "./_components/Legend";
import { PlanningGrid } from "./_components/PlanningGrid";
import {
  addLoan,
  getRoomSchedule,
  listEmployees,
  patchScheduleCell,
  removeLoan,
  resetWeek,
} from "./api-manager";
import type { CellValue, Employee, ScheduleResponse } from "./types";
import { ApiError } from "@/lib/auth";

const CYCLE_ORDER: CellValue[] = ["", "J", "N", "R"];

function nextValue(current: CellValue): CellValue {
  const idx = CYCLE_ORDER.indexOf(current);
  return CYCLE_ORDER[(idx + 1) % CYCLE_ORDER.length];
}

export default function PlanningPage() {
  return (
    <ManagerShell>
      <PlanningTab />
    </ManagerShell>
  );
}

function PlanningTab() {
  const { confirm } = useDialog();
  const { rooms, loading: roomsLoading } = useRooms();
  const { currentRoomId, setCurrentRoomId, weekStart, setWeekStart } = usePlanningContext();
  const [schedule, setSchedule] = useState<ScheduleResponse | null>(null);
  const [allEmployees, setAllEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [crossEmployeeId, setCrossEmployeeId] = useState<string>("");

  useEffect(() => {
    if (!currentRoomId && rooms.length) setCurrentRoomId(rooms[0].id);
  }, [rooms, currentRoomId, setCurrentRoomId]);

  const reload = useCallback(async () => {
    if (!currentRoomId) return;
    setLoading(true);
    setError(null);
    try {
      const [sched, emps] = await Promise.all([
        getRoomSchedule(currentRoomId, weekStart),
        listEmployees(),
      ]);
      setSchedule(sched);
      setAllEmployees(emps);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur de chargement du planning.");
    } finally {
      setLoading(false);
    }
  }, [currentRoomId, weekStart]);

  useEffect(() => {
    reload();
  }, [reload]);

  async function handleCellClick(employeeId: number, dayIndex: number, current: CellValue) {
    if (!currentRoomId) return;
    const next = nextValue(current);
    try {
      const updated = await patchScheduleCell(currentRoomId, weekStart, employeeId, dayIndex, next);
      setSchedule(updated);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors de la mise à jour de la cellule.");
    }
  }

  async function handleReset() {
    if (!currentRoomId) return;
    const ok = await confirm({
      title: "Réinitialiser la semaine",
      message: "Réinitialiser cette semaine sur la proposition automatique ?",
      confirmLabel: "Réinitialiser",
    });
    if (!ok) return;
    try {
      await resetWeek(currentRoomId, weekStart);
      await reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors de la réinitialisation.");
    }
  }

  async function handleAssignCross() {
    if (!currentRoomId || !crossEmployeeId) return;
    try {
      await addLoan(currentRoomId, weekStart, Number(crossEmployeeId));
      setCrossEmployeeId("");
      await reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors de l'affectation du renfort.");
    }
  }

  async function handleUnassign(employeeId: number) {
    if (!currentRoomId) return;
    try {
      await removeLoan(currentRoomId, weekStart, employeeId);
      await reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors du retrait du renfort.");
    }
  }

  if (roomsLoading) return <div className="text-muted text-sm">Chargement…</div>;
  if (!rooms.length)
    return (
      <div className="bg-panel border border-line rounded-lg p-4">
        <div className="text-muted text-sm text-center border border-dashed border-line rounded-md p-4">
          Crée une salle.
        </div>
      </div>
    );

  const otherEmployees = allEmployees.filter(
    (e) => e.room_id !== currentRoomId && !schedule?.roster.some((r) => r.id === e.id)
  );

  return (
    <div className="bg-panel border border-line rounded-lg p-4">
      <h2 className="text-lg font-semibold mb-3">Planning de la semaine</h2>

      {error && (
        <div className="mb-4 text-sm text-red bg-abs-bg border border-red/30 rounded-md px-3 py-2">
          {error}
        </div>
      )}

      <div className="flex gap-3 items-end flex-wrap mb-3">
        <div>
          <label className="block text-xs font-semibold text-muted mb-1">Salle</label>
          <RoomSelect rooms={rooms} value={currentRoomId} onChange={setCurrentRoomId} />
        </div>
        <div>
          <label className="block text-xs font-semibold text-muted mb-1">Semaine</label>
          <WeekNav weekStart={weekStart} onChange={setWeekStart} />
        </div>
        <div>
          <button
            type="button"
            className="bg-white border border-line rounded-md px-4 py-2 text-sm font-semibold hover:border-charcoal"
            onClick={handleReset}
          >
            ↺ Réinitialiser (proposition auto)
          </button>
        </div>
      </div>

      <div className="bg-[#fffbe6] border border-[#f0e0a0] rounded-md px-3 py-2.5 text-sm mb-3">
        Le planning J-N-R est <b>proposé automatiquement</b> chaque semaine. Les absences validées
        apparaissent en rouge et creusent la couverture → comble alors le trou avec un renfort
        ci-dessous. Clique une cellule pour ajuster (J→N→R→vide).
      </div>

      <Legend />

      {loading || !schedule ? (
        <div className="text-muted text-sm">Chargement…</div>
      ) : (
        <div className="overflow-x-auto">
          <PlanningGrid schedule={schedule} onCellClick={handleCellClick} onUnassign={handleUnassign} />
        </div>
      )}

      <h3 className="text-sm font-semibold mt-5 mb-2">Affecter un renfort (absence)</h3>
      <div className="flex gap-3 items-end flex-wrap">
        <div className="flex-[2] min-w-[220px]">
          <label className="block text-xs font-semibold text-muted mb-1">Agent d&apos;une autre salle</label>
          <select
            value={crossEmployeeId}
            onChange={(e) => setCrossEmployeeId(e.target.value)}
            className="border border-line rounded-md px-3 py-2 bg-white text-sm w-full"
          >
            <option value="">Aucun</option>
            {otherEmployees.map((e) => {
              const r = rooms.find((rm) => rm.id === e.room_id);
              return (
                <option key={e.id} value={e.id}>
                  {e.name} ({r ? r.name : "?"})
                </option>
              );
            })}
          </select>
        </div>
        <div>
          <button
            type="button"
            disabled={!crossEmployeeId}
            className="bg-charcoal text-white rounded-md px-4 py-2 text-sm font-semibold disabled:opacity-50"
            onClick={handleAssignCross}
          >
            + Affecter cette semaine
          </button>
        </div>
      </div>
      <p className="text-xs text-muted mt-2">
        Le renfort apparaît « prêté » pour cette semaine ; tu cliques ses cellules pour le positionner
        (Jour/Nuit). Fonctionne dans les deux sens entre salles.
      </p>
    </div>
  );
}
