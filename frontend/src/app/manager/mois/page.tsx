"use client";

import { useCallback, useEffect, useState } from "react";
import { ManagerShell } from "../_components/ManagerShell";
import { usePlanningContext } from "../_components/PlanningContext";
import { useRooms } from "../_components/useRooms";
import { RoomSelect } from "../_components/RoomSelect";
import { PlanningGrid } from "../_components/PlanningGrid";
import { getRoomSchedule } from "../api-manager";
import type { ScheduleResponse } from "../types";
import { fmtShort, isoWeekNum, monthWeeks, shiftMonthAnchor } from "../date-utils";
import { ApiError } from "@/lib/auth";

export default function MonthPage() {
  return (
    <ManagerShell>
      <MonthTab />
    </ManagerShell>
  );
}

function MonthTab() {
  const { rooms, loading: roomsLoading } = useRooms();
  const { currentRoomId, setCurrentRoomId, monthAnchor, setMonthAnchor } = usePlanningContext();
  const [schedules, setSchedules] = useState<Record<string, ScheduleResponse>>({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!currentRoomId && rooms.length) setCurrentRoomId(rooms[0].id);
  }, [rooms, currentRoomId, setCurrentRoomId]);

  const { weeks, monthName } = monthWeeks(monthAnchor);

  const reload = useCallback(async () => {
    if (!currentRoomId) return;
    setLoading(true);
    setError(null);
    try {
      const results = await Promise.all(weeks.map((w) => getRoomSchedule(currentRoomId, w)));
      const map: Record<string, ScheduleResponse> = {};
      weeks.forEach((w, i) => {
        map[w] = results[i];
      });
      setSchedules(map);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur de chargement du mois.");
    } finally {
      setLoading(false);
    }
    // weeks dérive de monthAnchor, qui est déjà une dépendance
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentRoomId, monthAnchor]);

  useEffect(() => {
    reload();
  }, [reload]);

  if (roomsLoading) return <div className="text-muted text-sm">Chargement…</div>;
  if (!rooms.length)
    return (
      <div className="bg-panel border border-line rounded-lg p-4">
        <div className="text-muted text-sm text-center border border-dashed border-line rounded-md p-4">
          Crée une salle.
        </div>
      </div>
    );

  return (
    <div className="bg-panel border border-line rounded-lg p-4">
      <div className="flex gap-3 items-end flex-wrap mb-3">
        <div>
          <label className="block text-xs font-semibold text-muted mb-1">Salle</label>
          <RoomSelect rooms={rooms} value={currentRoomId} onChange={setCurrentRoomId} />
        </div>
        <div>
          <label className="block text-xs font-semibold text-muted mb-1">Mois</label>
          <div className="flex items-center gap-2">
            <button
              type="button"
              className="bg-white border border-line rounded-md px-3 py-1.5 text-sm hover:border-charcoal"
              onClick={() => setMonthAnchor(shiftMonthAnchor(monthAnchor, -1))}
            >
              ‹
            </button>
            <b className="capitalize min-w-[160px] inline-block text-center text-sm">{monthName}</b>
            <button
              type="button"
              className="bg-white border border-line rounded-md px-3 py-1.5 text-sm hover:border-charcoal"
              onClick={() => setMonthAnchor(shiftMonthAnchor(monthAnchor, 1))}
            >
              ›
            </button>
          </div>
        </div>
      </div>

      <p className="text-sm text-muted mb-3">
        Vue mensuelle, dérivée semaine par semaine de l&apos;API planning. Couverture Jour/Nuit en bas
        de chaque semaine.
      </p>

      {error && (
        <div className="mb-4 text-sm text-red bg-abs-bg border border-red/30 rounded-md px-3 py-2">
          {error}
        </div>
      )}

      {loading ? (
        <div className="text-muted text-sm">Chargement…</div>
      ) : (
        weeks.map((w) => {
          const sched = schedules[w];
          if (!sched) return null;
          return (
            <div key={w} className="mb-4">
              <h4 className="text-[13.5px] font-semibold mb-1.5">
                Semaine du {fmtShort(sched.dates[0])} (S{isoWeekNum(w)})
              </h4>
              <div className="overflow-x-auto">
                <PlanningGrid schedule={sched} readOnly />
              </div>
            </div>
          );
        })
      )}
    </div>
  );
}
