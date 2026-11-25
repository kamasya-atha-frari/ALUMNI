# AlumniIstü (`alumni`)

### Intelligent Alumni Career Graph & Engagement Network
*Designed for graduates, students, and faculty of **Istanbul University Faculty of Economics, Department of Management Information Systems (Yönetim Bilişim Sistemleri - YBS)**.*

> 📘 **Core Documentation & Design System**:
> - 🎨 [**Brand Identity (`brand.md`)**](./brand.md) — High-end dark booking aesthetic with **Navy (`#14213D`)**, **Cream (`#F4EDE0`)**, and **Coral (`#FF6B58`)**
> - 📐 [**Design System & Token Specifications (`design.md`)**](./design.md) — Surfaces, spacing, pill shapes (`9999px`), card radius (`10px`), and CSS custom properties
> - 🗄️ [**Data Design Documentation (`ddd.md`)**](./ddd.md) — ER diagrams, detailed data dictionaries, schema architectures, and the Forgetting Test protocol

---

## 1. Executive Summary & Vision

**AlumniIstü** is a modern, containerized backend platform and career mobility engine engineered to bridge the generational divide between university alumni, current undergraduates, and academic leadership. Rather than serving as a static contact book, AlumniIstü models alumni data as an active **Career & Mentorship Graph**, delivering:

1. **Talent Discovery & Professional Mobility**: Granular tracking of graduate career trajectories across technology, banking, e-commerce, and enterprise consulting sectors.
2. **Algorithmic Mentorship Pairings**: Structured matching between senior alumni in leadership roles and current undergraduates seeking guidance.
3. **Institutional Telemetry for Academic Accreditations**: Automated empirical reporting on graduate employment velocity and curriculum alignment for accreditation evaluation boards (e.g., **YÖKAK**, **AACSB**).
4. **Resilient Persistence & Modern DevOps**: Containerized via Docker Compose with strict environment isolation, automated migration runners, realistic factories/seeders, and guaranteed state recovery passing the **Forgetting Test**.

```text
┌──────────────────────────────────────────────────────────────────────────────────┐
│                           AlumniIstü Core Architecture                          │
└────────────────────────────────────────┬─────────────────────────────────────────┘
                                         │
       ┌─────────────────────────────────┼─────────────────────────────────┐
       ▼                                 ▼                                 ▼
┌───────────────┐               ┌─────────────────┐               ┌────────────────┐
│ Talent Graph  │               │ Mentorship Hub  │               │ Accreditation  │
│  & Directory  │               │ & Compatibility │               │   Telemetry    │
└───────┬───────┘               └────────┬────────┘               └────────┬───────┘
        │                                │                                 │
        ├─ Full CRUD REST Interfaces     ├─ Skill-Matching Algorithm       ├─ 6/12 Month KPIs
        ├─ Company & Industry Filters    ├─ Capacity-Governed Pairings     ├─ Regional Mobility
        ├─ JSON Skills Serialization     ├─ State Machine Lifecycle        ├─ Employer Distribution
        └─ Migration & Seeder Pipeline   └─ Direct Referral Channels       └─ Automated Dossier Export
```

---

## 2. Core Functional Pillars & Deliverables

### 2.1 The Forgetting Test Protocol (Requirement ①)
Container filesystems are volatile by default. To guarantee database persistence across container destruction and restarts:
- MySQL data is mounted to a durable **Docker Named Volume**: `nexus_mysql_data:/var/lib/mysql`.
- When running `docker compose down` followed by `docker compose up`, all records, tables, and migrations are strictly preserved.
- Run the automated validation script:
  ```bash
  ./tests/forgetting_test.sh
  ```

### 2.2 Data Design Documentation (`ddd.md` <=> `README.md`) (Requirement ②)
Full schema documentation, conceptual models, and entity dictionaries are maintained in [**`ddd.md`**](./ddd.md).
- Interactive **Mermaid ER diagrams** mapping `departments`, `alumni`, `mentorship_requests`, and `migrations`.
- Comprehensive column data types, foreign key cascades, and indexing strategies.

### 2.3 Docker Compose Architecture (Requirement ③)
Container orchestration in `docker-compose.yml` is parameterized with default fallbacks and `.env` interpolation:
- Multi-container setup: PHP 8.2 Apache frontend/API (`app`) + MySQL 8.0 engine (`db`).
- Service healthchecks ensure `app` only starts when MySQL is accepting connections.

### 2.4 Environment Variables Architecture (Requirement ④)
Strict credential separation:
- `.env`: Local active configuration (strictly **git-ignored**).
- `.env.example` & `.example.env`: Tracked template files for deployment reference.
- `src/Core/Env.php`: Lightweight runtime parser loading `.env` variables for CLI and Docker runtimes.

### 2.5 Database Migrations (Requirement ⑤)
Structured database migrations stored in `database/migrations/`:
- `001_create_departments_table.sql`
- `002_create_alumni_table.sql`
- `003_create_mentorship_requests_table.sql`
- `004_create_job_postings_table.sql` (Companies & Job Postings schema)
- `005_create_applications_table.sql` (Applications schema)
- `006_create_users_table.sql` (Authenticated users, RBAC roles & Google OAuth fields)
- `007_create_captcha_challenges_table.sql` (Short-TTL single-use CAPTCHA challenges)
- `008_create_revoked_tokens_table.sql` (Server-side JWT logout revocation blocklist)
- `009_create_announcements_table.sql` (Admin-managed department announcements)
- `010_add_profile_fields_to_users_table.sql` (Editable profile bio, contact links & skills)
- Run migrations:
  ```bash
  php database/migrate.php [up|status|fresh]
  ```

### 2.6 Data Factory & Seeder (Requirement ⑥)
Automated generation of realistic Turkish graduate, company, job posting, and application profiles:
- **Alumni Factory**: `database/factories/AlumniFactory.php`
- **Job Posting Factory**: `database/factories/JobPostingFactory.php`
- **Application Factory**: `database/factories/ApplicationFactory.php`
- **Unified Seeder**: `database/seeders/DatabaseSeeder.php`
- Seed all tables:
  ```bash
  php database/seed.php --count=20
  ```
- Or seed dedicated job postings & applications pipeline:
  ```bash
  php database/seed_jobs.php --count=10
  ```

### 2.7 Full CRUD Interfaces & Nested Routes (Requirement ⑦)
All endpoints connect to MySQL with parameterized PDO queries (`prepare`/`execute`), with standby fallback:
- **Alumni Directory**:
  - `POST /api/v1/alumni` & `POST /api/users` (supports `{"name": "Ada", "email": "ada@cv.edu.tr"}`)
  - `GET /api/v1/alumni` & `GET /api/v1/alumni/{id}`
  - `PUT /api/v1/alumni/{id}` & `PATCH /api/v1/alumni/{id}`
  - `DELETE /api/v1/alumni/{id}`
- **Job Postings Feature (Direct & Nested)**:
  - `GET /api/v1/job-postings` (filters: `job_type`, `status`, `company_id`, `user_id`, `search`)
  - `POST /api/v1/job-postings` (create job posting)
  - `GET /api/v1/job-postings/{id}` (single job posting details with application count)
  - `PUT/PATCH /api/v1/job-postings/{id}` (modify posting)
  - `DELETE /api/v1/job-postings/{id}` (remove posting)
  - Nested: `GET /api/v1/companies/{id}/job-postings` & `POST /api/v1/companies/{id}/job-postings`
  - Nested: `GET /api/v1/users/{id}/job-postings` & `POST /api/v1/users/{id}/job-postings`
- **Applications Feature (Direct & Nested)**:
  - `GET /api/v1/applications` (filters: `job_posting_id`, `user_id`, `status`)
  - `POST /api/v1/applications` (submit candidate application)
  - `GET /api/v1/applications/{id}` (inspect single application with applicant profile)
  - `PUT/PATCH /api/v1/applications/{id}` (transition review status: `submitted` -> `under_review` -> `interview` -> `accepted` / `rejected`)
  - `DELETE /api/v1/applications/{id}` (delete application)
  - Nested: `GET /api/v1/job-postings/{id}/applications` & `POST /api/v1/job-postings/{id}/applications`
  - Nested: `GET /api/v1/users/{id}/applications`
- **Interactive Web UI**:
  - Welcome Landing Page (Create Account / Login): `http://localhost:8080/`
  - Graduate Directory & Career Observatory: `http://localhost:8080/directory` (and `/dashboard`)
  - Job Board & Application Pipeline: `http://localhost:8080/jobs`
  - Career Graph Telemetry: `http://localhost:8080/analytics`

### 2.8 Middleware Architecture & Error Guardians (Milestone 07)
Enterprise-grade request interceptors and fail-safe middleware pipeline:

1. **Request Logger Middleware (`src/Middleware/LoggerMiddleware.php`)**:
   - Emits real-time HTTP telemetry directly to the terminal stdout and stderr.
   - Exact format: `[time] [method] [route] [res.type] [duration]`
   - Example terminal row: `12:20:30 Post /user 201 18 ms` (or `10:20:30 Post /user 201 18 ms`).
   - Equipped with shutdown listener to guarantee log output even if handlers call `exit;`.

2. **404 Guardian Middleware (`src/Middleware/NotFoundGuardianMiddleware.php`)**:
   - Intercepts unmapped endpoints and non-existent IDs.
   - Enforces strict HTTP status code `404`.
   - Returns structured JSON with explicit, human-readable `reason` describing the missing resource.
   - Serves dark-mode generic 404 template (`templates/404.html`) with guardian diagnostic badges for browser requests.
   - Graceful URL alias redirection for common variants (`/home` -> `/`, `/job` -> `/jobs`, `/application` -> `/applications`).

3. **5xx Server Error Guardian & File Logging (`src/Middleware/ServerErrorGuardianMiddleware.php`)**:
   - Catches unhandled exceptions, runtime errors, and fatal parse errors to prevent unformatted crashes.
   - Automatically initializes the `logs/` directory if missing and logs incident records into `logs/error.logs`.
   - Log entry format: `[timestamp] [500] [ID:err_xxx] [METHOD ROUTE] Error: Class: Message in File:Line \n Trace...`.
   - Returns clean, safe HTTP 500 responses without leaking sensitive environment secrets or credentials.

4. **JobPosting Domain Validation Rules**:
   - `title`: **Required**, maximum **120 characters**.
   - `company` / `company_name`: **Required**.
   - `salary` / `salary_range`: **Optional**.
   - `description`: **Optional**.
   - `city` / `location`: **Optional**.

5. **Postman Collection for Validation & Error Testing**:
   - Import `docs/AlumniIstü_Postman_Collection.json` into Postman to test invalid requests (missing title, oversized title >120 chars, missing company), 404 Guardian intercepted routes, and 500 error resilience.

---

### 2.9 Authentication & Authorization (RBAC, CAPTCHA, Google OAuth, Admin Panel & Member Profile)

AlumniIstü enforces stateless **HS256 JWT Bearer Token Authentication** with **server-side logout revocation**, **Role-Based Access Control (RBAC)**, **self-hosted SVG/Math CAPTCHA**, and **Google OAuth 2.0 ("Continue with Google")**. Full schema diagrams and security invariants are documented in [`ddd.md`](./ddd.md).

#### User Roles & Access Permissions

| Role | Self-Register? | Key Access Permissions |
| :--- | :---: | :--- |
| **`guest`** *(unauthenticated)* | — | View Welcome landing screen (`/`), browse public directory (`/directory`, `/dashboard`), job board (`/jobs`), analytics summary (`/analytics`), faculty page (`/about`), API Docs (`/swagger`), Register (`/register`), and Login (`/login`). Blocked (`401`) from protected routes. |
| **`alumni` / `user`** *(default)* | Yes | Access member profile & activity workspace (`/my-profile`, `/profile`), edit **own** bio/links/skills (`PATCH /api/profile/me`), view own applications & posted jobs (`/api/profile/me/activity`), delete own job postings, and log out. Blocked (`403`) from other users' profiles and `/admin`. |
| **`student`** | Yes | Access `/my-profile`, edit own profile, submit/withdraw own job applications, and request alumni mentorship. |
| **`employer`** | Yes | Access `/my-profile`, create companies (`POST /api/v1/companies`), manage job postings, and transition candidate application statuses. |
| **`faculty` / `moderator`** | Admin-assigned | Access institutional accreditation telemetry (`GET /api/v1/analytics/accreditation`) and moderation controls. |
| **`admin`** | Admin-assigned | Full system governance via **Admin Panel (`/admin`)** and `/api/v1/admin/*`: manage users, assign roles, activate/deactivate or delete users (with self-lockout protection), manage alumni profiles, moderate job postings, and publish department announcements. |

#### Registration Flow (Standard + CAPTCHA & Google OAuth 2.0)

1. **Standard Form Registration with Self-Hosted CAPTCHA** (works out-of-the-box in local Docker with zero third-party API keys):
   - Client fetches a short-TTL (`300s`), single-use SVG or Math challenge via `GET /api/auth/captcha` (or `/api/v1/auth/captcha?mode=math`).
   - Client submits account details + `captcha_id` & `captcha_answer` to `POST /api/auth/register`.
   - Server validates fields, verifies CAPTCHA before creating the user (`422` on missing/incorrect/expired/reused CAPTCHA), checks duplicate `username`/`email`/`student_id` (`409 Conflict`), hashes passwords with `password_hash()`, and returns `201 Created` + HS256 Bearer `access_token`.
2. **Google OAuth 2.0 Registration & Sign-In ("Continue with Google")**:
   - Configure OAuth 2.0 Web Application credentials in **Google Cloud Console → APIs & Services → Credentials**:
     - **Authorized JavaScript origins**: `http://localhost:8080`, `http://alumni-istu`
     - **Authorized redirect URIs**: `http://localhost:8080/api/v1/auth/google/callback` (and/or `http://alumni-istu/api/v1/auth/google/callback`)
   - Populate `.env` (see `.env.example`):
     ```env
     GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
     GOOGLE_CLIENT_SECRET=your-client-secret
     GOOGLE_REDIRECT_URI=http://localhost:8080/api/v1/auth/google/callback
     ```
   - Browser flow: `GET /api/auth/google` (signed CSRF `state`) → Google consent → `GET /api/auth/google/callback` → creates a new `auth_provider='google'` account (`password_hash=NULL`) or links to an existing verified email, then issues the same application HS256 Bearer token.
   - API / Swagger / Postman flow: `POST /api/auth/google/token` with `{"id_token": "<google-oidc-id-token>", "role": "alumni"}`.

#### Token-Based Login, Logout & `Authorization: Bearer <token>` Usage

```bash
# 1. Login (returns HS256 Bearer access_token containing sub, username, role & unique jti)
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"emre.arslan","password":"AlumniPass2026!"}'

# 2. Call a protected route using Authorization: Bearer <token>
curl http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer <YOUR_ACCESS_TOKEN>"

# 3. Logout (revokes token jti server-side in revoked_tokens & clears auth cookie)
curl -X POST http://localhost:8080/api/auth/logout \
  -H "Authorization: Bearer <YOUR_ACCESS_TOKEN>"

# 4. Reusing the logged-out token immediately returns HTTP 401 (TOKEN_REVOKED)
curl http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer <YOUR_ACCESS_TOKEN>"
```

- **Auth & Role Middleware**: `AuthMiddleware` (`src/Middleware/AuthMiddleware.php`) validates signature, expiry, and revocation (`401`) and attaches the authenticated user to `RequestContext`; `RoleMiddleware::requireRole([...])` (`src/Middleware/RoleMiddleware.php`) enforces role permissions (`403 FORBIDDEN_ROLE`).
- **Dynamic Role-Based Navigation**: `public/js/auth-nav.js` queries `GET /api/auth/me` on every page load—rendering **Login/Register** for guests, **My Profile + Logout** for authenticated users, and **My Profile + Admin Panel + Logout** for admins, automatically resetting to guest state if a token expires or is revoked.

#### Assigning an `admin` Account & Accessing Admin / User Pages

- **Option 1 — Seeder (Automatic)**: Running `docker compose exec app php database/seed.php --count=5` seeds the owner admin account from `.env` (`ADMIN_USERNAME=kamasya`, `ADMIN_EMAIL=kamasyaatha.frari@ogr.iu.edu.tr`, `ADMIN_PASSWORD=AdminPass2026!`) plus demo accounts (`emre.arslan` / `AlumniPass2026!`, `deniz.kaya` / `StudentPass2026!`, `trendyol.talent` / `EmployerPass2026!`).
- **Option 2 — CLI Promotion Tool**: Promote any registered local or Google user by email or username:
  ```bash
  docker compose exec app php database/promote_admin.php kamasyaatha.frari@ogr.iu.edu.tr
  ```
- **Option 3 — Direct SQL**:
  ```sql
  UPDATE users SET role = 'admin', is_active = 1 WHERE email = 'kamasyaatha.frari@ogr.iu.edu.tr';
  ```
- **User-Specific Pages (Auth Protected)**: `/my-profile`, `/profile` — edit own alumni profile, graduation year, bio, social links, skills, and inspect submitted applications & job postings (`PATCH /api/v1/profile/me`, `GET /api/v1/profile/me/activity`).
- **Admin Panel (Auth + `admin` Role Protected)**: `/admin` — system telemetry, user role assignment, account activation/deactivation/deletion, alumni management, job posting moderation, and department announcements.

#### Quick Verification (Browser, Swagger UI, Postman & CLI Tests)

- **Local Browser**: Visit `http://alumni-istu` (or `http://localhost:8080`):
  - Welcome screen with **Create Account** / **Login** CTAs at `/`
  - Full Alumni Directory & Career Observatory at `/directory` (and `/dashboard`)
  - Register with CAPTCHA or Google at `/register`
  - Sign in / Log out at `/login`
  - Edit your own profile & activity at `/my-profile`
  - Manage system data as `admin` at `/admin`
- **Swagger UI (`BearerAuth`)**: Open `http://alumni-istu/swagger` (or `http://localhost:8080/swagger`) → execute `POST /api/v1/auth/login` → click **Authorize** → paste `access_token` into **BearerAuth** → test `/api/v1/auth/me`, `/api/v1/profile/me`, and `/api/v1/admin/*`.
- **Postman Collection**: Import `docs/AlumniIstü_Postman_Collection.json` and run:
  - **Folder 5**: Registration, CAPTCHA, Login & Logout (`5.1`–`5.10`)
  - **Folder 6**: Google OAuth 2.0 endpoints (`6.1`–`6.6`)
  - **Folder 7**: Auth & Role Middleware `401`/`403` RBAC tests (`7.1`–`7.5`)
  - **Folder 8**: Admin Panel API (`8.1`–`8.7`)
  - **Folder 9**: User Profile, Dashboard & Ownership `403 OWNERSHIP_VIOLATION` tests (`9.1`–`9.6`)
- **Automated PHP Verification Suites**:
  ```bash
  docker compose exec app php tests/test_auth_and_captcha.php
  docker compose exec app php tests/test_admin_panel.php
  docker compose exec app php tests/test_user_profile_ownership.php
  docker compose exec app php tests/test_middleware_and_validation.php
  ```

---

## 3. Technology Stack & Decision Rationale

| Architecture Layer | Selected Technology | Engineering Rationale | Limitation / Drawback |
| :--- | :--- | :--- | :--- |
| **Backend Runtime** | **PHP 8.2 (Strict Types)** | - **Zero Compilation Overhead**: Instant edit-and-run development loop.<br>- **Native Web Architecture**: Built specifically for HTTP request processing.<br>- **Modern PHP Standards**: Employs `declare(strict_types=1);`, Backed Enums, and DTO patterns without heavyweight framework bloat. | **Synchronous Concurrency**: Operates on a per-request lifecycle; event loops require specialized extensions compared to Go or Node.js. |
| **Database Engine** | **MySQL 8.0 (InnoDB)** | - **Relational Data Integrity**: Strict foreign key constraints and ACID transaction guarantees.<br>- **Hybrid Schema**: Native `JSON` columns allow flexible skillset tagging.<br>- **Container-Native**: Instant provisioning with volume persistence. | **Schema Rigidity**: Evolving massive schemas requires formal database migrations. |
| **Containerization** | **Docker & Docker Compose** | - **Strict Reproducibility**: Satisfies the coursework rule (`docker compose up`) ensuring the stack runs identically on any machine. | **First-Run Overhead**: Initial build requires pulling MySQL and Apache base images. |
| **Developer Assistant**| **Antigravity** | - **Autonomous Agentic Execution**: Context-aware terminal orchestration, automated Dockerfile verification, and migration generators. | **Newer Ecosystem**: Smaller repository of historical third-party forum threads. |

---

## 4. Architectural Design & Directory Layout

```text
alumni/
├── Dockerfile                  # Production-ready PHP 8.2 Apache container configuration
├── docker-compose.yml          # Multi-container orchestration (AlumniIstü API + MySQL 8.0)
├── AGENTS.md                   # AI Assistant operational context & architectural protocols
├── README.md                   # Complete system documentation & quickstart guide
├── brand.md                    # Brand identity specification (Navy #14213D, Cream #F4EDE0, Coral #FF6B58)
├── design.md                   # Design system & token specifications (surfaces, shapes, CSS variables)
├── ddd.md                      # Data Design Documentation (ERD, data dictionary, persistence)
├── .gitignore                  # Git exclusions for OS, containers, and .env files
├── .env.example                # Environment variables reference template
├── .example.env                # Environment variables alternate reference template
├── .env                        # Active environment variables (git-ignored)
│
├── public/                     # [Document Root] Public web access gateway
│   ├── .htaccess               # Apache URL rewrite rules to Front Controller
│   └── index.php               # Front Controller, PSR-4 autoloader & route dispatcher
│
├── logs/                       # [Telemetry & Diagnostics] Auto-managed error logs
│   └── error.logs              # Incident stack traces recorded by 5xx Guardian
│
├── src/                        # [Application Core] Domain logic & infrastructure
│   ├── Core/
│   │   ├── Database.php        # Type-safe PDO singleton connection wrapper
│   │   ├── Env.php             # Lightweight .env environment loader
│   │   ├── Response.php        # Standardized JSON and HTML response serializer
│   │   └── Router.php          # Parametric regex route dispatcher ({id} and :id support)
│   ├── Middleware/
│   │   ├── MiddlewareInterface.php         # Standard HTTP middleware contract
│   │   ├── LoggerMiddleware.php            # Request logger emitting [time] [method] [route] [res.type] [duration]
│   │   ├── NotFoundGuardianMiddleware.php  # 404 Guardian with descriptive error reasons
│   │   ├── Guardian404Middleware.php       # Alias wrapper for 404 Guardian
│   │   ├── ServerErrorGuardianMiddleware.php # 5xx Guardian with auto file logging
│   │   └── Guardian5xxMiddleware.php       # Alias wrapper for 5xx Guardian
│   ├── Models/
│   │   ├── JobPosting.php      # JobPosting model with Company, Poster, Applications relations
│   │   ├── Application.php     # Application model with JobPosting & Applicant relations
│   │   ├── Company.php         # Company entity with published JobPostings relation
│   │   ├── Alumni.php          # Alumni profile entity with JobPostings & Applications relations
│   │   └── User.php            # User entity alias for classroom compatibility
│   ├── Domain/
│   │   └── Enums/
│   │       ├── UserRole.php    # Backed Enum: ALUMNI, STUDENT, FACULTY, ADMIN
│   │       └── MentorshipStatus.php # Backed Enum: AVAILABLE, AT_CAPACITY, INACTIVE
│   ├── Services/
│   │   ├── AlumniService.php   # Business logic, full CRUD operations & career analytics
│   │   └── UserService.php     # In-memory user management without database
│   └── Controllers/
│       ├── AlumniApiController.php     # REST API v1 alumni CRUD endpoints
│       ├── JobPostingApiController.php # REST API v1 job postings (validation & nested routes)
│       ├── ApplicationApiController.php# REST API v1 applications (CRUD & nested routes)
│       ├── CompanyApiController.php    # REST API v1 companies catalog endpoints
│       ├── UserController.php          # In-memory user management controller
│       ├── HealthController.php        # Telemetry and system health probes
│       └── WebController.php           # Template dispatcher for browser views
│
├── database/                   # [Database Subsystem]
│   ├── schema.sql              # Initial MySQL DDL schema & baseline fixtures
│   ├── migrate.php             # Database migration CLI runner (up, status, fresh)
│   ├── seed.php                # Unified database seeder CLI runner (--count=20)
│   ├── seed_jobs.php           # Dedicated job postings & applications seeder
│   ├── migrations/             # Incremental migration SQL files
│   │   ├── 001_create_departments_table.sql
│   │   ├── 002_create_alumni_table.sql
│   │   ├── 003_create_mentorship_requests_table.sql
│   │   ├── 004_create_job_postings_table.sql
│   │   └── 005_create_applications_table.sql
│   ├── factories/
│   │   ├── AlumniFactory.php   # Factory generating realistic Turkish alumni dataset
│   │   ├── JobPostingFactory.php # Factory generating realistic tech job postings
│   │   └── ApplicationFactory.php# Factory generating realistic candidate applications
│   └── seeders/
│       └── DatabaseSeeder.php  # Unified database seeder
│
├── templates/                  # [Presentation Layer] High-performance client views
│   ├── dashboard.html          # Interactive alumni directory with CRUD modals (Tailwind CSS)
│   ├── jobs.html               # Interactive job board & application pipeline UI
│   ├── about.html              # Department overview & temporary about page
│   ├── analytics.html          # Career Graph mobility & accreditation dashboard
│   ├── swagger.html            # Dark-themed Swagger UI API Explorer
│   ├── 404.html                # 404 Guardian error template (dark-mode, diagnostic badges)
│   └── 500.html                # 5xx Guardian error template (dark-mode, incident ID display)
│
├── docs/
│   ├── openapi.json            # OpenAPI 3.1.0 interactive contract specification
│   └── AlumniIstü_Week03_Postman_Collection.json # Week 03 Postman Collection
│
└── tests/
    ├── run_tests.php           # Automated CLI master assertion suite
    ├── test_week3_api.php      # Week 03 automated verification suite
    └── forgetting_test.sh      # Bash automated validation for "The Forgetting Test"
```

---

## 5. How to Run the System

### Primary Method: Single-Command Docker Orchestration
In strict adherence to project evaluation instructions:

```bash
docker compose up --build
```

- Boots **MySQL 8.0** with health checks, automatically creating and mounting named volume `nexus_mysql_data`.
- Boots **PHP 8.2 Apache**, routing traffic through `/public/index.php`.
- The application will be immediately live at: **`http://localhost:8080`**.
- Subdirectory deployments on local Apache/XAMPP (`http://localhost/alumni`) are automatically supported via dynamic base path normalization.

---

### Whiteboard Milestone Routes (`Routes / "auto"`)

| # | HTTP Method | Endpoint Pattern | Example URL | Expected Response / Output |
| :-: | :--- | :--- | :--- | :--- |
| **1** | `GET` / `POST` | `/` or `/auto` | `http://localhost:8080/auto` | `"ok"` |
| **2** | `GET` | `/hello` | `http://localhost:8080/hello` | `"Hello, World!"` |
| **3** | `GET` | `/hello/{name}` | `http://localhost:8080/hello/emre` | `"Hello, Emre!"` |
| **4** | `GET` | `/sum/{number1}/{number2}` | `http://localhost:8080/sum/15/25` | `40` (sum of number1 + number2) |
| **5** | `GET` | `/` | `http://localhost:8080/` | Temporary One Main Page (`templates/dashboard.html`) |
| **6** | `GET` | `/about` | `http://localhost:8080/about` | Temporary About Page (`templates/about.html`) |

---

### Week 03 Whiteboard Milestone: RESTful API & In-Memory User Management
*(Lecture: Assoc. Prof. Dr. Emre Akadal • Web Programming • Week 03)*

| Item # | HTTP Method | Endpoint | Description | Request Body Example | Expected Status |
| :-: | :--- | :--- | :--- | :--- | :-: |
| **①** | `GET` | `/api/health` | Health Check Probe (Returns JSON) | *None* | `200 OK` |
| **②** | *Tooling* | **Postman** | Import collection: `docs/AlumniIstü_Week03_Postman_Collection.json` | - | - |
| **③** | `POST` | `/api/users` | Create User *(Don't use any database yet)* | `{"name": "Elif Kaya", "graduationYear": 2024}` | `201 Created` |
| **④** | `GET` | `/api/users` | List All Users (In-Memory Dataset) | *None* | `200 OK` |
| **⑤** | `GET` | `/api/users/{id}` | Retrieve Single User by ID | *None* | `200 OK` / `404` |
| **⑤** | `PUT` | `/api/users/{id}` | Replace Entire User Object *(Slide C: replace whole)* | `{"name": "Elif Kaya Akın", "graduationYear": 2025}` | `200 OK` / `404` |
| **⑤** | `PATCH` | `/api/users/{id}` | Partial Update *(Slide C: change one field)* | `{"graduationYear": 2026}` | `200 OK` / `404` |
| **⑥** | `DELETE` | `/api/users/{id}` | Remove User Record *(Slide C: remove record)* | *None* | `200 OK` / `404` |
| **⑦** | `GET` | `/api/swagger` | Interactive Swagger UI API Documentation | *None* | `200 OK` |

> **Slide D Verification**:
> - Request body sent: `{"name": "Elif Kaya", "graduationYear": 2024}`
> - Response received: `HTTP/1.1 201 Created` with Content-Type `application/json`
> - Payload: `{"id": 42, "name": "Elif Kaya", "graduationYear": 2024}`
> - `/api/alumni` routes are also provided as direct aliases for live lecture testing.

---

### Week 07 Whiteboard Milestone: Middleware, Error Guardians & JobPosting Validation
*(Lecture: Assoc. Prof. Dr. Emre Akadal • Web Programming • Week 07 — 2026-11-11)*

| Item # | Component | File | Description | Terminal / HTTP Output |
| :-: | :--- | :--- | :--- | :--- |
| **①** | **Logger Middleware** | `src/Middleware/LoggerMiddleware.php` | Outputs real-time HTTP telemetry to terminal on every request | `12:20:30 Post /user 201 18 ms` |
| **②** | **JobPosting Validation** | `src/Controllers/JobPostingApiController.php` | Domain rules: `title` required + max 120 chars; `company` required; `salary` optional; `description` optional | `HTTP 422` on violation |
| **③** | **404 Guardian Middleware** | `src/Middleware/NotFoundGuardianMiddleware.php` | Returns structured JSON / HTML 404 with human-readable `reason` for unmatched routes | `HTTP 404 {"guardian": "404 Guardian Middleware", ...}` |
| **④** | **5xx Guardian Middleware** | `src/Middleware/ServerErrorGuardianMiddleware.php` | Catches all uncaught exceptions and fatal errors, returns clean 500 responses | `HTTP 500 {"guardian": "5xx Guardian Middleware", "error_id": "err_..."}` |
| **⑤** | **Error File Logging** | `logs/error.logs` | Auto-creates `logs/` directory; appends stack traces with unique `error_id` on 5xx events | `[2026-11-11 12:00:00] [500] [ID:err_xxx] [METHOD /path]` |
| **⑥** | **Generic 404 & Redirection** | `public/index.php` + `src/Middleware/NotFoundGuardianMiddleware.php` | JSON for API routes, HTML template for browsers, `302 Redirect` for known URL aliases | `/home → /`, `/job → /jobs` |

**Logger Middleware Terminal Output Verification:**
```text
Terminal (docker logs alumni-app-1):
  12:20:30 Post /api/v1/alumni 201 18 ms
  12:20:35 Get /api/v1/alumni 200 5 ms
  12:21:00 Get /api/v1/job-postings/999 404 3 ms
  12:21:15 Post /api/v1/alumni 422 2 ms
```

**404 Guardian JSON Verification:**
```bash
curl http://localhost:8080/api/v1/nonexistent-route
# → HTTP 404
# → {"status":"error","code":404,"guardian":"404 Guardian Middleware","reason":"The requested endpoint 'GET /api/v1/nonexistent-route' does not exist..."}
```

**5xx Guardian + File Logging Verification:**
```bash
curl http://localhost:8080/api/crash-test
# → HTTP 500 (clean JSON, no stack trace leaked)
cat logs/error.logs
# → [2026-11-11 12:00:00] [500] [ID:err_671452c4] [GET /api/crash-test]
#    Error: RuntimeException: ...
#    Trace: ...
```

**JobPosting Validation Verification:**
```bash
# Missing title → HTTP 422
curl -X POST http://localhost:8080/api/v1/job-postings -d '{"company":"Trendyol"}'
# → {"status":"error","code":422,"message":"Validation error: 'title' is required."}

# Title too long → HTTP 422
curl -X POST http://localhost:8080/api/v1/job-postings -d '{"title":"AAAAA...121chars","company":"Trendyol"}'
# → {"status":"error","code":422,"message":"Validation error: 'title' must not exceed 120 characters."}

# Missing company → HTTP 422
curl -X POST http://localhost:8080/api/v1/job-postings -d '{"title":"Backend Engineer"}'
# → {"status":"error","code":422,"message":"Validation error: 'company' (or company_name) is required."}

# Valid (salary & description optional) → HTTP 201
curl -X POST http://localhost:8080/api/v1/job-postings -d '{"title":"Backend Engineer","company":"Trendyol"}'
# → {"status":"success","message":"Job posting created successfully","data":{...}}
```

---

### Key Application Routes Summary

| Surface | URL Path | Method | Description |
| :--- | :--- | :--- | :--- |
| **Directory UI** | [`http://localhost:8080/`](http://localhost:8080/) | `GET` | Interactive Alumni Directory with live CRUD modals & search |
| **Job Board UI** | [`http://localhost:8080/jobs`](http://localhost:8080/jobs) | `GET` | Interactive Job Board & applications pipeline interface |
| **About Page** | [`http://localhost:8080/about`](http://localhost:8080/about) | `GET` | Istanbul University MIS Department overview (Whiteboard Item 6) |
| **Analytics UI** | [`http://localhost:8080/analytics`](http://localhost:8080/analytics) | `GET` | Career mobility graph and institutional accreditation KPIs |
| **Register UI** | [`http://localhost:8080/register`](http://localhost:8080/register) | `GET` | Standard registration with SVG/Math CAPTCHA + Continue with Google |
| **Login UI** | [`http://localhost:8080/login`](http://localhost:8080/login) | `GET` | Local & Google OAuth sign-in, Bearer session inspector & Logout |
| **My Profile UI** | [`http://localhost:8080/my-profile`](http://localhost:8080/my-profile) | `GET` | Auth-protected member dashboard, profile editor & My Activity (`/dashboard`, `/profile`) |
| **Admin Panel UI** | [`http://localhost:8080/admin`](http://localhost:8080/admin) | `GET` | Admin-only control plane for users, roles, alumni, jobs & announcements |
| **API Explorer** | [`http://localhost:8080/swagger`](http://localhost:8080/swagger) | `GET` | Interactive Swagger UI API documentation (`BearerAuth` enabled) |
| **Auth & CAPTCHA** | [`http://localhost:8080/api/auth/captcha`](http://localhost:8080/api/auth/captcha) | `GET/POST` | `/api/auth/{captcha,register,login,logout,me,google,google/callback,google/token}` |
| **Profile API** | [`http://localhost:8080/api/v1/profile/me`](http://localhost:8080/api/v1/profile/me) | `GET/PATCH` | Authenticated own profile, `/me/activity`, and ownership-enforced `/users/{id}` |
| **Admin API** | [`http://localhost:8080/api/v1/admin/overview`](http://localhost:8080/api/v1/admin/overview) | `CRUD` | Admin-only `/api/v1/admin/{overview,users,alumni,job-postings,announcements}` |
| **Health Probe** | [`http://localhost:8080/api/v1/health`](http://localhost:8080/api/v1/health) | `GET` | System memory telemetry and database connectivity status |
| **Users API** | [`http://localhost:8080/api/users`](http://localhost:8080/api/users) | `GET/POST` | Week 03 In-Memory User CRUD without database |
| **List Alumni** | [`http://localhost:8080/api/v1/alumni`](http://localhost:8080/api/v1/alumni) | `GET` | Query alumni (`?cohort=`, `?industry=`, `?mentor=`, `?search=`) |
| **Create Alumni**| [`http://localhost:8080/api/v1/alumni`](http://localhost:8080/api/v1/alumni) | `POST` | Create new alumni record (`POST /api/users` also supported) |
| **Show Alumni**  | [`http://localhost:8080/api/v1/alumni/{id}`](http://localhost:8080/api/v1/alumni/1) | `GET` | Retrieve single alumni profile by ID |
| **Update Alumni**| [`http://localhost:8080/api/v1/alumni/{id}`](http://localhost:8080/api/v1/alumni/1) | `PUT/PATCH` | Modify existing profile |
| **Delete Alumni**| [`http://localhost:8080/api/v1/alumni/{id}`](http://localhost:8080/api/v1/alumni/1) | `DELETE` | Remove alumni record |
| **Job Postings** | [`http://localhost:8080/api/v1/job-postings`](http://localhost:8080/api/v1/job-postings) | `CRUD` | Direct & nested routes for company job opportunities |
| **Applications** | [`http://localhost:8080/api/v1/applications`](http://localhost:8080/api/v1/applications) | `CRUD` | Candidate application submission & review workflow |
| **OpenAPI Spec** | [`http://localhost:8080/openapi.json`](http://localhost:8080/openapi.json) | `GET` | Raw OpenAPI 3.1.0 JSON contract |

---

### Executing Migrations, Seeders & The Forgetting Test

Within the container (or locally if PHP/MySQL are installed):

```bash
# Run database migrations
docker compose exec app php database/migrate.php

# Check migration status
docker compose exec app php database/migrate.php status

# Seed realistic alumni data
docker compose exec app php database/seed.php --count=20

# Run the automated Forgetting Test
./tests/forgetting_test.sh
```

---

## 6. Academic Context & Course Information
- **Institution**: Istanbul University (`ogr.iu.edu.tr`)
- **Faculty**: Faculty of Economics (İktisat Fakültesi)
- **Department**: Management Information Systems (Yönetim Bilişim Sistemleri - YBS)
- **Repository**: `github.com/kamasya-atha-frari/ALUMNI`
- **Documentation**: [`brand.md`](./brand.md) • [`design.md`](./design.md) • [`ddd.md`](./ddd.md)
- **License**: MIT
