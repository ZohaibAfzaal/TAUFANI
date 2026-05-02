# Technical Guide: direct-debt-main

This document provides an overview of the project's technology stack, setup implementation, and essential commands for development.

## 📊 Technology Stack

| Category | Technology | Version | Description |
| :--- | :--- | :--- | :--- |
| **Backend** | Laravel | 13.5.0 | Core PHP framework |
| **Language** | PHP | 8.5.5 | Modern high-performance runtime |
| **Frontend** | Livewire + Volt | 4.2.0 | Reactive UI components in PHP |
| **Styling** | Tailwind CSS | 4.0.0 | Utility-first CSS framework |
| **Build Tool** | Vite | 8.0.8 | Fast asset bundling & HMR |
| **Database** | MySQL | 8.x | Primary data storage (taufani_split) |
| **Auth** | Laravel Breeze | 2.4.0 | Simplified authentication scaffolding |

## 🏗️ Implementation Overview

The project was configured using a **synchronized local environment** strategy:

1.  **Portable PHP Integration**: Configured the project to utilize the local PHP binary located in the `/php` directory, bypassing the need for a global system installation.
2.  **MySQL Migration**: Switched the primary database engine from SQLite to MySQL. This involved updating the `.env` configuration and executing schema migrations on the `taufani_split` database.
3.  **Frontend Compilation**: Synchronized dependencies via NPM and compiled the modern TALL stack assets (Tailwind, Alpine, Livewire, Laravel) using Vite.
4.  **Custom Tooling**: Added a custom Artisan command `make:user` to facilitate quick user creation via CLI.
5.  **UI Enhancements**: Updated the authentication templates to include a "Register" option on the login screen for better user flow.

## 💻 Essential Commands

Run these commands from the `taufani-laravel` directory:

### Development Servers
*   **Start Backend**: `..\php\php.exe artisan serve`
*   **Start Frontend**: `npm run dev`

### Management
*   **Add User**: `..\php\php.exe artisan make:user`
*   **Migrate DB**: `..\php\php.exe artisan migrate`
*   **Refresh Assets**: `npm run build`
*   **Optimize App**: `..\php\php.exe artisan optimize:clear`

---
*Created by Antigravity AI - 2026-05-02*
