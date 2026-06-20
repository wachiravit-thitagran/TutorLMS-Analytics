#!/usr/bin/env bash

set -e

if [ -z "$1" ]; then
  echo "Usage: $0 <new-version>"
  echo "Example: $0 1.0.2"
  exit 1
fi

NEW_VERSION=$1
echo "Bumping version to $NEW_VERSION..."

# Update main plugin file (header comment and constant)
sed -i '' -E "s/Version: [0-9A-Za-z._-]+/Version: ${NEW_VERSION}/" tutorlms-analytics.php
sed -i '' -E "s/define\( 'TUTORLMS_ANALYTICS_VERSION', '[0-9A-Za-z._-]+' \);/define( 'TUTORLMS_ANALYTICS_VERSION', '${NEW_VERSION}' );/" tutorlms-analytics.php

# Update tests bootstrap (constant)
sed -i '' -E "s/define\( 'TUTORLMS_ANALYTICS_VERSION', '[0-9A-Za-z._-]+' \);/define( 'TUTORLMS_ANALYTICS_VERSION', '${NEW_VERSION}' );/" tests/bootstrap.php

echo "✅ Version bumped successfully to $NEW_VERSION in all files."
echo ""
echo "To commit and push this release, run:"
echo "  git commit -am \"chore(release): bump version to $NEW_VERSION\""
echo "  git push"
