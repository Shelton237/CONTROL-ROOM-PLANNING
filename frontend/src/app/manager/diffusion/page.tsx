"use client";

import { useCallback, useEffect, useState } from "react";
import { ManagerShell } from "../_components/ManagerShell";
import { usePlanningContext } from "../_components/PlanningContext";
import { useRooms } from "../_components/useRooms";
import { RoomSelect } from "../_components/RoomSelect";
import { WeekNav } from "../_components/WeekNav";
import { getDiffusionPreview, sendDiffusion } from "../api-manager";
import type { DiffusionEmail, DiffusionSendResponse } from "../types";
import { fmtShort } from "../date-utils";
import { ApiError } from "@/lib/auth";

export default function DiffusionPage() {
  return (
    <ManagerShell>
      <DiffusionTab />
    </ManagerShell>
  );
}

function DiffusionTab() {
  const { rooms, loading: roomsLoading } = useRooms();
  const { currentRoomId, setCurrentRoomId, weekStart, setWeekStart } = usePlanningContext();
  const [emails, setEmails] = useState<DiffusionEmail[]>([]);
  const [loading, setLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [sendResult, setSendResult] = useState<DiffusionSendResponse | null>(null);

  useEffect(() => {
    if (!currentRoomId && rooms.length) setCurrentRoomId(rooms[0].id);
  }, [rooms, currentRoomId, setCurrentRoomId]);

  const reload = useCallback(async () => {
    if (!currentRoomId) return;
    setLoading(true);
    setError(null);
    setSendResult(null);
    try {
      const data = await getDiffusionPreview(currentRoomId, weekStart);
      setEmails(data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur de chargement de l'aperçu de diffusion.");
    } finally {
      setLoading(false);
    }
  }, [currentRoomId, weekStart]);

  useEffect(() => {
    reload();
  }, [reload]);

  async function handleSend() {
    if (!currentRoomId) return;
    if (!confirm("Envoyer réellement les e-mails de planning pour cette semaine ?")) return;
    setSending(true);
    setError(null);
    try {
      const result = await sendDiffusion(currentRoomId, weekStart);
      setSendResult(result);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors de l'envoi de la diffusion.");
    } finally {
      setSending(false);
    }
  }

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
      <h2 className="text-lg font-semibold mb-1">Diffusion du planning</h2>
      <p className="text-sm text-muted mb-3">
        Aperçu du planning personnalisé par agent. « Envoyer » déclenche un envoi réel (best-effort) via
        le backend.
      </p>

      <div className="flex gap-3 items-end flex-wrap mb-3">
        <div>
          <label className="block text-xs font-semibold text-muted mb-1">Salle</label>
          <RoomSelect rooms={rooms} value={currentRoomId} onChange={setCurrentRoomId} />
        </div>
        <div>
          <label className="block text-xs font-semibold text-muted mb-1">Semaine</label>
          <WeekNav weekStart={weekStart} onChange={setWeekStart} showParity={false} />
        </div>
        <button
          type="button"
          disabled={sending || loading || emails.length === 0}
          className="bg-red hover:bg-red-dark text-white rounded-md px-4 py-2 text-sm font-semibold disabled:opacity-50"
          onClick={handleSend}
        >
          {sending ? "Envoi…" : "Envoyer les e-mails"}
        </button>
      </div>

      {error && (
        <div className="mb-4 text-sm text-red bg-abs-bg border border-red/30 rounded-md px-3 py-2">
          {error}
        </div>
      )}

      {sendResult && (
        <div className="mb-4 text-sm bg-[#fffbe6] border border-[#f0e0a0] rounded-md px-3 py-2">
          <b>{sendResult.sent.length}</b> envoi(s) réussi(s), <b>{sendResult.failed.length}</b> échec(s).
          {sendResult.failed.length > 0 && (
            <ul className="list-disc ml-5 mt-1">
              {sendResult.failed.map((f) => (
                <li key={f.employee_id}>
                  {f.name} {f.email ? `(${f.email})` : ""} : {f.error || "échec"}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      {loading ? (
        <div className="text-muted text-sm">Chargement…</div>
      ) : emails.length === 0 ? (
        <div className="text-muted text-sm text-center border border-dashed border-line rounded-md p-4">
          Aucun agent planifié.
        </div>
      ) : (
        emails.map((mail) => (
          <div key={mail.employee_id} className="border border-line rounded-lg p-3.5 mb-3">
            <div className="flex justify-between items-center gap-2.5 flex-wrap">
              <div>
                <b>{mail.name}</b>{" "}
                {mail.email ? (
                  <span className="text-xs text-muted">{mail.email}</span>
                ) : (
                  <span className="text-xs text-red">e-mail manquant</span>
                )}
              </div>
              <div className="text-xs text-muted">{mail.subject}</div>
            </div>
            <pre className="bg-[#fafafa] border border-line rounded-md p-2.5 text-[13px] whitespace-pre-wrap mt-2.5 font-sans">
              {mail.body}
            </pre>
          </div>
        ))
      )}

      <p className="text-xs text-muted mt-2">
        Semaine du {weekStart ? fmtShort(weekStart) : ""} — aperçu généré par le backend (même format
        que la référence legacy).
      </p>
    </div>
  );
}
