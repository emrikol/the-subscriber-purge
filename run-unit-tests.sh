#!/bin/bash

# Unit Test Runner with Docker
# Runs unit tests in a PHP container (no WordPress/MySQL needed)
# Portable and team-friendly
#
# Usage:
#   ./run-unit-tests.sh           # Run tests normally
#   ./run-unit-tests.sh --debug   # Show test names and timing info
#   ./run-unit-tests.sh --testdox # Show test names in readable format

set -euo pipefail

# Configuration
PHP_VERSION="${PHP_VERSION:-8.4}"
IMAGE_NAME="tsp-unit-test-php${PHP_VERSION}"
CONTAINER_NAME="tsp-unit-tests-$$"
DEBUG_MODE=""
TESTDOX_MODE=""

# Parse arguments
while [[ $# -gt 0 ]]; do
	case $1 in
		--debug)
			DEBUG_MODE="--debug"
			shift
			;;
		--testdox)
			TESTDOX_MODE="--testdox"
			shift
			;;
		*)
			echo "Unknown option: $1"
			echo "Usage: $0 [--debug] [--testdox]"
			exit 1
			;;
	esac
done

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "🧪 Running Unit Tests in Docker..."
echo "===================================="
echo "📦 PHP Version: $PHP_VERSION"
if [ -n "$DEBUG_MODE" ]; then
	echo "🔍 Debug mode: ON (showing test names and timing)"
fi
if [ -n "$TESTDOX_MODE" ]; then
	echo "📝 Testdox mode: ON (readable test names)"
fi
echo ""

# Check if Docker is running
if ! docker info &> /dev/null; then
	echo -e "${RED}❌ Docker is not running${NC}"
	echo "Please start Docker and try again"
	exit 1
fi

# Reset coverage output on the host so the container can write fresh reports.
rm -rf tests/coverage
mkdir -p tests/coverage

# Cleanup function
cleanup() {
	echo ""
	echo "🧹 Cleaning up..."
	docker rm -f "$CONTAINER_NAME" 2>/dev/null || true
}

trap cleanup EXIT

# Build a simple PHP image with composer and xdebug if it doesn't exist
echo "🔨 Preparing Docker image with Xdebug..."
docker build -t "$IMAGE_NAME" -f - . <<EOF
FROM php:${PHP_VERSION}-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \\
    git \\
    unzip \\
    zip \\
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP extensions (minimal for unit tests)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install Xdebug for code coverage
RUN pecl install xdebug \\
    && docker-php-ext-enable xdebug

# Configure Xdebug for coverage only (not debugging)
RUN echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Set working directory
WORKDIR /app

CMD ["bash"]
EOF

echo ""
echo "🏃 Running unit tests..."
echo ""

# Run PHPUnit in container
docker run --rm \
    --name "$CONTAINER_NAME" \
    -v "$(pwd):/app" \
    -w /app \
	-e DEBUG_MODE="$DEBUG_MODE" \
	-e TESTDOX_MODE="$TESTDOX_MODE" \
	"$IMAGE_NAME" \
	bash -c '
        set -e
        # Install dependencies if needed
        if [ ! -d "vendor" ]; then
            echo "📦 Installing Composer dependencies..."
            composer install --no-interaction --prefer-dist
            echo ""
        fi

        # Run unit tests with coverage
        echo "📊 Running tests with code coverage..."

        rm -rf tests/coverage
        mkdir -p tests/coverage/html

        # Build PHPUnit command with optional flags
        PHPUNIT_CMD="./vendor/bin/phpunit -c tests/phpunit.xml.dist --testsuite=unit --coverage-php tests/coverage/coverage.php --colors=always"

        # Add debug/testdox flags if enabled
        if [ -n "$DEBUG_MODE" ]; then
            # Add debug flag and wrap output to add timing
            PHPUNIT_CMD="$PHPUNIT_CMD --debug"

            # Wrap PHPUnit output to add timing using inline bash
            set +e
            eval $PHPUNIT_CMD 2>&1 | while IFS= read -r line; do
                if [[ "$line" =~ started ]]; then
                    START_MS=$(date +%s%3N)
                    echo "$line"
                elif [[ "$line" =~ ended ]]; then
                    END_MS=$(date +%s%3N)
                    DURATION=$((END_MS - START_MS))
                    echo "${line/ended/ended ($DURATION ms)}"
                    START_MS=0
                else
                    echo "$line"
                fi
            done
            EXIT_CODE=$?
            set -e
        else
            if [ -n "$TESTDOX_MODE" ]; then
                PHPUNIT_CMD="$PHPUNIT_CMD --testdox"
            fi
            # Execute the command
            set +e
            eval $PHPUNIT_CMD
            EXIT_CODE=$?
            set -e
        fi

        echo ""
        # Generate HTML, Clover, and text coverage from the serialized coverage data.
        set +e
        php -d xdebug.mode=coverage tests/render-coverage.php
        GEN_EXIT=$?
        set -e

        if [ $GEN_EXIT -ne 0 ]; then
            echo "⚠️  Coverage report generation failed (exit $GEN_EXIT)."
        else
            echo ""
            echo "📈 Coverage reports generated:"
            echo "   - HTML Report: tests/coverage/html/index.html"
            echo "   - Clover XML:  tests/coverage/coverage.xml"
        fi

        exit $EXIT_CODE
    '

EXIT_CODE=$?

echo ""
if [ $EXIT_CODE -eq 0 ]; then
	echo -e "${GREEN}✅ All unit tests passed!${NC}"
else
	echo -e "${RED}❌ Some unit tests failed${NC}"
fi

exit $EXIT_CODE
