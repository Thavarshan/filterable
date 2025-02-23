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
SRC_DIR="./src"

# Check if Duster is installed
if ! is_composer_package_installed $DUSTER_PACKAGE; then
    echo "Installing $DUSTER_PACKAGE..."
    composer require --dev $DUSTER_PACKAGE
fi

# Run the Duster analysis
echo "Running Duster on $SRC_DIR..."
$DUSTER_PATH fix
