import { createFileRoute } from "@tanstack/react-router";
import { AppShell } from "@/components/AppShell";
import { SettleForm } from "@/components/SettleForm";

export const Route = createFileRoute("/settle")({
  component: SettlePage,
  head: () => ({
    meta: [
      { title: "Settle Up — Taufani Split" },
      { name: "description", content: "Pay exactly who you owe. Direct settlements only." },
    ],
  }),
});

function SettlePage() {
  return (
    <AppShell>
      <div className="space-y-4">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight">Settle Up</h1>
          <p className="text-sm text-muted-foreground">Pay directly — no redirected payments.</p>
        </div>
        <SettleForm />
      </div>
    </AppShell>
  );
}
