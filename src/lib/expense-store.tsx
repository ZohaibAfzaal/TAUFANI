import React, {
  createContext,
  useContext,
  useState,
  useCallback,
  useEffect,
  type ReactNode,
} from "react";

// ─── Types ────────────────────────────────────────────────────────────────────

export interface User {
  id: string;
  name: string;
  avatar: string;
}

export interface Expense {
  id: string;
  description: string;
  amount: number;
  paidBy: string;
  participants: string[];
  splitType: "equal" | "custom";
  customSplits?: Record<string, number>;
  groupId: string;
  date: string;
}

export interface Settlement {
  id: string;
  from: string;
  to: string;
  amount: number;
  date: string;
  groupId: string;
  note?: string;
}

export interface Group {
  id: string;
  name: string;
  members: string[];
  emoji: string;
  createdBy: string;
}

export interface PairBalance {
  from: string;
  to: string;
  amount: number;
}

// ─── Default seed data ────────────────────────────────────────────────────────

const DEFAULT_USERS: User[] = [
  { id: "you", name: "Zohaib", avatar: "🤵" },
  { id: "b", name: "Abdullah", avatar: "🧑" },
  { id: "c", name: "Sanaullah", avatar: "🧔" },
  { id: "d", name: "Wahab", avatar: "👨" },
  { id: "e", name: "Qadir", avatar: "👨‍🦳" },
];

const DEFAULT_GROUPS: Group[] = [
  { id: "trip", name: "Goa Trip", members: ["you", "b", "c", "d", "e"], emoji: "✈️", createdBy: "you" },
  { id: "flat", name: "Flat Expenses", members: ["you", "b", "c"], emoji: "🏠", createdBy: "b" },
];

const DEFAULT_EXPENSES: Expense[] = [
  {
    id: "e1",
    description: "Hotel booking",
    amount: 8000,
    paidBy: "b",
    participants: ["you", "b", "c", "d", "e"],
    splitType: "equal",
    groupId: "trip",
    date: "2026-04-10",
  },
  {
    id: "e2",
    description: "Dinner",
    amount: 3000,
    paidBy: "you",
    participants: ["you", "b", "c"],
    splitType: "equal",
    groupId: "trip",
    date: "2026-04-11",
  },
  {
    id: "e3",
    description: "Electricity bill",
    amount: 2400,
    paidBy: "c",
    participants: ["you", "b", "c"],
    splitType: "equal",
    groupId: "flat",
    date: "2026-04-05",
  },
];

// ─── LocalStorage helpers ─────────────────────────────────────────────────────

const LS_KEYS = {
  users: "taufani_users",
  groups: "taufani_groups",
  expenses: "taufani_expenses",
  settlements: "taufani_settlements",
};

function loadFromLS<T>(key: string, fallback: T): T {
  try {
    const raw = localStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : fallback;
  } catch {
    return fallback;
  }
}

function saveToLS<T>(key: string, value: T) {
  localStorage.setItem(key, JSON.stringify(value));
}

// ─── Context ──────────────────────────────────────────────────────────────────

interface StoreContextType {
  users: User[];
  groups: Group[];
  expenses: Expense[];
  settlements: Settlement[];

  // User management
  addUser: (name: string, avatar?: string) => User;
  removeUser: (id: string) => void;

  // Group management
  addGroup: (name: string, members: string[], emoji?: string) => Group;
  removeGroup: (id: string) => void;
  updateGroupMembers: (groupId: string, members: string[]) => void;

  // Expense management
  addExpense: (e: Omit<Expense, "id" | "date">) => Expense;
  deleteExpense: (id: string) => void;

  // Settlement management
  addSettlement: (s: Omit<Settlement, "id" | "date">) => void;

  // Computed
  getPairBalances: (groupId?: string) => PairBalance[];
  getUserBalance: (userId: string, groupId?: string) => { owes: PairBalance[]; owed: PairBalance[] };
  getUserById: (id: string) => User | undefined;
  getGroupById: (id: string) => Group | undefined;

  // Utility
  resetAllData: () => void;
}

const StoreContext = createContext<StoreContextType | null>(null);

// ─── Provider ─────────────────────────────────────────────────────────────────

export function StoreProvider({ children }: { children: ReactNode }) {
  const [users, setUsers] = useState<User[]>(() =>
    loadFromLS(LS_KEYS.users, DEFAULT_USERS),
  );
  const [groups, setGroups] = useState<Group[]>(() =>
    loadFromLS(LS_KEYS.groups, DEFAULT_GROUPS),
  );
  const [expenses, setExpenses] = useState<Expense[]>(() =>
    loadFromLS(LS_KEYS.expenses, DEFAULT_EXPENSES),
  );
  const [settlements, setSettlements] = useState<Settlement[]>(() =>
    loadFromLS(LS_KEYS.settlements, [] as Settlement[]),
  );

  // Persist on change
  useEffect(() => saveToLS(LS_KEYS.users, users), [users]);
  useEffect(() => saveToLS(LS_KEYS.groups, groups), [groups]);
  useEffect(() => saveToLS(LS_KEYS.expenses, expenses), [expenses]);
  useEffect(() => saveToLS(LS_KEYS.settlements, settlements), [settlements]);

  // ── User management ──────────────────────────────────────────────────────

  const addUser = useCallback((name: string, avatar = "🧑") => {
    const user: User = { id: `u${Date.now()}`, name, avatar };
    setUsers((prev) => [...prev, user]);
    return user;
  }, []);

  const removeUser = useCallback((id: string) => {
    if (id === "you") return; // Can't remove "You"
    setUsers((prev) => prev.filter((u) => u.id !== id));
    setGroups((prev) =>
      prev.map((g) => ({ ...g, members: g.members.filter((m) => m !== id) })),
    );
  }, []);

  // ── Group management ─────────────────────────────────────────────────────

  const addGroup = useCallback((name: string, members: string[], emoji = "👥") => {
    const group: Group = { id: `g${Date.now()}`, name, members, emoji, createdBy: "you" };
    setGroups((prev) => [...prev, group]);
    return group;
  }, []);

  const removeGroup = useCallback((id: string) => {
    setGroups((prev) => prev.filter((g) => g.id !== id));
    setExpenses((prev) => prev.filter((e) => e.groupId !== id));
    setSettlements((prev) => prev.filter((s) => s.groupId !== id));
  }, []);

  const updateGroupMembers = useCallback((groupId: string, members: string[]) => {
    setGroups((prev) =>
      prev.map((g) => (g.id === groupId ? { ...g, members } : g)),
    );
  }, []);

  // ── Expense management ────────────────────────────────────────────────────

  const addExpense = useCallback((e: Omit<Expense, "id" | "date">) => {
    const expense: Expense = {
      ...e,
      id: `e${Date.now()}`,
      date: new Date().toISOString().slice(0, 10),
    };
    setExpenses((prev) => [expense, ...prev]);
    return expense;
  }, []);

  const deleteExpense = useCallback((id: string) => {
    setExpenses((prev) => prev.filter((e) => e.id !== id));
  }, []);

  // ── Settlement management ─────────────────────────────────────────────────

  const addSettlement = useCallback((s: Omit<Settlement, "id" | "date">) => {
    const settlement: Settlement = {
      ...s,
      id: `s${Date.now()}`,
      date: new Date().toISOString().slice(0, 10),
    };
    setSettlements((prev) => [settlement, ...prev]);
  }, []);

  // ── Balance computations ──────────────────────────────────────────────────

  const getPairBalances = useCallback(
    (groupId?: string) => {
      const balanceMap = new Map<string, number>();
      const key = (a: string, b: string) => (a < b ? `${a}|${b}` : `${b}|${a}`);
      const sign = (from: string, to: string) => (from < to ? 1 : -1);

      const filteredExp = groupId
        ? expenses.filter((e) => e.groupId === groupId)
        : expenses;

      for (const exp of filteredExp) {
        const n = exp.participants.length;
        for (const p of exp.participants) {
          if (p === exp.paidBy) continue;
          let share: number;
          if (exp.splitType === "custom" && exp.customSplits) {
            share = exp.customSplits[p] ?? 0;
          } else {
            share = exp.amount / n;
          }
          const k = key(p, exp.paidBy);
          const s = sign(p, exp.paidBy);
          balanceMap.set(k, (balanceMap.get(k) ?? 0) + share * s);
        }
      }

      const filteredStl = groupId
        ? settlements.filter((s) => s.groupId === groupId)
        : settlements;

      for (const stl of filteredStl) {
        const k = key(stl.from, stl.to);
        const s = sign(stl.from, stl.to);
        balanceMap.set(k, (balanceMap.get(k) ?? 0) - stl.amount * s);
      }

      const result: PairBalance[] = [];
      for (const [k, val] of balanceMap) {
        if (Math.abs(val) < 0.01) continue;
        const [a, b] = k.split("|");
        if (val > 0) {
          result.push({ from: a, to: b, amount: Math.round(val * 100) / 100 });
        } else {
          result.push({ from: b, to: a, amount: Math.round(-val * 100) / 100 });
        }
      }
      return result;
    },
    [expenses, settlements],
  );

  const getUserBalance = useCallback(
    (userId: string, groupId?: string) => {
      const all = getPairBalances(groupId);
      return {
        owes: all.filter((b) => b.from === userId),
        owed: all.filter((b) => b.to === userId),
      };
    },
    [getPairBalances],
  );

  const getUserById = useCallback(
    (id: string) => users.find((u) => u.id === id),
    [users],
  );
  const getGroupById = useCallback(
    (id: string) => groups.find((g) => g.id === id),
    [groups],
  );

  // ── Utility ───────────────────────────────────────────────────────────────

  const resetAllData = useCallback(() => {
    setUsers(DEFAULT_USERS);
    setGroups(DEFAULT_GROUPS);
    setExpenses(DEFAULT_EXPENSES);
    setSettlements([]);
  }, []);

  return (
    <StoreContext.Provider
      value={{
        users,
        groups,
        expenses,
        settlements,
        addUser,
        removeUser,
        addGroup,
        removeGroup,
        updateGroupMembers,
        addExpense,
        deleteExpense,
        addSettlement,
        getPairBalances,
        getUserBalance,
        getUserById,
        getGroupById,
        resetAllData,
      }}
    >
      {children}
    </StoreContext.Provider>
  );
}

export function useStore() {
  const ctx = useContext(StoreContext);
  if (!ctx) throw new Error("useStore must be used within StoreProvider");
  return ctx;
}
