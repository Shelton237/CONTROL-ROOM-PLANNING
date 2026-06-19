"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth";
import { Header } from "@/components/Header";
import { TabNav } from "./_components/TabNav";

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
      <TabNav />
      <main className="max-w-[1180px] w-full mx-auto p-5">{children}</main>
    </>
  );
}
