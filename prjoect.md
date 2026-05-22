# GPM / Designer Task System
## Local Development Setup

---

# Project Overview

This project replaces the current Excel-based GPM/Designer workflow tracker with a Laravel-based web application running on Azure with Microsoft 365 SSO.

The system will support:

- Microsoft Entra ID (Azure AD) authentication
- Role-based task visibility
- Task assignment to GPMs and Designers
- Multiple Designers per task
- Excel import/export during transition phase
- PostgreSQL database
- Event-driven notifications (later phases)
- Dashboard views for Designers and GPMs
- SKU-based task synchronization

---

# Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11 |
| Frontend | Blade + Tailwind (initial MVP) |
| Database | PostgreSQL |
| Auth | Microsoft Entra ID / Azure AD |
| Hosting | Azure App Service |
| File Storage | Local initially / Azure Blob later |
| Queue | Redis |
| Excel Import | Laravel Excel |
| Permissions | Spatie Laravel Permission |

---

# Local Development Requirements

Install:

- PHP 8.3+
- Composer
- Node.js 20+
- PostgreSQL 15+
- Redis
- Git

Optional:

- Laravel Herd
- Docker Desktop

---

# Create Project

```bash
composer create-project laravel/laravel gpm-task-system
cd gpm-task-system