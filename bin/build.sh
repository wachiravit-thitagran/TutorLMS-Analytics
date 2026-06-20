#!/usr/bin/env bash

set -e

PLUGIN_SLUG="tutorlms-analytics"
DIST_DIR="dist"
BUILD_DIR="build/${PLUGIN_SLUG}"

# Clean up previous builds
rm -rf build dist
mkdir -p "$BUILD_DIR"
mkdir -p "$DIST_DIR"

echo "Copying files to build directory..."
rsync -av \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='tests' \
  --exclude='bin' \
  --exclude='build' \
  --exclude='dist' \
  --exclude='vendor' \
  --exclude='.gitignore' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='phpunit.xml.dist' \
  ./ "$BUILD_DIR/"

echo "Creating zip archive..."
cd build
zip -r "../${DIST_DIR}/${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}"
cd ..

echo "Build complete. Artifact is located at ${DIST_DIR}/${PLUGIN_SLUG}.zip"
