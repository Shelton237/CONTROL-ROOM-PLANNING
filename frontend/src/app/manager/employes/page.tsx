"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { ManagerShell } from "../_components/ManagerShell";
import { useRooms } from "../_components/useRooms";
import { createEmployee, deleteEmployee, listEmployees, updateEmployee } from "../api-manager";
import type { DaySpecValue, Employee, EmployeeType } from "../types";
import { DSHORT } from "../date-utils";
import { ApiError } from "@/lib/auth";

function defaultSpec(): DaySpecValue[] {
  const s: DaySpecValue[] = Array(7).fill("off");
  for (let i = 0; i < 6; i++) s[i] = "on";
  return s;
}

function emptyForm() {
  return {
    id: null as number | null,
    name: "",
    email: "",
    roomId: "" as string | number,
    type: "rotation" as EmployeeType,
    daySpec: defaultSpec(),
    altParity: 0 as 0 | 1,
  };
}

export default function EmployeesPage() {
  return (
    <ManagerShell>
      <EmployeesTab />
    </ManagerShell>
  );
}

function EmployeesTab() {
  const { rooms, loading: roomsLoading } = useRooms();
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filterRoomId, setFilterRoomId] = useState<string>("");
  const [form, setForm] = useState(emptyForm());

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await listEmployees(filterRoomId ? Number(filterRoomId) : undefined);
      setEmployees(data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur de chargement des employés.");
    } finally {
      setLoading(false);
    }
  }, [filterRoomId]);

  useEffect(() => {
    reload();
  }, [reload]);

  useEffect(() => {
    if (!form.roomId && rooms.length) setForm((f) => ({ ...f, roomId: rooms[0].id }));
  }, [rooms, form.roomId]);

  function startEdit(e: Employee) {
    setForm({
      id: e.id,
      name: e.name,
      email: e.email ?? "",
      roomId: e.room_id,
      type: e.type,
      daySpec: e.day_spec ?? defaultSpec(),
      altParity: (e.alt_parity ?? 0) as 0 | 1,
    });
  }

  function cancelEdit() {
    setForm(emptyForm());
  }

  function toggleDay(d: number) {
    setForm((f) => {
      const spec = [...f.daySpec];
      const cur = spec[d];
      spec[d] = cur === "on" ? "alt" : cur === "alt" ? "off" : "on";
      return { ...f, daySpec: spec };
    });
  }

  async function handleSave() {
    if (!form.name.trim()) {
      alert("Nom requis.");
      return;
    }
    if (!form.roomId) {
      alert("Salle requise.");
      return;
    }
    const payload = {
      room_id: Number(form.roomId),
      name: form.name.trim(),
      email: form.email.trim() || null,
      type: form.type,
      day_spec: form.type === "fixed_day" ? form.daySpec : null,
      alt_parity: form.type === "fixed_day" ? form.altParity : null,
    };
    try {
      if (form.id) {
        await updateEmployee(form.id, payload);
      } else {
        await createEmployee(payload);
      }
      cancelEdit();
      await reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors de l'enregistrement.");
    }
  }

  async function handleDelete(id: number) {
    if (!confirm("Supprimer cet employé ?")) return;
    try {
      await deleteEmployee(id);
      await reload();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Erreur lors de la suppression.");
    }
  }

  const roomName = useMemo(() => {
    const map = new Map(rooms.map((r) => [r.id, r.name]));
    return (id: number) => map.get(id) ?? "?";
  }, [rooms]);

  return (
    <div className="bg-panel border border-line rounded-lg p-4">
      <h2 className="text-lg font-semibold mb-1">Employés</h2>
      <p className="text-sm text-muted mb-3">
        <b>Rotation</b> = agent tournant J/N/R (groupé en binômes dans l&apos;ordre d&apos;ajout).{" "}
        <b>Jour fixe</b> = agent de journée avec jours fixes (alternance possible 1 semaine sur 2).
        L&apos;e-mail sert à la diffusion.
      </p>

      {error && (
        <div className="mb-4 text-sm text-red bg-abs-bg border border-red/30 rounded-md px-3 py-2">
          {error}
        </div>
      )}

      <div className="mb-3">
        <label className="block text-xs font-semibold text-muted mb-1">Filtrer par salle</label>
        <select
          value={filterRoomId}
          onChange={(e) => setFilterRoomId(e.target.value)}
          className="border border-line rounded-md px-3 py-2 bg-white text-sm"
        >
          <option value="">Toutes les salles</option>
          {rooms.map((r) => (
            <option key={r.id} value={r.id}>
              {r.name}
            </option>
          ))}
        </select>
      </div>

      {loading || roomsLoading ? (
        <div className="text-muted text-sm">Chargement…</div>
      ) : (
        <table className="border-collapse w-full text-sm mb-4">
          <thead>
            <tr>
              {["Nom", "E-mail", "Salle", "Type", "Jours (si fixe)", ""].map((h) => (
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
            {employees.length === 0 ? (
              <tr>
                <td colSpan={6} className="text-center text-muted text-sm p-3 border border-line">
                  Aucun employé
                </td>
              </tr>
            ) : (
              employees.map((e) => {
                const days =
                  e.type === "fixed_day" && e.day_spec
                    ? e.day_spec
                        .map((s, d) => (s === "on" ? DSHORT[d] : s === "alt" ? `${DSHORT[d]}½` : null))
                        .filter(Boolean)
                        .join(", ")
                    : "–";
                return (
                  <tr key={e.id}>
                    <td className="border border-line px-2 py-1.5 font-semibold">{e.name}</td>
                    <td className="border border-line px-2 py-1.5">
                      {e.email || <span className="text-xs text-red">e-mail manquant</span>}
                    </td>
                    <td className="border border-line px-2 py-1.5">{roomName(e.room_id)}</td>
                    <td className="border border-line px-2 py-1.5">
                      {e.type === "fixed_day" ? (
                        <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#fdeede] text-[#9a6212]">
                          Jour fixe
                        </span>
                      ) : (
                        <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#eef] text-[#33408a]">
                          Rotation · binôme {e.binome ?? "?"}
                        </span>
                      )}
                    </td>
                    <td className="border border-line px-2 py-1.5">{days}</td>
                    <td className="border border-line px-2 py-1.5 text-right whitespace-nowrap">
                      <button className="text-red underline text-xs mr-2" onClick={() => startEdit(e)}>
                        Modifier
                      </button>
                      <button
                        className="text-red underline text-xs"
                        onClick={() => handleDelete(e.id)}
                      >
                        Supprimer
                      </button>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      )}

      <h3 className="text-sm font-semibold mt-4 mb-2">
        {form.id ? "Modifier l'employé" : "Ajouter un employé"}
      </h3>
      <div className="flex gap-3 flex-wrap">
        <div className="flex-1 min-w-[150px]">
          <label className="block text-xs font-semibold text-muted mb-1">Nom complet</label>
          <input
            className="w-full border border-line rounded-md px-3 py-2 text-sm"
            value={form.name}
            onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
            placeholder="Prénom Nom"
          />
        </div>
        <div className="flex-1 min-w-[150px]">
          <label className="block text-xs font-semibold text-muted mb-1">E-mail</label>
          <input
            type="email"
            className="w-full border border-line rounded-md px-3 py-2 text-sm"
            value={form.email}
            onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
            placeholder="prenom@thara-services.mg"
          />
        </div>
        <div className="flex-1 min-w-[150px]">
          <label className="block text-xs font-semibold text-muted mb-1">Salle</label>
          <select
            className="w-full border border-line rounded-md px-3 py-2 text-sm bg-white"
            value={form.roomId}
            onChange={(e) => setForm((f) => ({ ...f, roomId: e.target.value }))}
          >
            {rooms.map((r) => (
              <option key={r.id} value={r.id}>
                {r.name}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="flex gap-3 flex-wrap mt-2.5">
        <div className="flex-none w-[200px]">
          <label className="block text-xs font-semibold text-muted mb-1">Type</label>
          <select
            className="w-full border border-line rounded-md px-3 py-2 text-sm bg-white"
            value={form.type}
            onChange={(e) => setForm((f) => ({ ...f, type: e.target.value as EmployeeType }))}
          >
            <option value="rotation">Rotation Jour / Nuit</option>
            <option value="fixed_day">Jour fixe</option>
          </select>
        </div>
        <div className="flex-[2] min-w-[260px]">
          <label className="block text-xs font-semibold text-muted mb-1">
            Jours <span className="text-xs text-muted">(agents « Jour fixe » uniquement)</span>
          </label>
          <div
            className={`flex gap-1.5 flex-wrap ${form.type !== "fixed_day" ? "opacity-40 pointer-events-none" : ""}`}
          >
            {DSHORT.map((d, i) => {
              const st = form.daySpec[i];
              return (
                <span
                  key={i}
                  onClick={() => toggleDay(i)}
                  className={`px-2.5 py-1.5 border rounded-md cursor-pointer text-xs select-none ${
                    st === "on"
                      ? "bg-charcoal text-white border-charcoal"
                      : st === "alt"
                      ? "bg-white text-red border-2 border-dashed border-red font-bold"
                      : "border-line"
                  }`}
                >
                  {d}
                  {st === "alt" ? " ½" : ""}
                </span>
              );
            })}
          </div>
          {form.type !== "fixed_day" && (
            <div className="text-xs text-muted mt-1.5">
              Non applicable : un agent en rotation suit le cycle J-N-R automatique.
            </div>
          )}
        </div>
        <div className="flex-none w-[190px]">
          <label className="block text-xs font-semibold text-muted mb-1">« 1 sem/2 » les…</label>
          <select
            disabled={form.type !== "fixed_day"}
            className="w-full border border-line rounded-md px-3 py-2 text-sm bg-white disabled:opacity-50"
            value={form.altParity}
            onChange={(e) => setForm((f) => ({ ...f, altParity: Number(e.target.value) as 0 | 1 }))}
          >
            <option value={0}>Semaines paires</option>
            <option value={1}>Semaines impaires</option>
          </select>
        </div>
      </div>

      <div className="mt-4 flex gap-2">
        <button
          type="button"
          className="bg-red hover:bg-red-dark text-white rounded-md px-4 py-2 text-sm font-semibold"
          onClick={handleSave}
        >
          Enregistrer
        </button>
        {form.id && (
          <button
            type="button"
            className="bg-white border border-line rounded-md px-4 py-2 text-sm font-semibold"
            onClick={cancelEdit}
          >
            Annuler
          </button>
        )}
      </div>
    </div>
  );
}
