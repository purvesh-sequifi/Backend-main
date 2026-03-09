#!/bin/bash
# =============================================================================
# Python Virtual Environment Setup Script
# =============================================================================
# This script creates an isolated Python virtual environment for py-scripts
# and installs all dependencies from requirements.txt
#
# Usage: sudo bash scripts/install-python-deps.sh [deploy_dir]
#   - deploy_dir: Optional. Defaults to script's parent directory
#
# Benefits of Virtual Environment:
#   - Isolated dependencies per deployment
#   - Safe rollbacks (each deployment has its own packages)
#   - No conflicts with system Python packages
# =============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🐍 Starting Python Virtual Environment Setup...${NC}"

# Determine project root directory
if [ -n "$1" ]; then
    PROJECT_ROOT="$1"
else
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
fi

PY_SCRIPTS_DIR="${PROJECT_ROOT}/py-scripts"
VENV_PATH="${PY_SCRIPTS_DIR}/.venv"
REQUIREMENTS_FILE="${PY_SCRIPTS_DIR}/requirements.txt"

echo "📁 Project Root: ${PROJECT_ROOT}"
echo "📁 Python Scripts Dir: ${PY_SCRIPTS_DIR}"
echo "📁 Virtual Env Path: ${VENV_PATH}"

# Verify py-scripts directory exists
if [ ! -d "$PY_SCRIPTS_DIR" ]; then
    echo -e "${RED}❌ Error: py-scripts directory not found at ${PY_SCRIPTS_DIR}${NC}"
    exit 1
fi

# Verify requirements.txt exists
if [ ! -f "$REQUIREMENTS_FILE" ]; then
    echo -e "${RED}❌ Error: requirements.txt not found at ${REQUIREMENTS_FILE}${NC}"
    exit 1
fi

# Install Python3 and venv if not present
echo -e "${YELLOW}📦 Ensuring Python3 and venv are installed...${NC}"
apt-get update -qq
apt-get install -y python3 python3-pip python3-venv python3-dev

# Remove existing venv if it exists (clean install)
if [ -d "$VENV_PATH" ]; then
    echo -e "${YELLOW}🗑️  Removing existing virtual environment...${NC}"
    rm -rf "$VENV_PATH"
fi

# Create virtual environment
echo -e "${YELLOW}🔧 Creating virtual environment...${NC}"
python3 -m venv "$VENV_PATH"

# Activate and install dependencies
echo -e "${YELLOW}📥 Installing dependencies from requirements.txt...${NC}"
source "${VENV_PATH}/bin/activate"

# Upgrade pip first
pip install --upgrade pip

# Install requirements
pip install -r "$REQUIREMENTS_FILE"

# Verify installation
echo -e "${YELLOW}🔍 Verifying installed packages...${NC}"
pip list

# Set proper permissions
echo -e "${YELLOW}🔐 Setting permissions...${NC}"
chown -R www-data:www-data "$VENV_PATH" 2>/dev/null || true
chmod -R 755 "$VENV_PATH"

# Deactivate
deactivate

echo ""
echo -e "${GREEN}✅ Python Virtual Environment Setup Complete!${NC}"
echo -e "${GREEN}   Virtual environment location: ${VENV_PATH}${NC}"
echo -e "${GREEN}   Python executable: ${VENV_PATH}/bin/python${NC}"
echo ""

