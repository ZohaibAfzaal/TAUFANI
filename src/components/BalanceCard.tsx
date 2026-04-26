import { motion } from "framer-motion";
import { ArrowUpRight, ArrowDownLeft } from "lucide-react";
import { useStore } from "@/lib/expense-store";

export function BalanceCard() {
  const { getUserBalance, getUserById } = useStore();
  const { owes, owed } = getUserBalance("you");
  const totalOwes = owes.reduce((s, b) => s + b.amount, 0);
  const totalOwed = owed.reduce((s, b) => s + b.amount, 0);

  return (
    <div className="space-y-4">
      {/* Summary cards */}
      <div className="grid grid-cols-2 gap-3">
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          className="rounded-xl border border-destructive/20 bg-destructive/5 p-4"
        >
          <div className="flex items-center gap-2 text-destructive">
            <ArrowUpRight className="h-4 w-4" />
            <span className="text-xs font-semibold uppercase tracking-wider">You owe</span>
          </div>
          <p className="mt-2 font-display text-2xl font-bold text-destructive">
            RS{totalOwes.toFixed(0)}
          </p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.05 }}
          className="rounded-xl border border-success/20 bg-success/5 p-4"
        >
          <div className="flex items-center gap-2 text-success">
            <ArrowDownLeft className="h-4 w-4" />
            <span className="text-xs font-semibold uppercase tracking-wider">You are owed</span>
          </div>
          <p className="mt-2 font-display text-2xl font-bold text-success">
            RS{totalOwed.toFixed(0)}
          </p>
        </motion.div>
      </div>

      {/* Detailed list */}
      <div className="space-y-2">
        {owes.map((b, i) => {
          const user = getUserById(b.to);
          return (
            <motion.div
              key={`owe-${b.to}`}
              initial={{ opacity: 0, x: -12 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: i * 0.04 }}
              className="flex items-center justify-between rounded-lg border bg-card px-4 py-3"
            >
              <div className="flex items-center gap-3">
                <span className="text-xl">{user?.avatar}</span>
                <span className="text-sm font-medium text-card-foreground">
                  You owe <strong>{user?.name}</strong>
                </span>
              </div>
              <span className="font-display text-sm font-bold text-destructive">
                RS{b.amount.toFixed(0)}
              </span>
            </motion.div>
          );
        })}
        {owed.map((b, i) => {
          const user = getUserById(b.from);
          return (
            <motion.div
              key={`owed-${b.from}`}
              initial={{ opacity: 0, x: -12 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: (owes.length + i) * 0.04 }}
              className="flex items-center justify-between rounded-lg border bg-card px-4 py-3"
            >
              <div className="flex items-center gap-3">
                <span className="text-xl">{user?.avatar}</span>
                <span className="text-sm font-medium text-card-foreground">
                  <strong>{user?.name}</strong> owes you
                </span>
              </div>
              <span className="font-display text-sm font-bold text-success">
                RS{b.amount.toFixed(0)}
              </span>
            </motion.div>
          );
        })}
        {owes.length === 0 && owed.length === 0 && (
          <p className="py-8 text-center text-sm text-muted-foreground">All settled up! 🎉</p>
        )}
      </div>
    </div>
  );
}
