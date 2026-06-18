"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { AgentNav } from "../components/AgentNav";
import { Legend, StatusCell } from "../components/StatusCell";
import type { CellValue, ScheduleResponse } from "../types";
import { DSHORT, fmtShort, isoWeekNum, monthWeeks, shiftMonthAnchor, todayISO } from "../lib/dates";

type WeekData = {
  weekStart: string;
  schedule: ScheduleResponse | null;
  error: string | null;
};

export default function AgentMonthPage() {
  const [anchor, setAnchor] = useState(() => todayISO());
  const [weeksData, setWeeksData] = useState<WeekData[]>([]);
  const [loading, setLoading] = useState(true);

  const { weeks, monthName } = monthWeeks(anchor);

  const load = useCallback(async (weekList: string[]) => {
    setLoading(true);
    const results = await Promise.all(
      weekList.map(async (weekStart) => {
        try {
          const schedule = await api.get<ScheduleResponse>(`/me/schedule?week=${weekStart}`);
          return { weekStart, schedule, error: null } as WeekData;
        } catch (err) {
          return {
            weekStart,
            schedule: null,
            error: err instanceof ApiError ? err.message : "Erreur de chargement.",
          } as WeekData;
        }
      })
    );
    setWeeksData(results);
    setLoading(false);
  }, []);

  useEffect(() => {
    load(weeks);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [anchor]);

  return (
    <div className="bg-panel border border-line rounded-lg p-5">
      <AgentNav />
      <h2 className="text-lg font-semibold mb-1">Mon mois</h2>
      <p className="text-sm text-muted mb-4">
        Vue mensuelle agrégée de mon planning personnel, semaine par semaine (lecture seule).
      </p>

      <div className="flex items-center gap-2 mb-4">
        <button
          onClick={() => setAnchor((a) => shiftMonthAnchor(a, -1))}
          className="border border-line rounded-md px-3 py-1.5 text-sm hover:border-charcoal"
        >
          ‹
        </button>
        <b className="min-w-[160px] text-center inline-block capitalize text-sm">{monthName}</b>
        <button
          onClick={() => setAnchor((a) => shiftMonthAnchor(a, 1))}
          className="border border-line rounded-md px-3 py-1.5 text-sm hover:border-charcoal"
        >
          ›
        </button>
      </div>

      <Legend />

      {loading && <p className="text-sm text-muted">Chargement…</p>}

      {!loading &&
        weeksData.map(({ weekStart, schedule, error }) => {
          const me = schedule?.roster?.[0];
          const dates = schedule?.dates ?? [];
          const mine: CellValue[] = me ? schedule?.grid[String(me.id)] ?? [] : [];
          return (
            <div key={weekStart} className="mb-5">
              <h4 className="text-sm font-medium mb-1.5">
                Semaine du {fmtShort(weekStart)} (S{isoWeekNum(weekStart)})
              </h4>
              {error && (
                <div className="text-sm text-red bg-abs-bg border border-red/30 rounded-md px-3 py-2">
                  {error}
                </div>
              )}
              {!error && dates.length > 0 && (
                <table className="border-collapse w-full text-xs">
                  <thead>
                    <tr>
                      {dates.map((iso, i) => (
                        <th key={iso} className="border border-line bg-gray-50 py-1 px-1.5 text-muted">
                          {DSHORT[i]} {fmtShort(iso).split(" ")[0]}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      {(mine.length ? mine : Array(7).fill("R" as CellValue)).map((s, d) => (
                        <StatusCell key={d} value={s} />
                      ))}
                    </tr>
                  </tbody>
                </table>
              )}
            </div>
          );
        })}
    </div>
  );
}
