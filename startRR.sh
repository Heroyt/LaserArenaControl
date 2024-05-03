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
else
  echo "Skipping git fetch for dev"
fi

composer update
composer dump-autoload

php install.php

if [ ! -f "package-lock.json" ]; then
  npm update
else
  npm install
fi

# Build assets
npm run build

# Prepare some tasks
./bin/console translations:compile
./bin/console regression:update

# Clear DI, model and info cache
./bin/console cache:clean -dmi


# Run project
echo 'Starting...'
echo $PWD
rr -v
cron & rr serve -d -p -c .rr.yaml
