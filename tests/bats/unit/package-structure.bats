#!/usr/bin/env bats
#
# Unit Tests for Laravel Activity Package Structure
#
# Tests:
# - Package metadata (composer.json)
# - Directory structure
# - Autoload configuration
# - Service provider registration
#

# Load test helpers
load '../helpers/test_helper'

setup() {
    setup_test_env
}

teardown() {
    teardown_test_env
}

# ============================================================================
# Package Metadata Tests
# ============================================================================

@test "composer.json exists in package root" {
    assert_file_exists "${PROJECT_ROOT}/composer.json"
}

@test "composer.json is valid JSON" {
    run php -r "json_decode(file_get_contents('${PROJECT_ROOT}/composer.json'));"
    [ "$status" -eq 0 ]
}

@test "package name is zairakai/laravel-activity" {
    run php -r "echo json_decode(file_get_contents('${PROJECT_ROOT}/composer.json'))->name;"
    [ "$output" = "zairakai/laravel-activity" ]
}

@test "package type is library" {
    run php -r "echo json_decode(file_get_contents('${PROJECT_ROOT}/composer.json'))->type;"
    [ "$output" = "library" ]
}

# ============================================================================
# Directory Structure Tests
# ============================================================================

@test "src directory exists" {
    assert_dir_exists "${PROJECT_ROOT}/src"
}

@test "tests directory exists" {
    assert_dir_exists "${PROJECT_ROOT}/tests"
}

@test "tests/Unit directory exists" {
    assert_dir_exists "${PROJECT_ROOT}/tests/Unit"
}

@test "tests/Feature directory exists" {
    assert_dir_exists "${PROJECT_ROOT}/tests/Feature"
}

@test "tests/bats directory exists" {
    assert_dir_exists "${PROJECT_ROOT}/tests/bats"
}

# ============================================================================
# Source Files Tests
# ============================================================================

@test "ServiceProvider exists" {
    assert_file_exists "${PROJECT_ROOT}/src/LaravelActivityServiceProvider.php"
}

@test "TraceActivities trait exists" {
    assert_file_exists "${PROJECT_ROOT}/src/Traits/TraceActivities.php"
}

@test "PivotActivityFluent class exists" {
    assert_file_exists "${PROJECT_ROOT}/src/Support/PivotActivityFluent.php"
}

@test "ActivityQueryFluent class exists" {
    assert_file_exists "${PROJECT_ROOT}/src/Support/ActivityQueryFluent.php"
}

# ============================================================================
# Autoload Configuration Tests
# ============================================================================

@test "PSR-4 autoload is configured" {
    run php -r "
        \$json = json_decode(file_get_contents('${PROJECT_ROOT}/composer.json'), true);
        echo isset(\$json['autoload']['psr-4']) ? 'yes' : 'no';
    "
    [ "$output" = "yes" ]
}

@test "PSR-4 autoload-dev is configured" {
    run php -r "
        \$json = json_decode(file_get_contents('${PROJECT_ROOT}/composer.json'), true);
        echo isset(\$json['autoload-dev']['psr-4']) ? 'yes' : 'no';
    "
    [ "$output" = "yes" ]
}

# ============================================================================
# Service Provider Registration Tests
# ============================================================================

@test "ServiceProvider is registered in composer.json extras" {
    run php -r "
        \$json = json_decode(file_get_contents('${PROJECT_ROOT}/composer.json'), true);
        echo isset(\$json['extra']['laravel']['providers']) ? 'yes' : 'no';
    "
    [ "$output" = "yes" ]
}

@test "ServiceProvider class name is correct" {
    run php -r "
        \$json = json_decode(file_get_contents('${PROJECT_ROOT}/composer.json'), true);
        echo \$json['extra']['laravel']['providers'][0] ?? 'none';
    "
    [ "$output" = "Zairakai\\LaravelActivity\\LaravelActivityServiceProvider" ]
}

# ============================================================================
# Dependencies Tests
# ============================================================================

@test "spatie/laravel-activitylog is required" {
    run php -r "
        \$json = json_decode(file_get_contents('${PROJECT_ROOT}/composer.json'), true);
        echo isset(\$json['require']['spatie/laravel-activitylog']) ? 'yes' : 'no';
    "
    [ "$output" = "yes" ]
}

@test "zairakai/laravel-dev-tools is required in dev" {
    run php -r "
        \$json = json_decode(file_get_contents('${PROJECT_ROOT}/composer.json'), true);
        echo isset(\$json['require-dev']['zairakai/laravel-dev-tools']) ? 'yes' : 'no';
    "
    [ "$output" = "yes" ]
}

# ============================================================================
# Configuration Files Tests
# ============================================================================

@test "phpunit.xml exists" {
    assert_file_exists "${PROJECT_ROOT}/phpunit.xml"
}

@test "phpstan.neon exists" {
    assert_file_exists "${PROJECT_ROOT}/phpstan.neon"
}



@test "Makefile exists" {
    assert_file_exists "${PROJECT_ROOT}/Makefile"
}
