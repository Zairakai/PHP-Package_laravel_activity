#!/usr/bin/env bats
#
# Integration Tests for Composer Scripts
#
# Tests:
# - Composer script execution
# - Quality checks
# - Testing commands
# - Dev tools integration
#

# Load test helpers
load '../helpers/test_helper'

setup() {
    setup_test_env
    cd "$PROJECT_ROOT" || exit 1
}

teardown() {
    teardown_test_env
}

# ============================================================================
# Composer Scripts Availability Tests
# ============================================================================

@test "composer test script exists" {
    run php -r "
        \$json = json_decode(file_get_contents('composer.json'), true);
        echo isset(\$json['scripts']['test']) ? 'yes' : 'no';
    "
    [ "$output" = "yes" ]
}

@test "composer quality script exists" {
    run php -r "
        \$json = json_decode(file_get_contents('composer.json'), true);
        echo isset(\$json['scripts']['quality']) ? 'yes' : 'no';
    "
    [ "$output" = "yes" ]
}

@test "composer analyse script exists" {
    run php -r "
        \$json = json_decode(file_get_contents('composer.json'), true);
        echo isset(\$json['scripts']['analyse']) ? 'yes' : 'no';
    "
    [ "$output" = "yes" ]
}

@test "composer cs script exists" {
    run php -r "
        \$json = json_decode(file_get_contents('composer.json'), true);
        echo isset(\$json['scripts']['cs']) ? 'yes' : 'no';
    "
    [ "$output" = "yes" ]
}

@test "composer rector script exists" {
    run php -r "
        \$json = json_decode(file_get_contents('composer.json'), true);
        echo isset(\$json['scripts']['rector']) ? 'yes' : 'no';
    "
    [ "$output" = "yes" ]
}

# ============================================================================
# Vendor Binaries Tests
# ============================================================================

@test "phpunit binary exists in vendor/bin" {
    assert_file_exists "${VENDOR_BIN}/phpunit"
}

@test "phpstan binary exists in vendor/bin" {
    assert_file_exists "${VENDOR_BIN}/phpstan"
}

@test "pint binary exists in vendor/bin" {
    assert_file_exists "${VENDOR_BIN}/pint"
}

@test "rector binary exists in vendor/bin" {
    assert_file_exists "${VENDOR_BIN}/rector"
}


# ============================================================================
# PHPUnit Tests
# ============================================================================

@test "phpunit can list available test suites" {
    run "${VENDOR_BIN}/phpunit" --list-suites
    assert_success
    assert_output_contains "Unit"
    assert_output_contains "Feature"
}

@test "phpunit reports correct number of test files" {
    # Should have at least the FluentApiTest
    run bash -c "find tests -name '*Test.php' | wc -l"
    [ "$output" -ge 1 ]
}

# ============================================================================
# Dev Tools Integration Tests
# ============================================================================

@test "zairakai/laravel-dev-tools is installed" {
    assert_dir_exists "${PROJECT_ROOT}/vendor/zairakai/laravel-dev-tools"
}

@test "dev-tools scripts directory exists" {
    assert_dir_exists "${PROJECT_ROOT}/vendor/zairakai/laravel-dev-tools/scripts"
}

@test "setup-package.sh script exists" {
    assert_file_exists "${PROJECT_ROOT}/vendor/zairakai/laravel-dev-tools/scripts/setup-package.sh"
}

# ============================================================================
# Configuration Files Installation Tests
# ============================================================================

@test "phpunit.xml is a regular file (not symlink)" {
    # phpunit.xml must be copied, not symlinked (XML relative paths issue)
    [ -f "${PROJECT_ROOT}/phpunit.xml" ]
    [ ! -L "${PROJECT_ROOT}/phpunit.xml" ]
}

@test "Makefile is accessible" {
    # Can be symlink or copy
    [ -f "${PROJECT_ROOT}/Makefile" ]
}



# ============================================================================
# Git Integration Tests
# ============================================================================

@test "git repository exists" {
    assert_dir_exists "${PROJECT_ROOT}/.git"
}

@test "git hooks directory exists" {
    assert_dir_exists "${PROJECT_ROOT}/.git/hooks"
}

# ============================================================================
# Package Validation Tests
# ============================================================================

@test "composer validate succeeds" {
    run composer validate --no-check-publish --no-check-all
    assert_success
}

@test "composer autoload dump succeeds" {
    run composer dump-autoload --optimize
    assert_success
}
