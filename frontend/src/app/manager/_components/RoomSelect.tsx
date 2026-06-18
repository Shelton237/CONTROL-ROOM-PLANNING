"use client";

import type { Room } from "../types";

type Props = {
  rooms: Room[];
  value: number | null;
  onChange: (id: number) => void;
  id?: string;
};

export function RoomSelect({ rooms, value, onChange, id }: Props) {
  return (
    <select
      id={id}
      value={value ?? ""}
      onChange={(e) => onChange(Number(e.target.value))}
      className="border border-line rounded-md px-3 py-2 bg-white text-sm"
    >
      {rooms.map((r) => (
        <option key={r.id} value={r.id}>
          {r.name}
        </option>
      ))}
    </select>
  );
}
