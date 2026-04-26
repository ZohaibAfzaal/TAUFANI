import { useState, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { useStore } from "@/lib/expense-store";
import { Receipt, ArrowLeftRight, Trash2, Plus, X, Users } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export function GroupView() {
  const {
    users,
    groups,
    expenses,
    getPairBalances,
    getUserById,
    deleteExpense,
    addGroup,
    removeGroup,
    updateGroupMembers,
    addUser,
  } = useStore();
  const [selectedGroup, setSelectedGroup] = useState(groups[0]?.id || "");
  const [showAddGroup, setShowAddGroup] = useState(false);
  const [newGroupName, setNewGroupName] = useState("");
  const [newGroupEmoji, setNewGroupEmoji] = useState("👥");

  const [showAddMember, setShowAddMember] = useState(false);
  const [newMemberName, setNewMemberName] = useState("");
  const [newMemberAvatar, setNewMemberAvatar] = useState("👤");
  const [confirmAction, setConfirmAction] = useState<"leave" | "delete" | null>(null);

  const group = groups.find((g) => g.id === selectedGroup);
  const groupExpenses = expenses.filter((e) => e.groupId === selectedGroup);
  const balances = getPairBalances(selectedGroup);

  // Sync selectedGroup with available groups
  useEffect(() => {
    const memberGroups = groups.filter((g) => g.members.includes("you"));
    if (memberGroups.length > 0) {
      if (!selectedGroup || !memberGroups.find((g) => g.id === selectedGroup)) {
        setSelectedGroup(memberGroups[0].id);
      }
    } else if (selectedGroup !== "") {
      setSelectedGroup("");
    }
  }, [groups, selectedGroup]);

  const handleAddGroup = () => {
    if (!newGroupName.trim()) return;
    const g = addGroup(newGroupName, ["you"], newGroupEmoji);
    setSelectedGroup(g.id);
    setNewGroupName("");
    setShowAddGroup(false);
  };

  return (
    <div className="space-y-6">
      {/* Group tabs and Add button */}
      <div className="flex flex-wrap gap-2">
        {groups
          .filter((g) => g.members.includes("you"))
          .map((g) => (
            <button
              key={g.id}
              onClick={() => setSelectedGroup(g.id)}
              className={`flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors ${
                selectedGroup === g.id
                  ? "border-primary bg-primary text-primary-foreground"
                  : "border-border bg-card text-card-foreground hover:bg-accent"
              }`}
            >
              <span>{g.emoji}</span>
              {g.name}
            </button>
          ))}
        <button
          onClick={() => setShowAddGroup(true)}
          className="flex items-center gap-1.5 rounded-lg border border-dashed border-muted-foreground/50 px-3 py-1.5 text-sm font-medium text-muted-foreground hover:border-primary hover:text-primary transition-colors"
        >
          <Plus className="h-3.5 w-3.5" />
          New Group
        </button>
      </div>

      <AnimatePresence>
        {showAddGroup && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: "auto", opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            className="overflow-hidden"
          >
            <div className="space-y-3 rounded-xl border bg-card p-4">
              <div className="flex items-center justify-between">
                <h3 className="text-sm font-bold">Create New Group</h3>
                <button
                  onClick={() => setShowAddGroup(false)}
                  className="rounded-full p-1 hover:bg-accent"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
              <div className="grid grid-cols-[auto_1fr] gap-2">
                <Input
                  value={newGroupEmoji}
                  onChange={(e) => setNewGroupEmoji(e.target.value)}
                  className="w-12 text-center"
                  placeholder="Emoji"
                />
                <Input
                  value={newGroupName}
                  onChange={(e) => setNewGroupName(e.target.value)}
                  placeholder="Group Name (e.g. Europe Trip)"
                  onKeyDown={(e) => e.key === "Enter" && handleAddGroup()}
                />
              </div>
              <Button onClick={handleAddGroup} className="w-full h-9" size="sm">
                Create Group
              </Button>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {!group ? (
        <p className="py-12 text-center text-sm text-muted-foreground">
          No groups found. Create one to get started!
        </p>
      ) : (
        <div className="space-y-6">
          {/* Group Header Info */}
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Users className="h-4 w-4 text-primary" />
                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                  Group Members
                </h3>
              </div>
              <button
                  onClick={() => setShowAddMember(!showAddMember)}
                  className="text-xs text-primary hover:underline flex items-center gap-1"
                >
                  <Plus className="h-3 w-3" /> Add Member
                </button>
            </div>

            <AnimatePresence>
              {showAddMember && (
                <motion.div
                  initial={{ height: 0, opacity: 0 }}
                  animate={{ height: "auto", opacity: 1 }}
                  exit={{ height: 0, opacity: 0 }}
                  className="overflow-hidden space-y-3"
                >
                  <div className="rounded-xl border bg-secondary/30 p-4 space-y-4">
                    {/* Add existing */}
                    <div className="space-y-2">
                      <Label className="text-[10px] font-bold uppercase text-muted-foreground">
                        Add Existing Person
                      </Label>
                      <div className="flex flex-wrap gap-2">
                        {users
                          .filter((u) => !group.members.includes(u.id))
                          .map((u) => (
                            <button
                              key={u.id}
                              onClick={() =>
                                updateGroupMembers(group.id, [...group.members, u.id])
                              }
                              className="flex items-center gap-1.5 rounded-full border bg-card px-3 py-1 text-xs font-medium hover:bg-accent transition-colors"
                            >
                              <span>{u.avatar}</span> {u.name}
                            </button>
                          ))}
                        {users.filter((u) => !group.members.includes(u.id)).length === 0 && (
                          <p className="text-[10px] text-muted-foreground italic">
                            All people are already in this group.
                          </p>
                        )}
                      </div>
                    </div>

                    <div className="h-px bg-border/50" />

                    {/* Create new */}
                    <div className="space-y-2">
                      <Label className="text-[10px] font-bold uppercase text-muted-foreground">
                        Create New Person
                      </Label>
                      <div className="flex gap-2">
                        <Input
                          value={newMemberAvatar}
                          onChange={(e) => setNewMemberAvatar(e.target.value)}
                          className="w-12 h-8 text-center text-sm px-0"
                          placeholder="Emoji"
                        />
                        <Input
                          value={newMemberName}
                          onChange={(e) => setNewMemberName(e.target.value)}
                          className="h-8 text-sm flex-1"
                          placeholder="Name (e.g. Sahil)"
                          onKeyDown={(e) => {
                            if (e.key === "Enter" && newMemberName.trim()) {
                              const newUser = addUser(newMemberName, newMemberAvatar);
                              updateGroupMembers(group.id, [...group.members, newUser.id]);
                              setNewMemberName("");
                              setNewMemberAvatar("👤");
                            }
                          }}
                        />
                        <Button
                          size="sm"
                          className="h-8 py-0"
                          disabled={!newMemberName.trim()}
                          onClick={() => {
                            const newUser = addUser(newMemberName, newMemberAvatar);
                            updateGroupMembers(group.id, [...group.members, newUser.id]);
                            setNewMemberName("");
                            setNewMemberAvatar("👤");
                          }}
                        >
                          Add
                        </Button>
                      </div>
                    </div>
                  </div>
                </motion.div>
              )}
            </AnimatePresence>

            <div className="flex flex-wrap gap-2">
              {group.members.map((mId) => {
                const u = getUserById(mId);
                return (
                  <div
                    key={mId}
                    className="flex items-center gap-1.5 rounded-full border bg-card px-3 py-1 text-xs font-medium"
                  >
                    <span>{u?.avatar}</span> {u?.name}
                    {mId !== "you" && (
                      <button
                        onClick={() =>
                          updateGroupMembers(
                            group.id,
                            group.members.filter((id) => id !== mId),
                          )
                        }
                        className="ml-1 text-muted-foreground hover:text-destructive"
                      >
                        <X className="h-3 w-3" />
                      </button>
                    )}
                  </div>
                );
              })}
            </div>
          </div>


          {/* Pairwise balances */}
          <div>
            <div className="flex items-center gap-2 mb-3">
              <ArrowLeftRight className="h-4 w-4 text-primary" />
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Pairwise Balances
              </h3>
            </div>
            {balances.length === 0 && (
              <p className="py-4 text-center text-sm text-muted-foreground">All settled! 🎉</p>
            )}
            <div className="space-y-2">
              {balances.map((b, i) => (
                <motion.div
                  key={`${b.from}-${b.to}`}
                  initial={{ opacity: 0, y: 8 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: i * 0.04 }}
                  className="flex items-center justify-between rounded-lg border bg-card px-4 py-3"
                >
                  <div className="flex items-center gap-2 text-sm font-medium">
                    <span>{getUserById(b.from)?.avatar}</span>
                    <span>{getUserById(b.from)?.name}</span>
                    <span className="text-muted-foreground">→</span>
                    <span>{getUserById(b.to)?.avatar}</span>
                    <span>{getUserById(b.to)?.name}</span>
                  </div>
                  <span className="font-display text-sm font-bold text-destructive">
                    RS{b.amount.toFixed(0)}
                  </span>
                </motion.div>
              ))}
            </div>
          </div>

          {/* Expenses list */}
          <div>
            <div className="flex items-center gap-2 mb-3">
              <Receipt className="h-4 w-4 text-primary" />
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                All Expenses
              </h3>
            </div>
            <div className="space-y-2">
              {groupExpenses.length === 0 && (
                <p className="py-8 text-center text-sm text-muted-foreground">No expenses yet.</p>
              )}
              {groupExpenses.map((exp, i) => (
                <motion.div
                  key={exp.id}
                  initial={{ opacity: 0, y: 8 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: i * 0.04 }}
                  className="group relative rounded-lg border bg-card px-4 py-3"
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-semibold text-card-foreground">{exp.description}</p>
                      <p className="text-xs text-muted-foreground">
                        Paid by {getUserById(exp.paidBy)?.name} · {exp.date}
                      </p>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className="font-display text-sm font-bold text-foreground">
                        RS{exp.amount.toFixed(0)}
                      </span>
                      <button
                        onClick={() => deleteExpense(exp.id)}
                        className="opacity-0 group-hover:opacity-100 p-1 text-muted-foreground hover:text-destructive transition-all"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {exp.participants.map((p) => (
                      <span
                        key={p}
                        className="rounded-md bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
                      >
                        {getUserById(p)?.avatar} {getUserById(p)?.name}
                      </span>
                    ))}
                  </div>
                </motion.div>
              ))}
            </div>
          </div>

          {/* Danger Zone */}
          <div className="rounded-xl border border-destructive/30 bg-destructive/5 p-4 space-y-3">
            <p className="text-xs font-semibold uppercase tracking-wider text-destructive/70">Danger Zone</p>

            <AnimatePresence mode="wait">
              {confirmAction === null ? (
                <motion.div
                  key="actions"
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  exit={{ opacity: 0 }}
                  className="flex gap-3"
                >
                  {group.members.includes("you") && (
                    <button
                      onClick={() => setConfirmAction("leave")}
                      className="flex items-center gap-1.5 rounded-lg border border-destructive/40 px-3 py-1.5 text-xs font-medium text-destructive hover:bg-destructive hover:text-destructive-foreground transition-colors"
                    >
                      Leave Group
                    </button>
                  )}
                  <button
                    onClick={() => setConfirmAction("delete")}
                    className="flex items-center gap-1.5 rounded-lg bg-destructive px-3 py-1.5 text-xs font-medium text-destructive-foreground hover:bg-destructive/80 transition-colors"
                  >
                    <Trash2 className="h-3 w-3" /> Delete Group
                  </button>
                </motion.div>
              ) : (
                <motion.div
                  key="confirm"
                  initial={{ opacity: 0, y: 4 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: 4 }}
                  className="space-y-2"
                >
                  <p className="text-xs text-destructive font-medium">
                    {confirmAction === "delete"
                      ? `Delete "${group.name}" and ALL its expenses? This cannot be undone.`
                      : `Leave "${group.name}"? You will no longer see its expenses.`}
                  </p>
                  <div className="flex gap-2">
                    <button
                      onClick={() => {
                        if (confirmAction === "delete") {
                          const id = selectedGroup;
                          setSelectedGroup("");
                          setConfirmAction(null);
                          removeGroup(id);
                        } else {
                          const id = selectedGroup;
                          const newMembers = group.members.filter((m) => m !== "you");
                          setSelectedGroup("");
                          setConfirmAction(null);
                          updateGroupMembers(id, newMembers);
                        }
                      }}
                      className="rounded-lg bg-destructive px-3 py-1.5 text-xs font-medium text-destructive-foreground hover:bg-destructive/80 transition-colors"
                    >
                      Yes, {confirmAction === "delete" ? "delete" : "leave"}
                    </button>
                    <button
                      onClick={() => setConfirmAction(null)}
                      className="rounded-lg border px-3 py-1.5 text-xs font-medium hover:bg-accent transition-colors"
                    >
                      Cancel
                    </button>
                  </div>
                </motion.div>
              )}
            </AnimatePresence>
          </div>
        </div>
      )}
    </div>
  );
}

