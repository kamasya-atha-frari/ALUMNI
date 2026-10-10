# Istanbul University MIS Alumni Management System (`alumni`)

A comprehensive alumni tracking and engagement portal engineered specifically for the graduates, faculty, and students of **Istanbul University Faculty of Economics, Department of Management Information Systems (Yönetim Bilişim Sistemleri - MIS)**.

---

## 1. Project Overview

The **Istanbul University MIS Alumni Portal** bridges the gap between past graduates, current students, faculty members, and university administration. Built specifically for the Faculty of Economics Department of Management Information Systems, this platform serves dual imperatives:

1. **Alumni Community Empowerment**: Provides graduates with a modern digital environment to network, find career and mentorship opportunities, coordinate reunions, access campus resources, and support faculty initiatives.
2. **Institutional Tracking & Accreditation Analytics**: Equips faculty deans, department chairs, and quality accreditation coordinators (e.g., YÖKAK, AACSB criteria) with empirical data regarding graduate employment rates, salary progression, industry distribution, and geographic mobility.

```text
┌─────────────────────────────────────────────────────────────────────────────────┐
│               Istanbul University MIS Alumni Portal Ecosystem                   │
└──────────────────────────────────────┬──────────────────────────────────────────┘
                                       │
       ┌───────────────────────────────┼───────────────────────────────┐
       ▼                               ▼                               ▼
┌───────────────┐               ┌───────────────┐               ┌───────────────┐
│ Alumni Portal │               │  Career Hub   │               │   Admin CRM   │
│  & Directory  │               │ & Mentorship  │               │& Accreditation│
└───────────────┘               └───────────────┘               └───────────────┘
       │                               │                               │
       ├─ Directory Search             ├─ Job Board                    ├─ Graduate KPIs
       ├─ Privacy Toggles              ├─ Mentor Matching              ├─ YÖKAK Reports
       ├─ Career Timelines             ├─ Resume Reviews               ├─ Campaign Audit
       └─ Verified Badges              └─ Referral Network             └─ User Moderation
```

---

## 2. Key Features & System Modules

### 2.1 Alumni Directory & Profile Management
- **Granular Academic Tracking**: Records student ID, graduation year, degree level, and faculty advisors.
- **Professional Career Timeline**: Tracks current company, job title, industry sector (FinTech, SaaS, ERP Consulting, Data Analytics), and work location.
- **Parametric Multi-Filter Search**: Instant search filtering by graduation class, industry, current employer, and mentor availability.
- **Privacy & GDPR/KVKK Compliance**: Configurable privacy controls allowing alumni to selectively hide contact details from public view.

### 2.2 Career Services & Mentorship Hub
- **Alumni-to-Student Mentorship**: Mentors advertise available slots, domains of expertise (Product Management, Cloud Engineering, Business Intelligence), and preferred meeting formats.
- **Opportunity Board**: Alumni and partner companies publish internships and junior roles targeted at MIS graduates.

### 2.3 Events & Administrative Analytics
- **Departmental Reunions**: Publishing engine supporting department reunions and academic seminars with RSVP tracking.
- **Accreditation Readiness Dashboard**: Exportable metrics formatted for institutional review boards (YÖKAK, AACSB) measuring graduate employment within 6/12 months of graduation.

---

## 3. Technology Stack & Decision Rationale

| Layer | Selected Tech | Selection Rationale | Limitation / One Thing It Is Bad At |
| :--- | :--- | :--- | :--- |
| **Backend Language** | **PHP 8.2** | - **Native Web Integration**: Designed specifically for the web; handles HTTP requests, headers, and routing natively without complex runtime build steps.<br>- **Zero-Compilation Speed**: Code edits take effect immediately, allowing fast iterative prototyping.<br>- **Universal Compatibility**: Runs on Apache and Nginx with industry-standard PDO database abstraction. | **Concurrency & Threading**: Follows a synchronous, per-request execution model, resulting in higher memory overhead and lack of event-driven async concurrency compared to Node.js or Go. |
| **Database** | **MySQL 8.0** | - **Relational Data Integrity**: Alumni records, cohorts, faculties, and mentorship relations are strictly relational, demanding foreign-key constraints and ACID transactions.<br>- **Classic LAMP Synergy**: Proven, battle-tested pairing with PHP PDO.<br>- **Container-Ready**: Easily managed and initialized via Docker Compose. | **Analytical Queries & Rigid Schema**: Complex analytical window operations are slower than PostgreSQL, and table schema alterations require formal migration planning. |
| **AI Assistant** | **Antigravity** | - **Deep Agentic Integration**: Seamless terminal execution and file context automation directly within the development workflow.<br>- **Zero Cost**: Completely free and does not require paid enterprise tokens. | **Ecosystem Maturity**: Being a modern agentic assistant, community troubleshooting threads are still emerging compared to older assistants. |

---

## 4. Architectural Design & Project Structure

The codebase is organized following the **Model-View-Controller (MVC)** architectural pattern with dedicated Front Controller routing:

```text
Alumni/
│
├── .htaccess                  # [Gateway / Routing] Apache URL rewrite rules to Front Controller
├── index.php                  # [Controller / Router] Front Controller, URI normalization, route dispatcher
├── alumni.php                 # [Controller] Dedicated Alumni Resource Controller & fallback handler
├── README.md                  # [Documentation] Comprehensive project architecture and setup manual
├── AGENTS.md                  # [Agent Context] AI assistant workflow directives (Week 02 milestone)
├── Dockerfile                 # [Containerization] PHP 8.2 Apache container configuration
├── docker-compose.yml         # [Orchestration] Multi-container stack (PHP/Apache + MySQL 8.0)
├── .gitignore                 # [Version Control] Exclusions for OS, IDE, and temporary DB files
│
├── config/                    # [Configuration & DAL] Configuration and Database Abstraction Layer
│   ├── config.php             # Application, database credentials, and institutional metadata
│   └── db.php                 # PDO database connection factory (singleton with error handling)
│
├── controllers/               # [Controllers] Application Controllers
│   ├── UserController.php     # Web Controller for user management views, forms, and actions
│   └── ApiUserController.php  # REST API Controller for User CRUD JSON endpoints
│
├── database/                  # [Model / Persistence] Relational Database Schema & Data Fixtures
│   └── schema.sql             # MySQL DDL schema (users, alumni_profiles tables, constraints, seed data)
│
├── docs/                      # [API Specifications] OpenAPI 3.0.3 & Swagger Documentation
│   ├── openapi.json           # Complete OpenAPI 3.0.3 JSON specification
│   └── openapi.yaml           # Complete OpenAPI 3.0.3 YAML specification
│
├── models/                    # [Models / Domain] Application Domain Models
│   └── User.php               # In-memory User model with full CRUD operations & DB sync
│
├── routes/                    # [Routing Layer] Dedicated Route Definitions & Router Module
│   ├── Router.php             # Core OOP Router engine (pattern matching, parameter parsing, reflection dispatch)
│   ├── web.php                # Web route definitions (GET /users, POST /users, management routes)
│   └── api.php                # REST API route definitions (/api/users CRUD endpoints)
│
├── views/                     # [View / Presentation] HTML5 Templates & Client Interfaces
│   ├── main.html              # Alumni portal dashboard, responsive UI, dynamic JS directory consumer
│   ├── about.html             # Department overview, academic history, accreditation, and faculty info
│   ├── users.html             # User management UI view (directory table, stats, create modal)
│   └── swagger.html           # Interactive Swagger UI API documentation explorer
│
└── tests/                     # [Quality Assurance] Automated & Declarative Test Suites
    ├── api_tests.http         # RFC 2616 HTTP test collection for REST clients (VS Code / Postman)
    └── test_endpoints.php     # Automated PHP CLI test suite (assertions across all routes & CRUD)
```

---

## 5. How to Run the System

### The Golden Rule: Single-Command Docker Orchestration
In strict compliance with coursework instructions, the entire system launches using a single command:

```bash
docker compose up --build
```

### Quickstart Steps

1. **Clone the repository:**
   ```bash
   git clone https://github.com/<your-username>/alumni.git
   cd alumni
   ```

2. **Run with Docker Compose:**
   ```bash
   docker compose up
   ```
   *Docker Compose automatically initializes the MySQL container, imports `database/schema.sql`, builds the Apache/PHP service, and starts serving on port `8080`.*

3. **Explore the Application in Your Browser:**
   - **Main Portal Dashboard**: [http://localhost:8080/](http://localhost:8080/)
   - **Alumni Management Table**: [http://localhost:8080/users](http://localhost:8080/users)
   - **About the Department**: [http://localhost:8080/about](http://localhost:8080/about)
   - **Interactive Swagger Documentation**: [http://localhost:8080/swagger](http://localhost:8080/swagger)
   - **Dedicated Alumni API**: [http://localhost:8080/alumni](http://localhost:8080/alumni)
   - **Filter by Graduation Cohort**: [http://localhost:8080/alumni?year=2023](http://localhost:8080/alumni?year=2023)

4. **Alternative: Standalone PHP Built-in Server (Without Docker):**
   ```bash
   php -S localhost:8000 index.php
   ```

5. **Run the Automated Test Suite:**
   ```bash
   php tests/test_endpoints.php
   ```

---

## 6. Documented Endpoints Summary

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/` | Web: Alumni portal dashboard with live directory preview |
| `GET` | `/about` | Web: Istanbul University MIS department overview & academic history |
| `GET` | `/users` | Web: User management directory table with statistics |
| `POST` | `/users` | Web: Form submission to create user |
| `POST` | `/users/{id}/delete` | Web: Form submission to delete user |
| `GET` | `/swagger` | Web: Interactive Swagger UI explorer |
| `GET` | `/openapi.json` | API: Complete OpenAPI 3.0.3 specification |
| `GET` | `/alumni` | API: Retrieve all alumni profiles (supports `?year={cohort}`) |
| `GET` | `/api/users` | API: JSON list of all user records |
| `POST` | `/api/users` | API: Create user record via JSON payload |
| `GET` | `/api/users/{id}` | API: Retrieve single user record by ID |
| `PUT` | `/api/users/{id}` | API: Update user record |
| `DELETE` | `/api/users/{id}` | API: Delete user record |
| `GET` | `/auto/hello/{name}` | Utility: Parameterized greeting test route |
| `GET` | `/auto/sum/{a}/{b}` | Utility: Parameterized calculation test route |

---

## 7. Academic Context & Attribution
- **Institution**: Istanbul University (`ogr.iu.edu.tr`)
- **Faculty**: Faculty of Economics
- **Department**: Management Information Systems (Yönetim Bilişim Sistemleri - MIS)
- **Target Repository**: `github.com/<your-username>/alumni`
