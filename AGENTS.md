# AGENTS.md

## Agent Context & System Directives

This document provides context and operational guidelines for autonomous coding assistants (such as Antigravity) working on the `alumni` repository.

### 1. Architectural Philosophy
- **Framework**: Python 3.11+ using FastAPI.
- **Database**: PostgreSQL 16 managed via Docker Compose.
- **Container Target**: The system must always run cleanly with `docker compose up`.
- **API Style**: RESTful, type-annotated with Pydantic schemas, and documented via OpenAPI standards.

### 2. Standards & Conventions
- All routes must declare response models (`response_model=...`).
- Database credentials must be read from environment variables (e.g., `DATABASE_URL`).
- Code must adhere to PEP 8 standards with descriptive docstrings.
- New endpoints must be placed under `app/api/` or tagged by domain logic (e.g., `Alumni`, `Auth`, `Events`).

### 3. Planned Milestones
- **Week 01**: Repository initialization, tech stack justification, container setup, and `README.md`.
- **Week 02**: Routing architecture, `AGENTS.md` specifications, and initial CRUD endpoints for alumni records.
