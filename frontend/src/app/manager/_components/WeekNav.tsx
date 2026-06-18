"use client";

import { addDaysISO, fmtShort, isoWeekNum, mondayISOof, todayISO, weekParity } from "../date-utils";

type Props = {
  weekStart: string;
  onChange: (iso: string) => void;
  showParity?: boolean;
};

export function WeekNav({ weekStart, onChange, showParity = true }: Props) {
  const dates0 = weekStart;
  const dates6 = addDaysISO(weekStart, 6);
  return (
    <div className="flex items-center gap-2">
      <button
        type="button"
        className="bg-white border border-line rounded-md px-3 py-1.5 text-sm hover:border-charcoal"
        onClick={() => onChange(addDaysISO(weekStart, -7))}
      >
        ‹
      </button>
      <b className="min-w-[230px] text-center inline-block text-sm">
        {fmtShort(dates0)} – {fmtShort(dates6)} · S{isoWeekNum(weekStart)}
        {showParity ? ` (${weekParity(weekStart) ? "impaire" : "paire"})` : ""}
      </b>
      <button
        type="button"
        className="bg-white border border-line rounded-md px-3 py-1.5 text-sm hover:border-charcoal"
        onClick={() => onChange(addDaysISO(weekStart, 7))}
      >
        ›
      </button>
      <button
        type="button"
        className="bg-white border border-line rounded-md px-3 py-1.5 text-sm hover:border-charcoal"
        onClick={() => onChange(mondayISOof(todayISO()))}
      >
        Cette semaine
      </button>
    </div>
  );
}
