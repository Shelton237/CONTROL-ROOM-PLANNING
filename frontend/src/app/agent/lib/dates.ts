// Helpers de dates portés depuis docs/legacy-prototype.html (mêmes formules),
// pour rester cohérent avec le calcul des semaines ISO côté manager/backend.

export const DAYS = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche"];
export const DSHORT = ["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"];
export const HOURS: Record<string, string> = {
  J: "07h30 – 17h30",
  N: "17h30 – 07h30 (lendemain)",
};

function ymd(d: Date): string {
  return (
    d.getFullYear() +
    "-" +
    String(d.getMonth() + 1).padStart(2, "0") +
    "-" +
    String(d.getDate()).padStart(2, "0")
  );
}

export function todayISO(): string {
  return ymd(new Date());
}

function isoUTC(iso: string): number {
  return Date.parse(iso + "T00:00:00Z");
}

export function addDaysISO(iso: string, n: number): string {
  return new Date(isoUTC(iso) + n * 864e5).toISOString().slice(0, 10);
}

export function dowISO(iso: string): number {
  // 0=Lundi … 6=Dimanche
  return (new Date(isoUTC(iso)).getUTCDay() + 6) % 7;
}

export function mondayISOof(iso: string): string {
  return addDaysISO(iso, -dowISO(iso));
}

export function isoWeekNum(iso: string): number {
  const d = new Date(isoUTC(iso));
  const dn = (d.getUTCDay() + 6) % 7;
  d.setUTCDate(d.getUTCDate() - dn + 3);
  const ft = new Date(Date.UTC(d.getUTCFullYear(), 0, 4));
  const ftd = (ft.getUTCDay() + 6) % 7;
  ft.setUTCDate(ft.getUTCDate() - ftd + 3);
  return 1 + Math.round((d.getTime() - ft.getTime()) / (7 * 864e5));
}

export function weekParity(iso: string): number {
  return isoWeekNum(iso) % 2;
}

export function weekDatesISO(monIso: string): string[] {
  const out: string[] = [];
  for (let i = 0; i < 7; i++) out.push(addDaysISO(monIso, i));
  return out;
}

export function fmtShort(iso: string): string {
  const d = new Date(isoUTC(iso));
  return d.toLocaleDateString("fr-FR", { day: "2-digit", month: "short", timeZone: "UTC" });
}

export function monthWeeks(anchorIso: string): { weeks: string[]; monthName: string } {
  const ad = new Date(isoUTC(anchorIso));
  const y = ad.getUTCFullYear();
  const m = ad.getUTCMonth();
  const firstISO = `${y}-${String(m + 1).padStart(2, "0")}-01`;
  const lastISO = new Date(Date.UTC(y, m + 1, 0)).toISOString().slice(0, 10);
  let cur = mondayISOof(firstISO);
  const out: string[] = [];
  while (isoUTC(cur) <= isoUTC(lastISO) && out.length < 6) {
    out.push(cur);
    cur = addDaysISO(cur, 7);
  }
  return {
    weeks: out,
    monthName: ad.toLocaleDateString("fr-FR", { month: "long", year: "numeric", timeZone: "UTC" }),
  };
}

export function shiftMonthAnchor(anchorIso: string, n: number): string {
  const d = new Date(isoUTC(anchorIso));
  d.setUTCMonth(d.getUTCMonth() + n);
  return d.toISOString().slice(0, 10);
}

/** Heures restantes avant le début d'une date (comparé à "maintenant", heure locale du navigateur). */
export function hoursUntil(startIso: string): number {
  return (Date.parse(startIso + "T00:00:00") - Date.now()) / 3600000;
}
