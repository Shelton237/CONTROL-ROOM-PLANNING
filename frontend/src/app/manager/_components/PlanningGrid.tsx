"use client";

import { DSHORT, fmtShort } from "../date-utils";
import type { CellValue, ScheduleResponse } from "../types";
import { CellTag } from "./Legend";

type Props = {
  schedule: ScheduleResponse;
  onCellClick?: (employeeId: number, dayIndex: number, current: CellValue) => void;
  onUnassign?: (employeeId: number) => void;
  readOnly?: boolean;
};

function cellClass(value: CellValue): string {
  switch (value) {
    case "J":
      return "bg-jour-bg text-jour";
    case "N":
      return "bg-nuit-bg text-nuit";
    case "R":
      return "bg-repos-bg text-repos";
    case "ABS":
      return "bg-abs-bg text-abs";
    default:
      return "bg-white text-[#ccc]";
  }
}

export function PlanningGrid({ schedule, onCellClick, onUnassign, readOnly }: Props) {
  const { dates, roster, grid, coverage } = schedule;

  if (!roster.length) {
    return (
      <div className="text-muted text-sm p-4 text-center border border-dashed border-line rounded-md">
        Aucun agent dans cette salle. Ajoute-les dans Employés.
      </div>
    );
  }

  return (
    <table className="border-collapse w-full text-sm">
      <thead>
        <tr>
          <th className="border border-line px-2 py-1.5 text-left bg-[#fafafa] text-[12.5px] uppercase tracking-wide text-muted">
            Agent
          </th>
          {dates.map((iso, i) => (
            <th
              key={iso}
              className="border border-line px-2 py-1.5 bg-[#fafafa] text-[12.5px] uppercase tracking-wide text-muted"
            >
              {DSHORT[i]}
              <br />
              <span className="text-xs font-normal">{fmtShort(iso)}</span>
            </th>
          ))}
          <th className="border border-line bg-[#fafafa]" />
        </tr>
      </thead>
      <tbody>
        {roster.map((e) => {
          const row = grid[String(e.id)] ?? (Array(7).fill("") as CellValue[]);
          return (
            <tr key={e.id}>
              <td className="border border-line px-2 py-1.5 font-semibold whitespace-nowrap">
                {e.name}
                <CellTag cross={e.cross} type={e.type} binome={e.binome} />
              </td>
              {row.map((val, d) => {
                const isAbs = val === "ABS";
                const clickable = !readOnly && !isAbs && onCellClick;
                return (
                  <td
                    key={d}
                    className={`border border-line px-2 py-1.5 text-center font-bold min-w-[48px] select-none ${cellClass(
                      val
                    )} ${clickable ? "cursor-pointer" : isAbs ? "cursor-not-allowed" : ""}`}
                    title={isAbs ? "Absence – modifier dans Absences" : "Cliquer pour changer"}
                    onClick={() => {
                      if (clickable) onCellClick!(e.id, d, val);
                    }}
                  >
                    {val || "·"}
                  </td>
                );
              })}
              <td className="border border-line px-2 py-1.5 text-center">
                {e.cross && onUnassign ? (
                  <button
                    type="button"
                    className="text-red underline text-xs"
                    onClick={() => onUnassign(e.id)}
                  >
                    retirer
                  </button>
                ) : null}
              </td>
            </tr>
          );
        })}
        <tr>
          <td className="border border-line px-2 py-1.5 font-bold text-xs text-left">Couverture Jour</td>
          {coverage.J.map((j, i) => (
            <td
              key={i}
              className={`border border-line px-2 py-1.5 text-center font-bold text-xs ${
                j >= 2 ? "text-jour" : "bg-abs-bg text-red"
              }`}
            >
              {j}
              {j < 2 ? " ⚠" : ""}
            </td>
          ))}
          <td className="border border-line" />
        </tr>
        <tr>
          <td className="border border-line px-2 py-1.5 font-bold text-xs text-left">Couverture Nuit</td>
          {coverage.N.map((n, i) => (
            <td
              key={i}
              className={`border border-line px-2 py-1.5 text-center font-bold text-xs ${
                n >= 2 ? "text-jour" : "bg-abs-bg text-red"
              }`}
            >
              {n}
              {n < 2 ? " ⚠" : ""}
            </td>
          ))}
          <td className="border border-line" />
        </tr>
      </tbody>
    </table>
  );
}
