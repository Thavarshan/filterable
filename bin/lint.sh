#!/usr/bin/env bash

# Exit on error
set -e

# Function to check if a composer package is installed
is_composer_package_installed() {
    composer show "$1" >/dev/null 2>&1
}

# Constants
DUSTER_PACKAGE="tightenco/duster"
DUSTER_PATH="vendor/bin/duster"
DIRECTORIES_TO_ANALYSE="src"

# Check if Duster is installed
if ! is_composer_package_installed $DUSTER_PACKAGE; then
    echo "Installing $DUSTER_PACKAGE..."
    composer require --dev $DUSTER_PACKAGE
fi

# Run the Duster lint analysis
echo "Running Duster lint analysis on $DIRECTORIES_TO_ANALYSE..."
$DUSTER_PATH lint $DIRECTORIES_TO_ANALYSE

# Optionally, run the Duster fix command
# echo "Running Duster fix on $DIRECTORIES_TO_ANALYSE..."
# $DUSTER_PATH fix $DIRECTORIES_TO_ANALYSE

# Run PHP lint
find $DIRECTORIES_TO_ANALYSE -type f -name "*.php" -exec php -l {} \;
