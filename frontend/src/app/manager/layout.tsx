"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";
import { Header } from "@/components/Header";

export default function ManagerLayout({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (loading) return;
    if (!user) router.replace("/login");
    else if (user.role !== "manager") router.replace("/agent");
  }, [user, loading, router]);

  if (loading || !user || user.role !== "manager") return null;

  return (
    <>
      <Header />
      <main className="max-w-5xl w-full mx-auto p-5">{children}</main>
    </>
  );
}
