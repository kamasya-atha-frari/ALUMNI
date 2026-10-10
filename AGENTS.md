# AGENTS.md

## Agent Context & System Directives

This document establishes development principles and architecture conventions for autonomous AI assistants (e.g., Antigravity) maintaining the `alumni` repository.

### 1. Architectural Stack
- **Backend Language**: PHP 8.2 (running on Apache with `mod_rewrite`).
- **Database**: MySQL 8.0 (orchestrated via Docker Compose with PDO driver).
- **Architecture Pattern**: Lightweight RESTful backend with Front Controller dispatch.
- **Data Persistence**: Normalized relational tables (`users`, `departments`, `alumni_profiles`).

### 2. Operational Directives
- **Single Command Rule**: The system must launch completely using `docker compose up`.
- **Database Connectivity**: Always use PHP Data Objects (PDO) with prepared statements (`execute([...])`) to prevent SQL injection.
- **Graceful Fallbacks**: If the MySQL container is initializing or disconnected, API endpoints should provide safe standby responses rather than fatal HTTP 500 crashes.
- **Code Standards**: Follow PSR-12 coding style guidelines and document query contracts.

### 3. Roadmap & Milestones
- **Week 01**: Repository initialization, tech stack rationale, container setup, and `README.md`.
- **Week 02**: Advanced routing (`/routes`), `AGENTS.md` refinement, and full CRUD endpoints for profile tracking.
