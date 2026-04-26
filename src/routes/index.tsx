import { createFileRoute } from "@tanstack/react-router";
import { AppShell } from "@/components/AppShell";
import { BalanceCard } from "@/components/BalanceCard";

export const Route = createFileRoute("/")({
  component: DashboardPage,
  head: () => ({
    meta: [
      { title: "Taufani Split — Dashboard" },
      { name: "description", content: "See exactly who you owe and who owes you. No simplified debts." },
    ],
  }),
});

function DashboardPage() {
  return (
    <AppShell>
      <div className="space-y-4">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight">Dashboard</h1>
          <p className="text-sm text-muted-foreground">Direct balances — no debt simplification.</p>
        </div>
        <BalanceCard />
      </div>
    </AppShell>
  );
}
