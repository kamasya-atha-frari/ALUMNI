# AGENTS.md

## Development Protocols for Autonomous Assistants (AlumniNexus)

This specification provides architecture guidelines for autonomous AI engineering agents working on the `alumni` repository.

### 1. Architectural Principles
- **Modern PHP 8.2 Standard**: Every PHP file strictly begins with `declare(strict_types=1);`.
- **Domain-Driven Directory Layout**:
  - `src/Domain/Enums/`: Type-safe backed enums (`UserRole`, `MentorshipStatus`).
  - `src/Services/`: Pure business logic and domain computations.
  - `src/Controllers/`: HTTP controllers outputting standard JSON or rendering templates.
  - `src/Core/`: Router, Response helper, and Database singleton.
  - `public/`: The only directory exposed to the web server (containing `index.php` and `.htaccess`).

### 2. Operational Directives
- **Container Target**: The system must always launch cleanly using a single command:
  ```bash
  docker compose up
  ```
- **Prepared Statements**: All SQL queries must utilize parameterized PDO statements to eliminate SQL injection.
- **Standby Fallbacks**: Endpoints must never trigger unhandled exceptions; in-memory fallback datasets ensure 100% uptime even before database containers finish warming up.

### 3. Planned Milestones
- **Week 01**: Repository setup, modern architecture design, Docker containerization, and comprehensive README.
- **Week 02**: Advanced career pathway routing, agent task directives, and live mentorship request workflows.
