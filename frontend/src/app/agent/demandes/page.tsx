"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { AgentNav } from "../components/AgentNav";
import type { Absence, AbsenceType, PermissionRequestPayload } from "../types";
import { fmtShort, hoursUntil } from "../lib/dates";

function StatusTag({ status }: { status: Absence["status"] }) {
  if (status === "enregistree") {
    return (
      <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-jour-bg text-jour">
        Enregistrée
      </span>
    );
  }
  return (
    <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-abs-bg text-abs">
      Refusée (&lt;48h)
    </span>
  );
}

function typeLabel(type: AbsenceType): string {
  return type === "permission" ? "Permission" : "Absence";
}

export default function AgentRequestsPage() {
  const [absences, setAbsences] = useState<Absence[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [reason, setReason] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [resultMessage, setResultMessage] = useState<{ ok: boolean; text: string } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const data = await api.get<Absence[]>("/me/absences");
      setAbsences(
        [...data].sort((a, b) => (b.created_at || "").localeCompare(a.created_at || ""))
      );
    } catch (err) {
      setLoadError(err instanceof ApiError ? err.message : "Impossible de charger l'historique.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const willBeRefused = startDate ? hoursUntil(startDate) < 48 : false;

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setFormError(null);
    setResultMessage(null);
    if (!startDate) {
      setFormError("La date de début est requise.");
      return;
    }
    setSubmitting(true);
    try {
      const payload: PermissionRequestPayload = {
        start_date: startDate,
        end_date: endDate || startDate,
        reason,
      };
      const created = await api.post<Absence>("/me/permissions", payload);
      if (created.status === "enregistree") {
        setResultMessage({
          ok: true,
          text: "Demande enregistrée (délai de 48 h respecté). Elle apparaîtra dans le planning.",
        });
      } else {
        setResultMessage({
          ok: false,
          text: "Demande refusée : moins de 48 h avant la date de début. Elle n'impacte pas le planning mais reste visible dans l'historique ci-dessous.",
        });
      }
      setStartDate("");
      setEndDate("");
      setReason("");
      await load();
    } catch (err) {
      setFormError(err instanceof ApiError ? err.message : "Impossible de soumettre la demande.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="bg-panel border border-line rounded-lg p-5">
      <AgentNav />
      <h2 className="text-lg font-semibold mb-1">Mes demandes</h2>
      <p className="text-sm text-muted mb-4">
        Historique de mes absences et permissions, et formulaire de nouvelle demande de permission.
      </p>

      <div className="bg-[#fffbe6] border border-[#f0e0a0] rounded-md px-3 py-2.5 text-sm mb-4">
        Une demande de permission doit être faite <b>au moins 48 h avant</b> la date de début. En
        dessous de ce délai, elle sera <b>automatiquement refusée</b> et n&apos;impactera pas le
        planning — mais elle restera visible dans l&apos;historique ci-dessous.
      </div>

      <form onSubmit={handleSubmit} className="flex gap-3 flex-wrap items-end mb-2">
        <div className="min-w-[150px]">
          <label className="block text-xs font-semibold text-muted mb-1">Du</label>
          <input
            type="date"
            value={startDate}
            onChange={(e) => setStartDate(e.target.value)}
            required
            className="border border-line rounded-md px-2.5 py-2 w-full focus:outline-none focus:border-red"
          />
        </div>
        <div className="min-w-[150px]">
          <label className="block text-xs font-semibold text-muted mb-1">Au</label>
          <input
            type="date"
            value={endDate}
            onChange={(e) => setEndDate(e.target.value)}
            className="border border-line rounded-md px-2.5 py-2 w-full focus:outline-none focus:border-red"
          />
        </div>
        <div className="flex-[2] min-w-[200px]">
          <label className="block text-xs font-semibold text-muted mb-1">Motif</label>
          <input
            type="text"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="Raison de la permission"
            className="border border-line rounded-md px-2.5 py-2 w-full focus:outline-none focus:border-red"
          />
        </div>
        <div>
          <button
            type="submit"
            disabled={submitting}
            className="bg-red hover:bg-red-dark text-white font-semibold rounded-md px-4 py-2 disabled:opacity-60"
          >
            {submitting ? "Envoi…" : "Soumettre la demande"}
          </button>
        </div>
      </form>

      {startDate && willBeRefused && !resultMessage && (
        <p className="text-xs text-red mb-3">
          Attention : il reste moins de 48 h avant cette date. Cette demande sera automatiquement
          refusée.
        </p>
      )}

      {formError && (
        <div className="text-sm text-red bg-abs-bg border border-red/30 rounded-md px-3 py-2 mb-3">
          {formError}
        </div>
      )}
      {resultMessage && (
        <div
          className={`text-sm rounded-md px-3 py-2 mb-3 border ${
            resultMessage.ok
              ? "text-jour bg-jour-bg border-jour/30"
              : "text-red bg-abs-bg border-red/30"
          }`}
        >
          {resultMessage.text}
        </div>
      )}

      <h3 className="text-sm font-semibold mt-6 mb-2">Mon historique</h3>
      {loading && <p className="text-sm text-muted">Chargement…</p>}
      {loadError && (
        <div className="text-sm text-red bg-abs-bg border border-red/30 rounded-md px-3 py-2 mb-3">
          {loadError}
        </div>
      )}
      {!loading && !loadError && (
        <table className="border-collapse w-full text-sm">
          <thead>
            <tr>
              <th className="border border-line bg-gray-50 text-[12.5px] uppercase tracking-wide text-muted text-left py-1.5 px-2">
                Type
              </th>
              <th className="border border-line bg-gray-50 text-[12.5px] uppercase tracking-wide text-muted text-left py-1.5 px-2">
                Dates
              </th>
              <th className="border border-line bg-gray-50 text-[12.5px] uppercase tracking-wide text-muted text-left py-1.5 px-2">
                Motif
              </th>
              <th className="border border-line bg-gray-50 text-[12.5px] uppercase tracking-wide text-muted text-left py-1.5 px-2">
                Statut
              </th>
            </tr>
          </thead>
          <tbody>
            {absences.length === 0 && (
              <tr>
                <td colSpan={4} className="text-muted text-sm p-3.5 text-center border border-dashed border-line">
                  Aucune demande
                </td>
              </tr>
            )}
            {absences.map((a) => (
              <tr key={a.id}>
                <td className="border border-line py-1.5 px-2">{typeLabel(a.type)}</td>
                <td className="border border-line py-1.5 px-2">
                  {fmtShort(a.start_date)}
                  {a.end_date && a.end_date !== a.start_date ? ` – ${fmtShort(a.end_date)}` : ""}
                </td>
                <td className="border border-line py-1.5 px-2">{a.reason || ""}</td>
                <td className="border border-line py-1.5 px-2">
                  <StatusTag status={a.status} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
