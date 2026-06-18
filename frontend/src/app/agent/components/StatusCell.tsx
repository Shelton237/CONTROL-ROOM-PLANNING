import type { CellValue } from "../types";

const CELL_CLASSES: Record<CellValue, string> = {
  J: "bg-jour-bg text-jour",
  N: "bg-nuit-bg text-nuit",
  R: "bg-repos-bg text-repos",
  ABS: "bg-abs-bg text-abs",
  "": "bg-white text-line",
};

export function cellClass(value: CellValue | undefined): string {
  return CELL_CLASSES[value ?? ""] ?? CELL_CLASSES[""];
}

export function StatusCell({ value }: { value: CellValue | undefined }) {
  const v = value ?? "";
  return (
    <td className={`border border-line text-center font-bold min-w-[48px] py-2 ${cellClass(v)}`}>
      {v || "·"}
    </td>
  );
}

export function Legend() {
  return (
    <div className="flex gap-4 flex-wrap text-xs text-muted my-2.5">
      <span className="inline-flex items-center gap-1.5">
        <i className="w-3.5 h-3.5 rounded-sm inline-block bg-jour-bg border border-jour" /> J Jour
      </span>
      <span className="inline-flex items-center gap-1.5">
        <i className="w-3.5 h-3.5 rounded-sm inline-block bg-nuit-bg border border-nuit" /> N Nuit
      </span>
      <span className="inline-flex items-center gap-1.5">
        <i className="w-3.5 h-3.5 rounded-sm inline-block bg-repos-bg border border-repos" /> R Repos
      </span>
      <span className="inline-flex items-center gap-1.5">
        <i className="w-3.5 h-3.5 rounded-sm inline-block bg-abs-bg border border-abs" /> Absent
      </span>
    </div>
  );
}
