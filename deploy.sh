#!/usr/bin/env bash

# Frontend-only deploy for superadminpage: build the bundle locally,
# sync the compiled assets to the VPS, then reload the app.
# No composer steps: this app ships no PHP dependencies (see composer.json).

# Exit immediately if a command exits with a non-zero status
set -e

# Load .env if present in the same directory as this script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "$SCRIPT_DIR/.env" ]; then
  set -a
  # shellcheck disable=SC1091
  . "$SCRIPT_DIR/.env"
  set +a
fi

# ==========================================
# CONFIGURATION (Read from environment, with defaults)
# ==========================================
VPS_USER="${VPS_USER:-root}"
VPS_HOST="${VPS_HOST:-62.171.172.160}"
VPS_NC_PATH="${VPS_NC_PATH:-/opt/nextcloud-stack/nextcloud}"
APP_NAME="${APP_NAME:-superadminpage}"
CONTAINER_NAME="${CONTAINER_NAME:-nextcloud-app}"

# ==========================================
# VALIDATION
# ==========================================
if [ "$VPS_HOST" = "your-vps-ip" ]; then
  echo "⚠️  Error: Please configure VPS_HOST in your .env file."
  exit 1
fi

# ==========================================
# 1. BUILD FRONTEND ASSETS LOCALLY
# ==========================================
echo "📦 Building frontend assets..."
npm run build

# ==========================================
# 2. RSYNC COMPILED ASSETS TO VPS
# ==========================================
echo "🚀 Syncing frontend files to VPS..."
rsync -avz --delete \
  ./js/ "$VPS_USER@$VPS_HOST:$VPS_NC_PATH/custom_apps/$APP_NAME/js/"

# ==========================================
# 3. DOCKER COMMANDS: SET PERMISSIONS AND RELOAD APP
# ==========================================
echo "🔧 Setting permissions and enabling app inside Docker container..."
ssh "$VPS_USER@$VPS_HOST" "
  # Set ownership of the synced assets to www-data inside the container
  docker exec -u root $CONTAINER_NAME chown -R www-data:www-data /var/www/html/custom_apps/$APP_NAME/js

  # Reload the deployed app without triggering an App Store update.
  docker exec -u www-data $CONTAINER_NAME php occ app:disable $APP_NAME
  docker exec -u www-data $CONTAINER_NAME php occ app:enable $APP_NAME
"

echo "✅ Frontend deployment finished successfully!"
