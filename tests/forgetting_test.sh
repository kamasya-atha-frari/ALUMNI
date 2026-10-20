#!/usr/bin/env bash
set -e

# ==============================================================================
# AlumniNexus - "The Forgetting Test" Verification Script
# Classroom Requirement ①:
#   1. Create a record in the database
#   2. Run `docker compose down` (stops and removes containers)
#   3. Run `docker compose up -d` (recreates containers)
#   4. Check the record to prove data persistence across container restarts
# ==============================================================================

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[1;34m'
NC='\033[0m' # No Color

APP_URL="${APP_URL:-http://localhost:8080}"
RAND_SUFFIX=$(date +%s)
TEST_NAME="Forgetting Test Student ${RAND_SUFFIX}"
TEST_EMAIL="forgetting_${RAND_SUFFIX}@cv.edu.tr"
TEST_STUDENT_ID="FT${RAND_SUFFIX}"

echo -e "${BLUE}======================================================================${NC}"
echo -e "${BLUE}        AlumniNexus • The Forgetting Test Protocol                    ${NC}"
echo -e "${BLUE}======================================================================${NC}"

# Step 0: Ensure containers are running
echo -e "\n${YELLOW}Step 0: Ensuring Docker Compose services are running and healthy...${NC}"
docker compose up -d

echo "Waiting for app service to be responsive at ${APP_URL}..."
MAX_RETRIES=20
COUNT=0
until curl -s "${APP_URL}/api/v1/health" | grep -q "status" || [ $COUNT -eq $MAX_RETRIES ]; do
    sleep 2
    COUNT=$((COUNT+1))
done

if [ $COUNT -eq $MAX_RETRIES ]; then
    echo -e "${RED}✖ Error: Application at ${APP_URL} did not become ready in time.${NC}"
    exit 1
fi
echo -e "${GREEN}✔ Stack is healthy and online.${NC}"

# Step 1: Create a record
echo -e "\n${YELLOW}Step 1: Creating persistent record via POST /api/v1/alumni...${NC}"
CREATE_PAYLOAD=$(cat <<EOF
{
  "full_name": "${TEST_NAME}",
  "email": "${TEST_EMAIL}",
  "student_id": "${TEST_STUDENT_ID}",
  "cohort": 2024,
  "company": "Persistence Labs",
  "role": "Volume Engineer",
  "industry": "Cloud Storage",
  "location": "Istanbul, Turkey",
  "skills": ["Docker", "MySQL", "Volumes"],
  "is_mentor": true
}
EOF
)

CREATE_RES=$(curl -s -X POST "${APP_URL}/api/v1/alumni" \
  -H "Content-Type: application/json" \
  -d "${CREATE_PAYLOAD}")

RECORD_ID=$(echo "${CREATE_RES}" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)

if [ -z "${RECORD_ID}" ]; then
    echo -e "${RED}✖ Failed to create record. Response: ${CREATE_RES}${NC}"
    exit 1
fi

echo -e "${GREEN}✔ Record created successfully with ID: #${RECORD_ID}${NC}"
echo "  Name:  ${TEST_NAME}"
echo "  Email: ${TEST_EMAIL}"

# Step 2: docker compose down
echo -e "\n${YELLOW}Step 2: Executing 'docker compose down' (terminating containers)...${NC}"
docker compose down

# Step 3: docker compose up
echo -e "\n${YELLOW}Step 3: Executing 'docker compose up -d' (recreating containers)...${NC}"
docker compose up -d

echo "Waiting for database and app to re-establish connection..."
COUNT=0
until curl -s "${APP_URL}/api/v1/alumni/${RECORD_ID}" | grep -q "${TEST_EMAIL}" || [ $COUNT -eq $MAX_RETRIES ]; do
    sleep 2
    COUNT=$((COUNT+1))
done

# Step 4: Check the record
echo -e "\n${YELLOW}Step 4: Checking if record #${RECORD_ID} survived container destruction...${NC}"
CHECK_RES=$(curl -s "${APP_URL}/api/v1/alumni/${RECORD_ID}")

if echo "${CHECK_RES}" | grep -q "${TEST_EMAIL}"; then
    echo -e "${GREEN}======================================================================${NC}"
    echo -e "${GREEN}✔ THE FORGETTING TEST PASSED!${NC}"
    echo -e "${GREEN}Record #${RECORD_ID} (${TEST_NAME}) was successfully retrieved from persistent storage!${NC}"
    echo -e "${GREEN}Docker volume 'nexus_mysql_data' successfully preserved all state.${NC}"
    echo -e "${GREEN}======================================================================${NC}"
    exit 0
else
    echo -e "${RED}======================================================================${NC}"
    echo -e "${RED}✖ THE FORGETTING TEST FAILED!${NC}"
    echo -e "${RED}Record #${RECORD_ID} was lost after container restart.${NC}"
    echo -e "${RED}Check if named volume 'nexus_mysql_data' is mounted properly in docker-compose.yml.${NC}"
    echo -e "${RED}======================================================================${NC}"
    exit 1
fi
