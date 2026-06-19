"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { Legend, cellClass } from "./components/StatusCell";
import type { ScheduleResponse } from "./types";
import { DSHORT, fmtShort, isoWeekNum, mondayISOof, todayISO, weekParity } from "./lib/dates";

const STATUS_LABEL: Record<string, string> = {
  J: "07h30 – 17h30",
  N: "17h30 – 07h30",
  R: "repos",
  ABS: "absence",
  "": "repos",
};

export default function AgentWeekPage() {
  const [weekStart, setWeekStart] = useState(() => mondayISOof(todayISO()));
  const [schedule, setSchedule] = useState<ScheduleResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (week: string) => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<ScheduleResponse>(`/me/schedule?week=${week}`);
      setSchedule(data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de charger le planning.");
      setSchedule(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load(weekStart);
  }, [weekStart, load]);

  function shiftWeek(n: number) {
    setWeekStart((w) => {
      const d = new Date(Date.parse(w + "T00:00:00Z") + n * 7 * 864e5);
      return d.toISOString().slice(0, 10);
    });
  }

  const me = schedule?.roster?.[0];
  const mine = me ? schedule?.grid[String(me.id)] ?? [] : [];
  const dates = schedule?.dates ?? [];
  const vacations = mine.filter((s) => s === "J" || s === "N").length;

  return (
    <div className="bg-panel border border-line rounded-lg p-5">
      <h2 className="text-lg font-semibold mb-1">Ma semaine</h2>
      <p className="text-sm text-muted mb-4">
        Planning personnel en lecture seule. Pour toute modification, contacte le responsable.
      </p>

      <div className="flex items-center gap-2 mb-4">
        <button
          onClick={() => shiftWeek(-1)}
          className="border border-line rounded-md px-3 py-1.5 text-sm hover:border-charcoal"
        >
          ‹
        </button>
        <b className="min-w-[260px] text-center inline-block text-sm">
          {dates.length
            ? `${fmtShort(dates[0])} – ${fmtShort(dates[6])} · S${isoWeekNum(weekStart)} (${
                weekParity(weekStart) ? "impaire" : "paire"
              })`
            : "—"}
        </b>
        <button
          onClick={() => shiftWeek(1)}
          className="border border-line rounded-md px-3 py-1.5 text-sm hover:border-charcoal"
        >
          ›
        </button>
        <button
          onClick={() => setWeekStart(mondayISOof(todayISO()))}
          className="border border-line rounded-md px-3 py-1.5 text-sm hover:border-charcoal"
        >
          Cette semaine
        </button>
      </div>

      <Legend />

      {loading && <p className="text-sm text-muted">Chargement…</p>}
      {error && (
        <div className="text-sm text-red bg-abs-bg border border-red/30 rounded-md px-3 py-2 mb-4">
          {error}
        </div>
      )}

      {!loading && !error && (!me || dates.length === 0) && (
        <div className="text-muted text-sm p-4 text-center border border-dashed border-line rounded-md">
          Aucun planning disponible pour cette semaine.
        </div>
      )}

      {!loading && !error && me && dates.length > 0 && (
        <>
          <div className="flex gap-1 flex-wrap my-2">
            {dates.map((iso, d) => {
              const s = mine[d] || "R";
              return (
                <div
                  key={iso}
                  className={`flex-1 min-w-[90px] border border-line rounded-md p-2 text-center ${cellClass(
                    s
                  )}`}
                >
                  <div className="text-[11px] text-muted">
                    {DSHORT[d]} {fmtShort(iso).split(" ")[0]}
                  </div>
                  <div className="font-bold text-lg mt-0.5">{s || "R"}</div>
                  <div className="text-[11px] text-muted">{STATUS_LABEL[s] ?? STATUS_LABEL[""]}</div>
                </div>
              );
            })}
          </div>
          <p className="text-xs text-muted mt-3">
            Vacations cette semaine : <b>{vacations}</b>. Une question sur ton planning ? Vois avec le
            responsable.
          </p>
        </>
      )}
    </div>
  );
}
