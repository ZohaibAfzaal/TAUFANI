import { createFileRoute } from "@tanstack/react-router";
import { AppShell } from "@/components/AppShell";
import { AddExpenseForm } from "@/components/AddExpenseForm";

export const Route = createFileRoute("/add-expense")({
  component: AddExpensePage,
  head: () => ({
    meta: [
      { title: "Add Expense — Taufani Split" },
      { name: "description", content: "Add a new expense and preview who owes whom." },
    ],
  }),
});

function AddExpensePage() {
  return (
    <AppShell>
      <div className="space-y-4">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight">Add Expense</h1>
          <p className="text-sm text-muted-foreground">Split it fairly, see it clearly.</p>
        </div>
        <AddExpenseForm />
      </div>
    </AppShell>
  );
}
