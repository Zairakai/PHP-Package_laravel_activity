#!/usr/bin/env bash
#
# BATS Test Helpers for Laravel Activity Package
# Common utilities for BATS tests
#

# Setup test environment
setup_test_env() {
    # Create temporary test directory
    export TEST_TEMP_DIR="${BATS_TEST_TMPDIR}/laravel-activity-test-$$"
    mkdir -p "$TEST_TEMP_DIR"

    # Export project root (tests are in tests/bats/{unit,integration}/)
    export PROJECT_ROOT
    PROJECT_ROOT="$(cd "${BATS_TEST_DIRNAME}/../../.." && pwd)"

    # Export vendor bin directory
    export VENDOR_BIN="${PROJECT_ROOT}/vendor/bin"

    # Export composer binary
    export COMPOSER_BIN
    COMPOSER_BIN="$(command -v composer)"
}

# Teardown test environment
teardown_test_env() {
    if [[ -n "${TEST_TEMP_DIR:-}" ]] && [[ -d "$TEST_TEMP_DIR" ]]; then
        rm -rf "$TEST_TEMP_DIR"
    fi
}

# Assert file exists
assert_file_exists() {
    local file="$1"

    if [[ ! -f "$file" ]]; then
        echo "ASSERTION FAILED: File does not exist: $file" >&2
        return 1
    fi
}

# Assert file does not exist
assert_file_not_exists() {
    local file="$1"

    if [[ -f "$file" ]]; then
        echo "ASSERTION FAILED: File exists but shouldn't: $file" >&2
        return 1
    fi
}

# Assert directory exists
assert_dir_exists() {
    local dir="$1"

    if [[ ! -d "$dir" ]]; then
        echo "ASSERTION FAILED: Directory does not exist: $dir" >&2
        return 1
    fi
}

# Assert string contains
assert_output_contains() {
    local needle="$1"

    if [[ ! "$output" =~ $needle ]]; then
        echo "ASSERTION FAILED: Output does not contain: $needle" >&2
        echo "Actual output: $output" >&2
        return 1
    fi
}

# Assert string equals
assert_output_equals() {
    local expected="$1"

    if [[ "$output" != "$expected" ]]; then
        echo "ASSERTION FAILED: Output mismatch" >&2
        echo "Expected: $expected" >&2
        echo "Actual:   $output" >&2
        return 1
    fi
}

# Assert exit status
assert_success() {
    if [[ "$status" -ne 0 ]]; then
        echo "ASSERTION FAILED: Expected success (0), got $status" >&2
        echo "Output: $output" >&2
        return 1
    fi
}

assert_failure() {
    if [[ "$status" -eq 0 ]]; then
        echo "ASSERTION FAILED: Expected failure (non-zero), got 0" >&2
        echo "Output: $output" >&2
        return 1
    fi
}

# Create test file
create_test_file() {
    local filepath="$1"
    local content="${2:-test content}"

    mkdir -p "$(dirname "$filepath")"
    echo "$content" > "$filepath"
}

# Check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}
