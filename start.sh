#!/bin/bash

echo "Entry: $SHELL $0"

# Update project
git fetch --all --tags
if [ "$LAC_VERSION" = "stable" ]; then
  git switch stable
  git pull --recurse-submodules origin stable

  composer update
  php install.php
elif [ "$LAC_VERSION" != "dev" ]; then
  git checkout "v${LAC_VERSION}" -b "stable"
  git -C src/GameModels fetch --all --tags
  git -C src/GameModels checkout "v${LAC_MODELS_VERSION}" -b "stable"

  composer update
  php install.php
else
  echo "Skipping git fetch for dev"
fi

composer preload
composer dump-autoload

# Use PNPM or NPM
source ~/.bashrc
if ! command -v pnpm &> /dev/null
then
  echo "Running with NPM"
  # Update / Install js libraries
  if [ ! -f "package-lock.json" ]
  then
    npm update
  else
    npm install
  fi

  # Build assets
  npm run build
else
  echo "Running with PNPM"
  # Update / Install js libraries
  if [ ! -f "pnpm-lock.yaml" ]
  then
    pnpm update
  else
    pnpm install
  fi

  # Build assets
  pnpm run build
fi

# Clear DI, model and info cache
./bin/console cache:clean -dmi

# Prepare some tasks
./bin/console translations:compile
./bin/console regression:update
./bin/console theme:generate

# Run project
echo 'Starting...'
echo "$PWD"
rr -v
cron &
rr serve -c .rr.yaml