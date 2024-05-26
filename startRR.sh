#!/bin/bash

# Update project
if [ "$LAC_VERSION" != "dev" ]; then
  git fetch --all --tags
  if [[ "$LAC_VERSION" == "stable" ]]; then
    git pull --recurse-submodules
  else
    git checkout "v${LAC_VERSION}" -b "stable"
    git -C src/GameModels fetch --all --tags
    git -C src/GameModels checkout "v${LAC_MODELS_VERSION}" -b "stable"
  fi

  composer update

  php install.php
else
  echo "Skipping git fetch for dev"
fi

composer dump-autoload

if [ ! -f "package-lock.json" ]; then
  npm update
else
  npm install
fi

# Build assets
npm run build

# Clear DI, model and info cache
./bin/console cache:clean -dmi

# Prepare some tasks
./bin/console translations:compile
./bin/console regression:update

# Run project
echo 'Starting...'
echo $PWD
rr -v
cron &
bash
rr serve -d -c .rr.yaml
echo $?
