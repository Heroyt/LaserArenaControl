#!/bin/sh

set -e

echo "Entry: $SHELL $0"
echo "Version: $LAC_VERSION"

if [ "$LAC_VERSION" != "dev" ]; then
  if [ -n "$SSH_KEY" ] && [ -f "$SSH_KEY" ]; then
    eval "$(ssh-agent -s)" >/dev/null 2>&1
    if ssh-add "$SSH_KEY"; then
      echo "Setting SSH remote URL for GitHub..."
      git remote set-url origin git@github.com:Heroyt/LaserArenaControl.git
      mkdir -p /etc/ssh/ssh_config.d
      echo -e "Host github.com\n    User git\n    Hostname github.com\n    IdentityFile $SSH_KEY\n" > /etc/ssh/ssh_config.d/github.conf
    else
      echo "Failed to add SSH key. Falling back to HTTPS."
      git remote set-url origin https://github.com/Heroyt/LaserArenaControl.git
    fi
  else
    echo "No SSH key provided or file does not exist. Using HTTPS remote URL for GitHub."
    git remote set-url origin https://github.com/Heroyt/LaserArenaControl.git
  fi
fi

# Update project
echo "Fetching latest changes from GitHub..."
echo "Versions: LAC_VERSION=${LAC_VERSION}, LAC_MODELS_VERSION=${LAC_MODELS_VERSION}"
if [ "$LAC_VERSION" = "dev" ]; then
  echo "Skipping git fetch for dev"
else
  git fetch --all --tags
  if [ "$LAC_VERSION" = "stable" ]; then
    git switch stable
    git pull --recurse-submodules origin stable
  elif [ "$LAC_VERSION" = "staging" ]; then
    git switch staging
    git pull --recurse-submodules origin staging
  else
    git checkout "v${LAC_VERSION}" -b "stable"
    git -C src/GameModels fetch --all --tags
    git -C src/GameModels checkout "v${LAC_MODELS_VERSION}" -b "stable"
  fi
fi

if [ ! -f "composer.lock" ]; then
  composer update
else
  composer install
fi

composer preload || true
composer dump-autoload

# Function to run asset compilation in background
build_assets() {
  echo "Building assets in background..."
  # Use PNPM or NPM
  if ! command -v pnpm >/dev/null 2>&1; then
    echo "Running with NPM"
    if [ ! -f "package-lock.json" ]; then
      npm update
    else
      npm install
    fi
    npm run build
  else
    echo "Running with PNPM"
    export CI="true"
    if [ ! -f "pnpm-lock.yaml" ]; then
      pnpm update
    else
      # Try pnpm install with frozen lockfile first
      if ! pnpm install; then
        echo "pnpm install failed (likely due to outdated lockfile). Running pnpm update..."
        pnpm update
      fi
    fi
    pnpm run build
  fi
  echo "Asset build completed"
}

# Function to run optional setup tasks in background
run_optional_setup() {
  echo "Running optional setup tasks in background..."

  # These can run after the server is already serving requests
  ./bin/console translations:compile
  ./bin/console regression:update
  ./bin/console theme:generate

  echo "Optional setup tasks completed"
}

# Start asset compilation in background
build_assets &
ASSET_BUILD_PID=$!

# Run critical setup that must complete before server starts
./bin/console install

# Clear DI, model and info cache - this is critical for proper operation
./bin/console cache:clean -dmic

# Cleanup restart.txt if not correctly removed to prevent immediate restart of container
if [ -f ./temp/restart.txt ]; then
  rm -f ./temp/restart.txt
fi

# Start cron service
crond

# Start optional setup tasks in background
run_optional_setup &
OPTIONAL_SETUP_PID=$!

# Start the server immediately - don't wait for asset build to complete
echo 'Starting server...'
echo "$PWD"
rr -v
rr serve -c .rr.yaml -p &
RR_PID=$!

echo "Server started, waiting for asset build to complete..."

# Wait for asset build to complete, but don't block the server
wait $ASSET_BUILD_PID
echo "Asset build finished"

# Monitor for restart requests
while true; do
  if [ -f ./temp/restart.txt ]; then
    echo "Restarting container..."
    rm -f ./temp/restart.txt

    # Kill background processes
    kill $RR_PID 2>/dev/null || true
    kill $OPTIONAL_SETUP_PID 2>/dev/null || true

    rr stop
    exit 0
  fi
  sleep 5
done
