export function Legend() {
  const items: { label: string; bg: string; border: string }[] = [
    { label: "J Jour", bg: "bg-jour-bg", border: "border-jour" },
    { label: "N Nuit", bg: "bg-nuit-bg", border: "border-nuit" },
    { label: "R Repos", bg: "bg-repos-bg", border: "border-repos" },
    { label: "Absent", bg: "bg-abs-bg", border: "border-abs" },
  ];
  return (
    <div className="flex gap-4 flex-wrap text-xs text-muted my-2.5">
      {items.map((it) => (
        <span key={it.label} className="inline-flex items-center gap-1.5">
          <i className={`w-3.5 h-3.5 rounded-sm inline-block border ${it.bg} ${it.border}`} />
          {it.label}
        </span>
      ))}
    </div>
  );
}

export function CellTag({ cross, type, binome }: { cross: boolean; type: "rotation" | "fixed_day"; binome: number | null }) {
  if (cross) {
    return (
      <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-abs-bg text-red border border-dashed border-red ml-1.5">
        prêté
      </span>
    );
  }
  if (type === "fixed_day") {
    return (
      <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#fdeede] text-[#9a6212] ml-1.5">
        fixe
      </span>
    );
  }
  return (
    <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#eef] text-[#33408a] ml-1.5">
      B{binome ?? ""}
    </span>
  );
}
