# Istanbul University MIS Alumni Management System (`alumni`)

A containerized backend system and directory API engineered for tracking, engaging, and connecting graduates, faculty, and students of **Istanbul University Faculty of Economics, Department of Management Information Systems (Yönetim Bilişim Sistemleri - MIS)**.

---

## 1. Project Overview (What the system is)

The **Alumni Management System** is an institutional platform designed to bridge the gap between alumni, current undergraduate students, and faculty administration. It serves as a single source of truth for:
- Maintaining verified graduate directories.
- Monitoring career progression, industry distribution, and employment mobility.
- Supporting institutional quality benchmarks and accreditation standards (e.g., YÖKAK, AACSB).
- Enabling alumni-to-student mentorship and career networking.

The project is structured with an API-first backend architecture running on **PHP 8.2** and **MySQL 8.0**, fully orchestrated for local execution via **Docker Compose**.

---

## 2. System Scope & Functional Requirements (What it has to do)

The application handles core data workflows and functional domains:

1. **Graduate Directory & Profile Management**:
   - Store verified student records (Student ID, Full Name, Graduation Year, Degree, Department, Institutional Email).
   - Track professional records: current company, job title, industry sector, and work location.
   - Granular privacy controls (opt-in public profile vs. private contact details).

2. **Search, Directory & Query Filtering**:
   - Query alumni by graduation year cohort, department, or company.
   - RESTful JSON endpoints for integration with web dashboards or mobile applications.

3. **Career Progression & Mentorship Hub**:
   - Flag alumni who are actively available for student mentoring.
   - Provide channels for referral requests and career insights.

4. **Accreditation Metrics & Administrative Analytics**:
   - Provide aggregated data on graduate employment timelines and market verticals to assist faculty quality assessment boards.

---

## 3. Technology Stack Decisions & Rationale

| Layer | Selected Tech | Why We Picked It | One Thing It Is Bad At |
| :--- | :--- | :--- | :--- |
| **Backend Language** | **PHP 8.2** | - **Native Web Integration**: PHP is purpose-built for web servers; it natively parses HTTP requests, queries, and headers without heavy framework bloat.<br>- **Zero-Compilation Speed**: Changes in code take effect immediately without requiring compilation cycles.<br>- **Universal Host Compatibility**: Runs seamlessly on Apache and Nginx with industry-proven database abstraction (PDO). | **Concurrency & Threading**: PHP uses a per-request execution model and lacks built-in event-driven async concurrency out of the box compared to runtimes like Node.js or Go, making high-frequency WebSocket loads more resource-heavy. |
| **Database** | **MySQL 8.0** | - **Relational Data Integrity**: Alumni records, faculties, graduation cohorts, and mentorship relationships are inherently relational. MySQL enforces strict foreign-key relationships and ACID transactions.<br>- **Standard LAMP Synergy**: MySQL is the established, reliable pairing with PHP PDO.<br>- **Simple Docker Orchestration**: Official `mysql:8.0` image offers reliable health-checking and automatic `.sql` schema provisioning. | **Complex Analytical Queries & Schema Rigidity**: Schema alterations on large tables require formal migration planning, and it lacks some advanced analytical windowing and custom typing native to PostgreSQL. |
| **AI Assistant** | **Antigravity** | - **Agentic Workspace Execution**: Direct file and terminal integration streamlines Docker orchestration and debugging.<br>- **Zero Cost**: Completely free and does not require paid enterprise tokens. | **Ecosystem History**: As a modern agentic tool, it has a newer presence and fewer community discussion threads compared to older assistants. |

---

## 4. How to Run the System

In accordance with course project requirements, the system starts with a single command:

```bash
docker compose up --build
```

### Prerequisites
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running.
- [Docker Compose](https://docs.docker.com/compose/) (v2+).

### Quickstart Execution Steps

1. **Clone the repository:**
   ```bash
   git clone https://github.com/<your-username>/alumni.git
   cd alumni
   ```

2. **Launch all services:**
   ```bash
   docker compose up
   ```
   *Docker Compose will build the PHP/Apache container, launch the MySQL 8.0 database, and automatically run `/docker-entrypoint-initdb.d/init.sql` to initialize tables and seed data.*

3. **Test the endpoints in your browser or curl:**
   - **System Health & Meta Information:**  
     [http://localhost:8080/](http://localhost:8080/)
   - **Alumni Directory (All records):**  
     [http://localhost:8080/alumni.php](http://localhost:8080/alumni.php)
   - **Filter Alumni by Graduation Cohort (e.g. 2023):**  
     [http://localhost:8080/alumni.php?year=2023](http://localhost:8080/alumni.php?year=2023)

4. **Stop the containers:**
   ```bash
   docker compose down
   ```

---

## 5. Repository Structure

```text
alumni/
├── README.md               # Complete system specifications & execution guide
├── docker-compose.yml      # Multi-container orchestration (PHP/Apache + MySQL)
├── Dockerfile              # Docker image definition (PHP 8.2 + pdo_mysql)
├── AGENTS.md               # Agent development guidelines for upcoming milestones
├── .gitignore              # Git exclusion rules
├── database/
│   └── schema.sql          # Relational DDL schema & seed data fixtures
└── src/
    ├── config.php          # Database PDO connection factory with standby fallback
    ├── index.php           # Front service status & API health endpoint
    └── alumni.php          # Alumni directory listing & filtering API
```

---

## 6. Academic Context
- **Institution**: Istanbul University (`ogr.iu.edu.tr`)
- **Faculty**: Faculty of Economics
- **Department**: Management Information Systems (Yönetim Bilişim Sistemleri - MIS)
- **Course**: Software Engineering & Web Backend Development
