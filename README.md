# Alumni Management System (`alumni`)

A modern, containerized web backend designed to manage university alumni networks, professional trajectories, directory queries, and institutional engagement.

---

## 1. Project Overview (What the system is)

The **Alumni Management System** is a centralized backend service for tracking, connecting, and engaging university graduates. It serves as the digital backbone for universities (such as Istanbul University) to maintain an accurate directory of alumni, record career milestones, coordinate mentorship opportunities, and foster alumni networking.

The system is designed with a service-oriented, API-first architecture, allowing integration with university portals, mobile applications, and administrative dashboards.

---

## 2. System Scope & Functional Requirements (What it has to do)

The core responsibilities and feature milestones of the `alumni` system include:

1. **Alumni Profile & Identity Management**:
   - Store and manage verified graduate records (Student ID, Full Name, Graduation Year, Degree, Department/Faculty).
   - Allow alumni to update their current contact details, public profile, and social/professional links (LinkedIn, GitHub).

2. **Career & Professional History Tracking**:
   - Record current employment (Company, Title, Industry, Location).
   - Track chronological career milestones and career transitions post-graduation.

3. **Search & Directory Services**:
   - Filter and query alumni by graduation year, department, company, industry, or geographic location.
   - Fast, paginated directory listing with secure field-level visibility controls (e.g., private email vs. public profile).

4. **Mentorship & Networking Modules**:
   - Allow alumni to flag availability as mentors for current undergraduates.
   - Facilitate direct contact requests or networking inquiries between members.

5. **Events & Announcements Coordination**:
   - Publish institutional notices, alumni reunions, and career seminars.
   - Enable RSVP tracking for registered alumni.

---

## 3. Technology Stack & Decision Rationale

| Layer | Technology | Selection Rationale | Limitation / Weakness |
| :--- | :--- | :--- | :--- |
| **Backend Language & Framework** | **Python 3.11+ / FastAPI** | - **Intuitive Syntax**: Clean, human-readable syntax allows rapid prototyping and transparent codebase maintenance.<br>- **High Concurrency**: Native `async`/`await` support with Starlette provides excellent I/O throughput.<br>- **Automated Documentation**: Native OpenAPI/Swagger generation (`/docs`) makes endpoint testing and contract verification immediate and effortless.<br>- **Strong Typing**: Pydantic models guarantee automatic request parsing and schema validation. | **CPU-Bound Performance**: Python's Global Interpreter Lock (GIL) and interpreted nature result in slower raw computational execution compared to compiled systems like Go or Rust. |
| **Database** | **PostgreSQL 16** | - **Relational Data Integrity**: Alumni profiles, graduation cohorts, faculties, and career histories are naturally relational, demanding strict foreign-key integrity and ACID compliance.<br>- **Rich Querying**: Advanced indexing, complex joins, and JSONB capabilities provide full flexibility for future semi-structured profile attributes.<br>- **Container-Ready**: Minimal configuration required to orchestrate standard container images in Docker Compose. | **Schema Rigidity & Overhead**: Schema modifications require formal migration tracking (e.g., via Alembic), and memory footprint is noticeably higher than lightweight embedded solutions like SQLite. |
| **AI Assistant** | **Antigravity** | - **Context Awareness**: Deep workspace awareness and autonomous terminal execution speed up test cycles and refactoring.<br>- **Zero/Low Cost**: Provides a powerful agentic developer loop without requiring expensive enterprise subscriptions. | **Ecosystem Maturity**: Being a cutting-edge agentic framework, community workarounds and third-party plugin ecosystems are still actively developing. |

---

## 4. How to Run the System

In strict compliance with project guidelines, the entire application stack is orchestrated via a single command:

```bash
docker compose up --build
```

### Prerequisites
- [Docker](https://docs.docker.com/get-docker/) installed on your machine.
- [Docker Compose](https://docs.docker.com/compose/) (v2+).

### Step-by-Step Execution

1. **Clone the repository:**
   ```bash
   git clone https://github.com/<your-username>/alumni.git
   cd alumni
   ```

2. **Launch the services:**
   ```bash
   docker compose up
   ```

3. **Access the application:**
   - **Backend API Root**: `http://localhost:8000/`
   - **Interactive Swagger Documentation**: `http://localhost:8000/docs`
   - **ReDoc Documentation**: `http://localhost:8000/redoc`
   - **Healthcheck Endpoint**: `http://localhost:8000/health`

4. **Tear down services:**
   ```bash
   docker compose down
   ```

---

## 5. Directory Structure

```text
alumni/
├── README.md              # Project documentation, architecture, and instructions
├── docker-compose.yml     # Multi-container orchestration (FastAPI + PostgreSQL)
├── Dockerfile             # Container definition for the backend service
├── requirements.txt       # Python package dependencies
├── .gitignore             # Standard git exclusions (venv, pycache, docker volumes)
├── AGENTS.md              # Agent workflow instructions & specifications
└── app/
    ├── __init__.py        # Python package initialization
    └── main.py            # FastAPI entry point & basic health routes
```

---

## 6. License & Course Attribution
- Developed for University Coursework (`ogr.iu.edu.tr`).
- Public Repository: `github.com/<your-username>/alumni`
