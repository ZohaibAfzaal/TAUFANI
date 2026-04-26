import { createFileRoute } from "@tanstack/react-router";
import { AppShell } from "@/components/AppShell";
import { GroupView } from "@/components/GroupView";

export const Route = createFileRoute("/group")({
  component: GroupPage,
  head: () => ({
    meta: [
      { title: "Groups — Taufani Split" },
      { name: "description", content: "View group expenses and pairwise balances." },
    ],
  }),
});

function GroupPage() {
  return (
    <AppShell>
      <div className="space-y-4">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight">Groups</h1>
          <p className="text-sm text-muted-foreground">Expenses & pairwise balances per group.</p>
        </div>
        <GroupView />
      </div>
    </AppShell>
  );
}
