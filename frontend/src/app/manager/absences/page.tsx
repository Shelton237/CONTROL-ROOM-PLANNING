"use client";

import { useCallback, useEffect, useState } from "react";
import { ManagerShell } from "../_components/ManagerShell";
import { useDialog } from "../_components/DialogProvider";
import {
  createAbsence,
  createPermission,
  deleteAbsence,
  listAbsences,
} from "../api-manager";
import { listEmployees } from "../api-manager";
import type { Absence, Employee } from "../types";
import { fmtShort } from "../date-utils";
import { ApiError } from "@/lib/auth";

export default function AbsencesPage() {
  return (
    <ManagerShell>
      <AbsencesTab />
    </ManagerShell>
  );
}

function emptyAbsForm() {
  return { employeeId: "", start: "", end: "", reason: "" };
}

function AbsencesTab() {
  const { confirm } = useDialog();
  const [absences, setAbsences] = useState<Absence[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const [absForm, setAbsForm] = useState(emptyAbsForm());
  const [permForm, setPermForm] = useState(emptyAbsForm());

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [abs, emps] = await Promise.all([listAbsences(), listEmployees()]);
      setAbsences(abs);
      setEmployees(emps);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur de chargement.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    reload();
  }, [reload]);

  function employeeName(id: number) {
    return employees.find((e) => e.id === id)?.name ?? "?";
  }

  async function handleAddAbsence() {
    if (!absForm.employeeId || !absForm.start) {
      setError("Agent et date de début requis.");
      return;
    }
    setError(null);
    try {
      await createAbsence({
        employee_id: Number(absForm.employeeId),
        start_date: absForm.start,
        end_date: absForm.end || absForm.start,
        reason: absForm.reason.trim() || undefined,
      });
      setAbsForm(emptyAbsForm());
      setNotice(null);
      await reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors de la déclaration de l'absence.");
    }
  }

  async function handleAddPermission() {
    if (!permForm.employeeId || !permForm.start) {
      setError("Agent et date requis.");
      return;
    }
    setError(null);
    try {
      const result = await createPermission({
        employee_id: Number(permForm.employeeId),
        start_date: permForm.start,
        end_date: permForm.end || permForm.start,
        reason: permForm.reason.trim() || undefined,
      });
      setPermForm(emptyAbsForm());
      if (result.status === "refusee") {
        setNotice(
          "Demande REFUSÉE : le délai de 48 h avant la date n'est pas respecté. La demande n'est pas prise en compte dans le planning, mais reste visible dans l'historique."
        );
      } else {
        setNotice("Demande enregistrée (délai de 48 h respecté). Elle apparaît dans le planning.");
      }
      await reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors de la soumission de la permission.");
    }
  }

  async function handleDelete(id: number) {
    const ok = await confirm({
      title: "Annuler la demande",
      message: "Annuler cette absence/demande ?",
      confirmLabel: "Annuler la demande",
    });
    if (!ok) return;
    try {
      await deleteAbsence(id);
      await reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors de la suppression.");
    }
  }

  return (
    <div className="bg-panel border border-line rounded-lg p-4">
      <h2 className="text-lg font-semibold mb-1">Absences &amp; demandes</h2>
      <p className="text-sm text-muted mb-3">
        Le responsable peut déclarer une <b>absence</b> directement (maladie, urgence – sans délai).
        Une <b>demande de permission</b> n&apos;est <b>enregistrée que si elle est faite au moins 48 h à
        l&apos;avance</b> ; sinon elle est refusée et n&apos;impacte pas le planning.
      </p>

      {error && (
        <div className="mb-4 text-sm text-red bg-abs-bg border border-red/30 rounded-md px-3 py-2">
          {error}
        </div>
      )}
      {notice && (
        <div className="mb-4 text-sm bg-[#fffbe6] border border-[#f0e0a0] rounded-md px-3 py-2">
          {notice}
        </div>
      )}

      <h3 className="text-sm font-semibold mb-2">Déclarer une absence (responsable – immédiat)</h3>
      <div className="flex gap-3 items-end flex-wrap mb-4">
        <div>
          <label className="block text-xs font-semibold text-muted mb-1">Agent</label>
          <select
            className="border border-line rounded-md px-3 py-2 bg-white text-sm"
            value={absForm.employeeId}
            onChange={(e) => setAbsForm((f) => ({ ...f, employeeId: e.target.value }))}
          >
            <option value="">– choisir –</option>
            {employees.map((e) => (
              <option key={e.id} value={e.id}>
                {e.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-semibold text-muted mb-1">Du</label>
          <input
            type="date"
            className="border border-line rounded-md px-3 py-2 text-sm"
            value={absForm.start}
            onChange={(e) => setAbsForm((f) => ({ ...f, start: e.target.value }))}
          />
        </div>
        <div>
          <label className="block text-xs font-semibold text-muted mb-1">Au</label>
          <input
            type="date"
            className="border border-line rounded-md px-3 py-2 text-sm"
            value={absForm.end}
            onChange={(e) => setAbsForm((f) => ({ ...f, end: e.target.value }))}
          />
        </div>
        <div className="flex-[2] min-w-[180px]">
          <label className="block text-xs font-semibold text-muted mb-1">Motif</label>
          <input
            className="w-full border border-line rounded-md px-3 py-2 text-sm"
            value={absForm.reason}
            onChange={(e) => setAbsForm((f) => ({ ...f, reason: e.target.value }))}
            placeholder="Maladie, urgence…"
          />
        </div>
        <button
          type="button"
          className="bg-red hover:bg-red-dark text-white rounded-md px-4 py-2 text-sm font-semibold"
          onClick={handleAddAbsence}
        >
          Déclarer
        </button>
      </div>

      <h3 className="text-sm font-semibold mb-2">Saisir une demande de permission (règle des 48 h)</h3>
      <div className="flex gap-3 items-end flex-wrap mb-2">
        <div>
          <label className="block text-xs font-semibold text-muted mb-1">Agent</label>
          <select
            className="border border-line rounded-md px-3 py-2 bg-white text-sm"
            value={permForm.employeeId}
            onChange={(e) => setPermForm((f) => ({ ...f, employeeId: e.target.value }))}
          >
            <option value="">– choisir –</option>
            {employees.map((e) => (
              <option key={e.id} value={e.id}>
                {e.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-semibold text-muted mb-1">Du</label>
          <input
            type="date"
            className="border border-line rounded-md px-3 py-2 text-sm"
            value={permForm.start}
            onChange={(e) => setPermForm((f) => ({ ...f, start: e.target.value }))}
          />
        </div>
        <div>
          <label className="block text-xs font-semibold text-muted mb-1">Au</label>
          <input
            type="date"
            className="border border-line rounded-md px-3 py-2 text-sm"
            value={permForm.end}
            onChange={(e) => setPermForm((f) => ({ ...f, end: e.target.value }))}
          />
        </div>
        <div className="flex-[2] min-w-[180px]">
          <label className="block text-xs font-semibold text-muted mb-1">Motif</label>
          <input
            className="w-full border border-line rounded-md px-3 py-2 text-sm"
            value={permForm.reason}
            onChange={(e) => setPermForm((f) => ({ ...f, reason: e.target.value }))}
            placeholder="Raison personnelle…"
          />
        </div>
        <button
          type="button"
          className="bg-charcoal text-white rounded-md px-4 py-2 text-sm font-semibold"
          onClick={handleAddPermission}
        >
          Soumettre
        </button>
      </div>
      <p className="text-xs text-muted mb-4">
        Le système calcule l&apos;écart avec maintenant : &lt; 48 h → refusée et non enregistrée (mais
        reste visible ci-dessous).
      </p>

      <h3 className="text-sm font-semibold mb-2">Historique</h3>
      {loading ? (
        <div className="text-muted text-sm">Chargement…</div>
      ) : (
        <table className="border-collapse w-full text-sm">
          <thead>
            <tr>
              {["Agent", "Type", "Dates", "Motif", "Statut", ""].map((h) => (
                <th
                  key={h}
                  className="border border-line px-2 py-1.5 text-left bg-[#fafafa] text-[12.5px] uppercase tracking-wide text-muted"
                >
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {absences.length === 0 ? (
              <tr>
                <td colSpan={6} className="text-center text-muted text-sm p-3 border border-line">
                  Aucune absence ni demande
                </td>
              </tr>
            ) : (
              absences.map((a) => (
                <tr key={a.id}>
                  <td className="border border-line px-2 py-1.5 font-semibold">
                    {a.employee?.name ?? employeeName(a.employee_id)}
                  </td>
                  <td className="border border-line px-2 py-1.5">
                    {a.type === "permission" ? "Permission" : "Absence"}
                  </td>
                  <td className="border border-line px-2 py-1.5">
                    {fmtShort(a.start_date)}
                    {a.end_date && a.end_date !== a.start_date ? ` – ${fmtShort(a.end_date)}` : ""}
                  </td>
                  <td className="border border-line px-2 py-1.5">{a.reason || ""}</td>
                  <td className="border border-line px-2 py-1.5">
                    {a.status === "enregistree" ? (
                      <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-jour-bg text-jour">
                        Enregistrée
                      </span>
                    ) : (
                      <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-abs-bg text-abs">
                        Refusée (&lt;48h)
                      </span>
                    )}
                  </td>
                  <td className="border border-line px-2 py-1.5 text-right">
                    {a.status === "enregistree" && (
                      <button
                        className="text-red underline text-xs"
                        onClick={() => handleDelete(a.id)}
                      >
                        Annuler
                      </button>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      )}
    </div>
  );
}
