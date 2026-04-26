import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { useStore } from "@/lib/expense-store";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Check, Handshake, History, ArrowRight } from "lucide-react";

export function SettleForm() {
  const {
    getUserBalance,
    getUserById,
    groups,
    addSettlement,
    getPairBalances,
    settlements,
    getGroupById,
  } = useStore();
  const [groupId, setGroupId] = useState(groups[0]?.id || "");
  const [payerId, setPayerId] = useState("you");
  const [payeeId, setPayeeId] = useState<string | null>(null);
  const [amount, setAmount] = useState("");
  const [done, setDone] = useState(false);

  const balances = getPairBalances(groupId);
  const payerOwes = balances.filter((b) => b.from === payerId);

  const handleSettle = () => {
    if (!payeeId || !parseFloat(amount)) return;
    addSettlement({
      from: payerId,
      to: payeeId,
      amount: parseFloat(amount),
      groupId,
    });
    setDone(true);
  };

  const reset = () => {
    setPayeeId(null);
    setAmount("");
    setDone(false);
  };

  const group = getGroupById(groupId);

  return (
    <div className="space-y-8">
      <div className="space-y-5">
        <AnimatePresence mode="wait">
          {done ? (
            <motion.div
              key="done"
              initial={{ scale: 0.9, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.9, opacity: 0 }}
              className="flex flex-col items-center gap-4 py-8"
            >
              <div className="flex h-16 w-16 items-center justify-center rounded-full bg-success/10">
                <Check className="h-8 w-8 text-success" />
              </div>
              <div className="text-center">
                <h2 className="font-display text-xl font-bold">Payment Recorded!</h2>
                <p className="text-sm text-muted-foreground mt-1">
                  RS{amount} paid from {getUserById(payerId)?.name} to{" "}
                  {getUserById(payeeId!)?.name}
                </p>
              </div>
              <Button onClick={reset} className="mt-2">
                Settle Another
              </Button>
            </motion.div>
          ) : (
            <motion.div
              key="form"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="space-y-5"
            >
              <div className="rounded-xl border border-warning/30 bg-warning/5 p-4">
                <div className="flex items-center gap-2 text-warning-foreground">
                  <Handshake className="h-4 w-4" />
                  <p className="text-xs font-semibold">
                    Direct settlement only — no debt redirecting.
                  </p>
                </div>
              </div>

              {/* Group */}
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                  Group
                </Label>
                <div className="flex flex-wrap gap-2">
                  {groups.map((g) => (
                    <button
                      key={g.id}
                      onClick={() => {
                        setGroupId(g.id);
                        setPayeeId(null);
                      }}
                      className={`rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors ${
                        groupId === g.id
                          ? "border-primary bg-primary text-primary-foreground"
                          : "border-border bg-card text-card-foreground hover:bg-accent"
                      }`}
                    >
                      <span>{g.emoji}</span> {g.name}
                    </button>
                  ))}
                </div>
              </div>

              {/* Payer Selection */}
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                  Who is paying?
                </Label>
                <div className="flex flex-wrap gap-2">
                  {group?.members.map((mId) => {
                    const u = getUserById(mId);
                    return (
                      <button
                        key={mId}
                        onClick={() => {
                          setPayerId(mId);
                          setPayeeId(null);
                        }}
                        className={`flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors ${
                          payerId === mId
                            ? "border-primary bg-primary text-primary-foreground"
                            : "border-border bg-card text-card-foreground hover:bg-accent"
                        }`}
                      >
                        <span>{u?.avatar}</span> {u?.name}
                      </button>
                    );
                  })}
                </div>
              </div>

              {/* Payee Selection */}
              <div className="space-y-1.5">
                <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                  Record payment to:
                </Label>
                {payerOwes.length === 0 ? (
                  <p className="py-4 text-center text-sm text-muted-foreground border rounded-lg border-dashed">
                    {getUserById(payerId)?.name} doesn't owe anyone in this group! 🎉
                  </p>
                ) : (
                  <div className="space-y-2">
                    {payerOwes.map((b) => {
                      const u = getUserById(b.to);
                      const active = payeeId === b.to;
                      return (
                        <button
                          key={b.to}
                          onClick={() => {
                            setPayeeId(b.to);
                            setAmount(b.amount.toFixed(0));
                          }}
                          className={`flex w-full items-center justify-between rounded-lg border px-4 py-3 text-left transition-colors ${
                            active
                              ? "border-primary bg-primary/5 shadow-sm"
                              : "border-border bg-card hover:bg-accent"
                          }`}
                        >
                          <div className="flex items-center gap-3">
                            <span className="text-xl">{u?.avatar}</span>
                            <div>
                              <p className="text-sm font-semibold">{u?.name}</p>
                              <p className="text-xs text-muted-foreground">
                                Owes RS{b.amount.toFixed(0)}
                              </p>
                            </div>
                          </div>
                          {active && <Check className="h-5 w-5 text-primary" />}
                        </button>
                      );
                    })}
                  </div>
                )}
              </div>

              {/* Amount */}
              <AnimatePresence>
                {payeeId && (
                  <motion.div
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: 8 }}
                    className="space-y-1.5"
                  >
                    <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                      Amount (RS)
                    </Label>
                    <Input
                      type="number"
                      value={amount}
                      onChange={(e) => setAmount(e.target.value)}
                      className="font-display text-lg"
                      autoFocus
                    />
                    <Button
                      onClick={handleSettle}
                      disabled={!parseFloat(amount)}
                      className="w-full gap-2 mt-3"
                      size="lg"
                    >
                      <Check className="h-4 w-4" /> Record Settlement
                    </Button>
                  </motion.div>
                )}
              </AnimatePresence>
            </motion.div>
          )}
        </AnimatePresence>
      </div>

      {/* History Section */}
      <div className="pt-4 border-t">
        <div className="flex items-center gap-2 mb-4">
          <History className="h-4 w-4 text-muted-foreground" />
          <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Recent Settlements
          </h3>
        </div>
        {settlements.filter((s) => s.groupId === groupId).length === 0 ? (
          <p className="py-8 text-center text-sm text-muted-foreground">
            No settlements recorded for this group yet.
          </p>
        ) : (
          <div className="space-y-3">
            {settlements
              .filter((s) => s.groupId === groupId)
              .slice(0, 5)
              .map((s, i) => (
                <motion.div
                  key={s.id}
                  initial={{ opacity: 0, x: -10 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: i * 0.05 }}
                  className="flex items-center justify-between rounded-xl bg-surface/50 border px-4 py-3"
                >
                  <div className="flex items-center gap-2 text-sm">
                    <span className="font-bold">{getUserById(s.from)?.name}</span>
                    <ArrowRight className="h-3 w-3 text-muted-foreground" />
                    <span className="font-bold">{getUserById(s.to)?.name}</span>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-display font-bold text-success">
                      RS{s.amount.toFixed(0)}
                    </p>
                    <p className="text-[10px] text-muted-foreground">{s.date}</p>
                  </div>
                </motion.div>
              ))}
          </div>
        )}
      </div>
    </div>
  );
}

