"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";
import { Header } from "@/components/Header";
import { AgentNav } from "./components/AgentNav";

export default function AgentLayout({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (loading) return;
    if (!user) router.replace("/login");
    else if (user.role !== "agent") router.replace("/manager");
  }, [user, loading, router]);

  if (loading || !user || user.role !== "agent") return null;

  return (
    <>
      <Header />
      <AgentNav />
      <main className="max-w-5xl w-full mx-auto p-5">{children}</main>
    </>
  );
}
