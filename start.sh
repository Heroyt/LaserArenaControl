#!/bin/sh

set -e

echo "Entry: $SHELL $0"

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
    if ! pnpm install; then
      # Check for outdated lockfile error and run update if needed
      if grep -q 'ERR_PNPM_OUTDATED_LOCKFILE' pnpm-debug.log 2>/dev/null; then
        echo "pnpm install failed due to outdated lockfile. Running pnpm update..."
        pnpm update
      else
        echo "pnpm install failed. See pnpm-debug.log for details."
        exit 1
      fi
    fi
  fi
  pnpm run build
fi

./bin/console install

# Clear DI, model and info cache
./bin/console cache:clean -dmic

# Prepare some tasks
./bin/console translations:compile
./bin/console regression:update
./bin/console theme:generate

# Cleanup restart.txt if not correctly removed to prevent immediate restart of container
if [ -f ./temp/restart.txt ]; then
  rm -f ./temp/restart.txt
fi

# Start cron service
crond

# Run project
echo 'Starting...'
echo "$PWD"
rr -v
rr serve -c .rr.yaml -p &

while true; do
  if [ -f ./temp/restart.txt ]; then
    echo "Restarting container..."
    rm -f ./temp/restart.txt
    rr stop
    exit 0
  fi
  sleep 5
done