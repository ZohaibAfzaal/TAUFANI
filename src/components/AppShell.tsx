import { Link, useLocation } from "@tanstack/react-router";
import { LayoutDashboard, PlusCircle, Users, Handshake, RefreshCw } from "lucide-react";
import { useStore } from "@/lib/expense-store";

const NAV = [
  { to: "/", icon: LayoutDashboard, label: "Dashboard" },
  { to: "/add-expense", icon: PlusCircle, label: "Add" },
  { to: "/group", icon: Users, label: "Groups" },
  { to: "/settle", icon: Handshake, label: "Settle" },
] as const;

export function AppShell({ children }: { children: React.ReactNode }) {
  const location = useLocation();
  const { resetAllData } = useStore();

  return (
    <div className="min-h-screen flex flex-col bg-background">
      {/* Header */}
      <header className="sticky top-0 z-40 border-b bg-card/80 backdrop-blur-md">
        <div className="mx-auto flex h-14 max-w-lg items-center justify-between px-4">
          <div className="flex items-center gap-2">
            <span className="font-display text-lg font-bold tracking-tight text-primary">
              ⚡ Taufani Split
            </span>
          </div>
          <div className="flex items-center gap-3">
            <button
              onClick={() => {
                if (confirm("Reset all data to defaults?")) {
                  resetAllData();
                  window.location.reload();
                }
              }}
              className="p-2 text-muted-foreground hover:text-primary transition-colors"
              title="Reset Data"
            >
              <RefreshCw className="h-4 w-4" />
            </button>
            <span className="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground">
              No debt simplification
            </span>
          </div>
        </div>
      </header>

      {/* Content */}
      <main className="mx-auto w-full max-w-lg flex-1 px-4 py-6">{children}</main>

      {/* Bottom nav */}
      <nav className="sticky bottom-0 z-40 border-t bg-card/90 backdrop-blur-md">
        <div className="mx-auto flex max-w-lg items-center justify-around py-2">
          {NAV.map(({ to, icon: Icon, label }) => {
            const active = location.pathname === to;
            return (
              <Link
                key={to}
                to={to}
                className={`flex flex-col items-center gap-0.5 px-3 py-1 text-xs font-medium transition-colors ${active ? "text-primary" : "text-muted-foreground hover:text-foreground"}`}
              >
                <Icon className="h-5 w-5" />
                {label}
              </Link>
            );
          })}
        </div>
      </nav>
    </div>
  );
}
