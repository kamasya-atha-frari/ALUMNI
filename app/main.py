from fastapi import FastAPI
from pydantic import BaseModel
from typing import List, Optional

app = FastAPI(
    title="Alumni Management API",
    description="Backend API for managing university alumni records, directory queries, and profiles.",
    version="0.1.0",
)

class HealthResponse(BaseModel):
    status: str
    service: str
    version: str

class AlumniProfile(BaseModel):
    id: Optional[int] = None
    full_name: str
    graduation_year: int
    department: str
    current_company: Optional[str] = None
    role: Optional[str] = None

@app.get("/", tags=["Root"])
def read_root():
    return {
        "message": "Welcome to the Alumni Management API",
        "documentation": "/docs",
        "health": "/health",
    }

@app.get("/health", response_model=HealthResponse, tags=["Health"])
def health_check():
    return HealthResponse(
        status="healthy",
        service="alumni-backend",
        version="0.1.0",
    )

@app.get("/api/v1/alumni", response_model=List[AlumniProfile], tags=["Alumni"])
def get_sample_alumni():
    return [
        AlumniProfile(
            id=1,
            full_name="Jane Doe",
            graduation_year=2023,
            department="Computer Engineering",
            current_company="Tech Corp",
            role="Software Engineer",
        )
    ]
