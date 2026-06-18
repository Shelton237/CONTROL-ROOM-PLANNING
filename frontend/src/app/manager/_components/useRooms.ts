"use client";

import { useCallback, useEffect, useState } from "react";
import { listRooms } from "../api-manager";
import type { Room } from "../types";

export function useRooms() {
  const [rooms, setRooms] = useState<Room[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await listRooms();
      setRooms(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Erreur de chargement des salles.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    reload();
  }, [reload]);

  return { rooms, loading, error, reload };
}
