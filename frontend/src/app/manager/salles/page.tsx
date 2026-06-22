"use client";

import { useEffect, useState } from "react";
import { ManagerShell } from "../_components/ManagerShell";
import { useDialog } from "../_components/DialogProvider";
import { useRooms } from "../_components/useRooms";
import { createRoom, deleteRoom, listEmployees, updateRoom } from "../api-manager";
import { ApiError } from "@/lib/auth";

export default function RoomsPage() {
  return (
    <ManagerShell>
      <RoomsTab />
    </ManagerShell>
  );
}

function RoomsTab() {
  const { confirm, prompt } = useDialog();
  const { rooms, loading, error: loadError, reload } = useRooms();
  const [newName, setNewName] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [counts, setCounts] = useState<Record<number, number>>({});
  const [hasFixed, setHasFixed] = useState<Record<number, boolean>>({});

  useEffect(() => {
    listEmployees()
      .then((employees) => {
        const nextCounts: Record<number, number> = {};
        const nextHasFixed: Record<number, boolean> = {};
        for (const e of employees) {
          nextCounts[e.room_id] = (nextCounts[e.room_id] || 0) + 1;
          if (e.type === "fixed_day") nextHasFixed[e.room_id] = true;
        }
        setCounts(nextCounts);
        setHasFixed(nextHasFixed);
      })
      .catch(() => {});
  }, [rooms]);

  async function handleAdd() {
    const name = newName.trim();
    if (!name) return;
    try {
      await createRoom(name);
      setNewName("");
      await reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors de la création de la salle.");
    }
  }

  async function handleRename(id: number, current: string) {
    const name = await prompt({ title: "Renommer la salle", label: "Nouveau nom", defaultValue: current });
    if (!name) return;
    try {
      await updateRoom(id, name);
      await reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors du renommage.");
    }
  }

  async function handleDelete(id: number) {
    const ok = await confirm({
      title: "Supprimer la salle",
      message: "Supprimer cette salle ?",
      confirmLabel: "Supprimer",
    });
    if (!ok) return;
    try {
      await deleteRoom(id);
      await reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors de la suppression.");
    }
  }

  return (
    <div className="bg-panel border border-line rounded-lg p-4">
      <h2 className="text-lg font-semibold mb-1">Salles de contrôle</h2>
      <p className="text-sm text-muted mb-3">
        Format verrouillé : <b>service de quart 24/7</b>, rotation stricte Jour · Nuit · Repos pour les
        agents tournants (3 binômes), plus l&apos;agent jour fixe (Sylvie) qui rejoint un seul agent en
        Jour le temps que ça coïncide. Jamais plus de 2 agents simultanément en Jour, ni plus de 2 en
        Nuit. Chaque salle doit avoir un agent fixe.
      </p>

      {(error || loadError) && (
        <div className="mb-4 text-sm text-red bg-abs-bg border border-red/30 rounded-md px-3 py-2">
          {error || loadError}
        </div>
      )}

      {loading ? (
        <div className="text-muted text-sm">Chargement…</div>
      ) : (
        <table className="border-collapse w-full text-sm mb-4">
          <thead>
            <tr>
              {["Salle", "Mode", "Effectif", ""].map((h) => (
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
            {rooms.length === 0 ? (
              <tr>
                <td colSpan={4} className="text-center text-muted text-sm p-3 border border-line">
                  Aucune salle
                </td>
              </tr>
            ) : (
              rooms.map((r) => (
                <tr key={r.id}>
                  <td className="border border-line px-2 py-1.5 font-semibold">
                    {r.name}
                    {!hasFixed[r.id] && (
                      <span className="ml-2 inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-abs-bg text-red">
                        Aucun agent fixe
                      </span>
                    )}
                  </td>
                  <td className="border border-line px-2 py-1.5">Quart 24/7 – J · N · R</td>
                  <td className="border border-line px-2 py-1.5">{counts[r.id] || 0} employés</td>
                  <td className="border border-line px-2 py-1.5 text-right whitespace-nowrap">
                    <button
                      className="text-red underline text-xs mr-2"
                      onClick={() => handleRename(r.id, r.name)}
                    >
                      Renommer
                    </button>
                    <button className="text-red underline text-xs" onClick={() => handleDelete(r.id)}>
                      Supprimer
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      )}

      <h3 className="text-sm font-semibold mb-2">Ajouter une salle</h3>
      <div className="flex gap-3 items-end flex-wrap">
        <div className="flex-1 min-w-[150px]">
          <label className="block text-xs font-semibold text-muted mb-1">Nom</label>
          <input
            className="w-full border border-line rounded-md px-3 py-2 text-sm"
            value={newName}
            onChange={(e) => setNewName(e.target.value)}
            placeholder="Ex : Talatamaty"
          />
        </div>
        <button
          type="button"
          className="bg-red hover:bg-red-dark text-white rounded-md px-4 py-2 text-sm font-semibold shrink-0"
          onClick={handleAdd}
        >
          + Ajouter
        </button>
      </div>
    </div>
  );
}
