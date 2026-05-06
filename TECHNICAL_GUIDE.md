# 📘 Taufani — Technical Guide

> A quick reference for developers working on the **Taufani** debt-splitting application.

---

## 📦 Technology Stack

| Category     | Technology          | Version | Purpose                                   |
| :----------- | :------------------ | :------ | :---------------------------------------- |
| **Backend**  | Laravel             | 13.5.0  | Core PHP framework                        |
| **Language** | PHP                 | 8.5.5   | Modern high-performance runtime           |
| **Frontend** | Livewire + Volt     | 4.2.0   | Reactive UI components written in PHP     |
| **Styling**  | Tailwind CSS        | 4.0.0   | Utility-first CSS framework               |
| **Build**    | Vite                | 8.0.8   | Fast asset bundling & Hot Module Reload   |
| **Database** | MySQL               | 8.x     | Primary data store (`taufani_split`)      |
| **Auth**     | Laravel Breeze      | 2.4.0   | Simplified authentication scaffolding     |

---

## 🚀 Quick Start

> **All commands below must be run from the `taufani-laravel` directory.**

### 1. Start the Backend Server
```powershell
..\php\php.exe artisan serve
```
The app will be available at **http://127.0.0.1:8000**

### 2. Start the Frontend Dev Server (in a second terminal)
```powershell
npm run dev
```
Vite will watch for changes and hot-reload the browser automatically.

---

## 🛠️ Management Commands

### Add a New User
```powershell
..\php\php.exe artisan make:user
```
Interactively create a user account via the CLI — no browser needed.

### Run Database Migrations
```powershell
..\php\php.exe artisan migrate
```
Applies any pending schema changes to the `taufani_split` database.

### Rebuild Frontend Assets
```powershell
npm run build
```
Compiles and minifies CSS/JS for production. Run after changing Tailwind classes or JS files.

### Clear Application Cache
```powershell
..\php\php.exe artisan optimize:clear
```
Clears config, route, view, and other cached files. Useful after changing `.env` or config files.

---

## 🏗️ How This Project Was Set Up

1. **Portable PHP** — Uses the local `/php` binary instead of a global system install. No admin rights needed.
2. **MySQL Database** — Switched from SQLite to MySQL. Configured via `.env` and migrated to `taufani_split`.
3. **Frontend Stack** — Dependencies installed via NPM; assets compiled with Vite (TALL stack: Tailwind, Alpine, Livewire, Laravel).
4. **Custom CLI Tool** — `make:user` Artisan command added for quick user creation without touching the UI.
5. **Login UI** — Auth templates updated to include a "Register" link on the login page for better user flow.

---

## ❓ Troubleshooting

| Problem | Solution |
| :--- | :--- |
| `php.exe` not found | Make sure you are running commands from the `taufani-laravel` directory |
| Page not loading | Ensure both `artisan serve` and `npm run dev` are running |
| DB connection error | Check your `.env` file — `DB_DATABASE=taufani_split`, correct host/user/password |
| Styles not updating | Run `npm run build` or restart `npm run dev` |
| Config not applying | Run `optimize:clear` and refresh |

---

*Created by Mastermind Taufani Boys — 2026-05-02*
