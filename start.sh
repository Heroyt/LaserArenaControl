#!/bin/sh

# Update project
if [ "$LAC_VERSION" = "stable" ]; then
  git fetch --all --tags
  git checkout origin/stable
  git pull --recurse-submodules
elif [ "$LAC_VERSION" != "dev" ]; then
  git fetch --all --tags
  git checkout "v${LAC_VERSION}" -b "stable"
  git -C src/GameModels fetch --all --tags
  git -C src/GameModels checkout "v${LAC_MODELS_VERSION}" -b "stable"

  composer update

  php install.php
else
  echo "Skipping git fetch for dev"
fi

composer dump-autoload

if [ ! -f "package-lock.json" ]
then
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
rr serve -c .rr.yaml
echo $?
