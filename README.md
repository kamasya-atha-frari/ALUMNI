# AlumniNexus (`alumni`)

### Intelligent Alumni Career Graph & Engagement Network
*Designed for graduates, students, and faculty of **Istanbul University Faculty of Economics, Department of Management Information Systems (Yönetim Bilişim Sistemleri - YBS)**.*

> 📘 **Data Design Documentation**: For comprehensive ER diagrams, detailed data dictionaries, schema architectures, and the Forgetting Test protocol, see [**Data Design Documentation (`ddd.md`)**](./ddd.md).

---

## 1. Executive Summary & Vision

**AlumniNexus** is a modern, containerized backend platform and career mobility engine engineered to bridge the generational divide between university alumni, current undergraduates, and academic leadership. Rather than serving as a static contact book, AlumniNexus models alumni data as an active **Career & Mentorship Graph**, delivering:

1. **Talent Discovery & Professional Mobility**: Granular tracking of graduate career trajectories across technology, banking, e-commerce, and enterprise consulting sectors.
2. **Algorithmic Mentorship Pairings**: Structured matching between senior alumni in leadership roles and current undergraduates seeking guidance.
3. **Institutional Telemetry for Academic Accreditations**: Automated empirical reporting on graduate employment velocity and curriculum alignment for accreditation evaluation boards (e.g., **YÖKAK**, **AACSB**).
4. **Resilient Persistence & Modern DevOps**: Containerized via Docker Compose with strict environment isolation, automated migration runners, realistic factories/seeders, and guaranteed state recovery passing the **Forgetting Test**.

```text
┌──────────────────────────────────────────────────────────────────────────────────┐
│                           AlumniNexus Core Architecture                          │
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
  - Graduate Directory: `http://localhost:8080/`
  - Job Board & Application Pipeline: `http://localhost:8080/jobs`
  - Career Graph Telemetry: `http://localhost:8080/analytics`

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
├── docker-compose.yml          # Multi-container orchestration (AlumniNexus API + MySQL 8.0)
├── AGENTS.md                   # AI Assistant operational context & architectural protocols
├── README.md                   # Complete system documentation & quickstart guide
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
├── src/                        # [Application Core] Domain logic & infrastructure
│   ├── Core/
│   │   ├── Database.php        # Type-safe PDO singleton connection wrapper
│   │   ├── Env.php             # Lightweight .env environment loader
│   │   ├── Response.php        # Standardized JSON and HTML response serializer
│   │   └── Router.php          # Parametric regex route dispatcher ({id} and :id support)
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
│   │   └── AlumniService.php   # Business logic, full CRUD operations & career analytics
│   └── Controllers/
│       ├── AlumniApiController.php     # REST API v1 alumni CRUD endpoints
│       ├── JobPostingApiController.php # REST API v1 job postings (CRUD & nested routes)
│       ├── ApplicationApiController.php# REST API v1 applications (CRUD & nested routes)
│       ├── CompanyApiController.php    # REST API v1 companies catalog endpoints
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
│   ├── dashboard.html          # Interactive alumni directory with CRUD modals (Tailwind)
│   ├── jobs.html               # Interactive job board & application pipeline UI
│   ├── analytics.html          # Career Graph mobility & accreditation dashboard
│   ├── swagger.html            # Dark-themed Swagger UI API Explorer
│   └── 404.html                # Error fallback template
│
├── docs/
│   └── openapi.json            # OpenAPI 3.1.0 interactive contract specification
│
└── tests/
    ├── run_tests.php           # Automated CLI assertion suite
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

---

### Key Application Routes

| Surface | URL Path | Method | Description |
| :--- | :--- | :--- | :--- |
| **Directory UI** | [`http://localhost:8080/`](http://localhost:8080/) | `GET` | Interactive Alumni Directory with live CRUD modals & search |
| **Analytics UI** | [`http://localhost:8080/analytics`](http://localhost:8080/analytics) | `GET` | Career mobility graph and institutional accreditation KPIs |
| **API Explorer** | [`http://localhost:8080/swagger`](http://localhost:8080/swagger) | `GET` | Interactive Swagger UI API documentation |
| **Health Probe** | [`http://localhost:8080/api/v1/health`](http://localhost:8080/api/v1/health) | `GET` | System memory telemetry and database connectivity status |
| **List Alumni** | [`http://localhost:8080/api/v1/alumni`](http://localhost:8080/api/v1/alumni) | `GET` | Query alumni (`?cohort=`, `?industry=`, `?mentor=`, `?search=`) |
| **Create Alumni**| [`http://localhost:8080/api/v1/alumni`](http://localhost:8080/api/v1/alumni) | `POST` | Create new alumni record (`POST /api/users` also supported) |
| **Show Alumni**  | [`http://localhost:8080/api/v1/alumni/{id}`](http://localhost:8080/api/v1/alumni/1) | `GET` | Retrieve single alumni profile by ID |
| **Update Alumni**| [`http://localhost:8080/api/v1/alumni/{id}`](http://localhost:8080/api/v1/alumni/1) | `PUT/PATCH` | Modify existing profile |
| **Delete Alumni**| [`http://localhost:8080/api/v1/alumni/{id}`](http://localhost:8080/api/v1/alumni/1) | `DELETE` | Remove alumni record |
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
- **Documentation**: [ddd.md](./ddd.md)
- **License**: MIT
