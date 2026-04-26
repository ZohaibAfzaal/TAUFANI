import { useState, useMemo, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { useStore, type Expense } from "@/lib/expense-store";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ArrowRight, Check, Eye } from "lucide-react";

export function AddExpenseForm() {
  const { users, groups, addExpense, getUserById } = useStore();
  const myGroups = groups.filter((g) => g.members.includes("you"));
  const [groupId, setGroupId] = useState(myGroups[0]?.id || "");
  const [description, setDescription] = useState("");
  const [amount, setAmount] = useState("");
  const [paidBy, setPaidBy] = useState("you");
  const [participants, setParticipants] = useState<string[]>(
    myGroups[0]?.members || [],
  );
  const [splitType, setSplitType] = useState<"equal" | "custom">("equal");
  const [customSplits, setCustomSplits] = useState<Record<string, string>>({});
  const [step, setStep] = useState<"input" | "preview" | "done">("input");

  const group = myGroups.find((g) => g.id === groupId);

  // Sync selected group when groups list changes (e.g. after deletion/leaving)
  useEffect(() => {
    if (!myGroups.find((g) => g.id === groupId)) {
      const first = myGroups[0];
      setGroupId(first?.id || "");
      setParticipants(first?.members || []);
    }
  }, [myGroups, groupId]);

  const amountNum = parseFloat(amount) || 0;

  const preview = useMemo(() => {
    if (!amountNum || participants.length === 0) return [];
    const debts: { from: string; to: string; amount: number }[] = [];
    for (const p of participants) {
      if (p === paidBy) continue;
      let share: number;
      if (splitType === "custom") {
        share = parseFloat(customSplits[p] || "0");
      } else {
        share = amountNum / participants.length;
      }
      if (share > 0) {
        debts.push({ from: p, to: paidBy, amount: Math.round(share * 100) / 100 });
      }
    }
    return debts;
  }, [amountNum, participants, paidBy, splitType, customSplits]);

  const handleSubmit = () => {
    if (!group) return;
    const splits: Record<string, number> | undefined =
      splitType === "custom"
        ? Object.fromEntries(
            Object.entries(customSplits).map(([k, v]) => [k, parseFloat(v) || 0]),
          )
        : undefined;
    addExpense({
      description,
      amount: amountNum,
      paidBy,
      participants,
      splitType,
      customSplits: splits,
      groupId,
    });
    setStep("done");
  };

  const reset = () => {
    setDescription("");
    setAmount("");
    setPaidBy("you");
    setParticipants(group?.members || []);
    setCustomSplits({});
    setStep("input");
  };

  const toggleParticipant = (id: string) => {
    setParticipants((prev) =>
      prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id],
    );
  };

  if (myGroups.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-center">
        <p className="text-muted-foreground">You need to create a group first.</p>
      </div>
    );
  }

  if (step === "done") {
    return (
      <motion.div
        initial={{ scale: 0.9, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        className="flex flex-col items-center gap-4 py-16"
      >
        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-success/10">
          <Check className="h-8 w-8 text-success" />
        </div>
        <h2 className="font-display text-xl font-bold">Expense Added!</h2>
        <p className="text-sm text-muted-foreground">Balances have been updated.</p>
        <Button onClick={reset} className="mt-2">
          Add Another
        </Button>
      </motion.div>
    );
  }

  return (
    <div className="space-y-5">
      <AnimatePresence mode="wait">
        {step === "input" && (
          <motion.div
            key="input"
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -20 }}
            className="space-y-4"
          >
            {/* Group */}
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Group
              </Label>
              <div className="flex flex-wrap gap-2">
                {myGroups.map((g) => (
                  <button
                    key={g.id}
                    onClick={() => {
                      setGroupId(g.id);
                      setParticipants(g.members);
                    }}
                    className={`flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors ${
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


            {/* Description */}
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Description
              </Label>
              <Input
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="e.g. Dinner at Beach Shack"
              />
            </div>

            {/* Amount */}
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Amount (RS)
              </Label>
              <Input
                type="number"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                placeholder="0"
                className="font-display text-lg"
              />
            </div>

            {/* Paid by */}
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Paid by
              </Label>
              <div className="flex flex-wrap gap-2">
                {group.members.map((id) => {
                  const u = getUserById(id);
                  return (
                    <button
                      key={id}
                      onClick={() => setPaidBy(id)}
                      className={`flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors ${
                        paidBy === id
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

            {/* Participants */}
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Split between
              </Label>
              <div className="flex flex-wrap gap-2">
                {group.members.map((id) => {
                  const u = getUserById(id);
                  const active = participants.includes(id);
                  return (
                    <button
                      key={id}
                      onClick={() => toggleParticipant(id)}
                      className={`flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors ${
                        active
                          ? "border-primary bg-secondary text-secondary-foreground"
                          : "border-border bg-card text-muted-foreground hover:bg-accent"
                      }`}
                    >
                      <span>{u?.avatar}</span> {u?.name}
                      {active && <Check className="h-3.5 w-3.5" />}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Split type */}
            <div className="space-y-1.5">
              <Label className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Split type
              </Label>
              <div className="flex gap-2">
                {(["equal", "custom"] as const).map((t) => (
                  <button
                    key={t}
                    onClick={() => setSplitType(t)}
                    className={`rounded-lg border px-3 py-1.5 text-sm font-medium capitalize transition-colors ${
                      splitType === t
                        ? "border-primary bg-primary text-primary-foreground"
                        : "border-border bg-card text-card-foreground hover:bg-accent"
                    }`}
                  >
                    {t}
                  </button>
                ))}
              </div>
            </div>

            {/* Custom splits */}
            {splitType === "custom" && (
              <motion.div
                initial={{ height: 0, opacity: 0 }}
                animate={{ height: "auto", opacity: 1 }}
                className="space-y-2 overflow-hidden"
              >
                {participants
                  .filter((p) => p !== paidBy)
                  .map((id) => {
                    const u = getUserById(id);
                    return (
                      <div key={id} className="flex items-center gap-3">
                        <span className="w-24 text-sm font-medium">
                          {u?.avatar} {u?.name}
                        </span>
                        <Input
                          type="number"
                          placeholder="0"
                          value={customSplits[id] || ""}
                          onChange={(e) =>
                            setCustomSplits((prev) => ({ ...prev, [id]: e.target.value }))
                          }
                          className="max-w-28"
                        />
                      </div>
                    );
                  })}
              </motion.div>
            )}

            <Button
              onClick={() => setStep("preview")}
              disabled={!description || !amountNum || participants.length < 2}
              className="w-full gap-2"
              size="lg"
            >
              <Eye className="h-4 w-4" /> Preview Split
            </Button>
          </motion.div>
        )}

        {step === "preview" && (
          <motion.div
            key="preview"
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: 20 }}
            className="space-y-4"
          >
            <div className="rounded-xl border bg-card p-4">
              <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Visual Check
              </p>
              <h3 className="mt-1 font-display text-lg font-bold">{description}</h3>
              <p className="mt-0.5 text-sm text-muted-foreground">
                RS{amountNum.toFixed(0)} paid by {getUserById(paidBy)?.name}
              </p>
            </div>

            <div className="space-y-2">
              <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Who owes whom
              </p>
              {preview.map((d, i) => (
                <motion.div
                  key={`${d.from}-${d.to}`}
                  initial={{ opacity: 0, y: 8 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: i * 0.06 }}
                  className="flex items-center gap-3 rounded-lg border bg-surface px-4 py-3"
                >
                  <span className="text-lg">{getUserById(d.from)?.avatar}</span>
                  <span className="flex-1 text-sm font-medium text-surface-foreground">
                    <strong>{getUserById(d.from)?.name}</strong> owes{" "}
                    <strong>{getUserById(d.to)?.name}</strong>
                  </span>
                  <span className="font-display text-sm font-bold text-destructive">
                    RS{d.amount.toFixed(0)}
                  </span>
                  <ArrowRight className="h-4 w-4 text-muted-foreground" />
                  <span className="text-lg">{getUserById(d.to)?.avatar}</span>
                </motion.div>
              ))}
            </div>

            <div className="flex gap-3">
              <Button variant="outline" onClick={() => setStep("input")} className="flex-1">
                Back
              </Button>
              <Button onClick={handleSubmit} className="flex-1 gap-2" size="lg">
                <Check className="h-4 w-4" /> Confirm
              </Button>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
