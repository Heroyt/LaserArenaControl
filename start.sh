#!/bin/bash

# Update project
if [ "$LAC_VERSION" != "dev" ]; then
  git fetch --all --tags
  git checkout "v${LAC_VERSION}" -b "stable"
  git -C src/GameModels fetch --all --tags
  git -C src/GameModels checkout "v${LAC_MODELS_VERSION}" -b "stable"
  php install.php
else
  echo "Skipping git fetch for dev"
fi

# Run project
php index.php event/server & cron & php-fpm
