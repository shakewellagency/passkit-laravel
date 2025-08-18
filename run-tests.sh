#!/bin/bash

# PassKit Laravel Package Test Runner
echo "🧪 Running PassKit Laravel Package Tests"
echo "========================================"

# Set working directory to package root
cd "$(dirname "$0")"

# Check if we're in Laravel project context
if [ ! -f "../../../vendor/bin/phpunit" ]; then
    echo "❌ Error: Could not find PHPUnit. Make sure you're running this from within a Laravel project."
    exit 1
fi

# Set test environment
export APP_ENV=testing
export DB_CONNECTION=sqlite
export DB_DATABASE=:memory:

echo "📊 Test Configuration:"
echo "  Environment: testing"
echo "  Database: SQLite (in-memory)"
echo "  Test Framework: PHPUnit + Pest"
echo ""

# Run different test suites
echo "🔬 Running Unit Tests..."
../../../vendor/bin/phpunit tests/Unit --testdox --colors=always

echo ""
echo "🚀 Running Feature Tests..."
../../../vendor/bin/phpunit tests/Feature --testdox --colors=always

echo ""
echo "🔗 Running Integration Tests..."
../../../vendor/bin/phpunit tests/Integration --testdox --colors=always

echo ""
echo "✅ Test run completed!"
echo ""
echo "📋 Test Coverage Summary:"
echo "  • Unit Tests: Model logic, service methods, utilities"
echo "  • Feature Tests: API endpoints, controllers, authentication"
echo "  • Integration Tests: End-to-end workflows, data consistency"
echo ""
echo "🎯 To run specific tests:"
echo "  ./run-tests.sh unit     # Unit tests only"
echo "  ./run-tests.sh feature  # Feature tests only"
echo "  ./run-tests.sh models   # Model tests only"
echo "  ./run-tests.sh services # Service tests only"