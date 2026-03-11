# BATS Tests for Laravel Activity Package

This directory contains the BATS (Bash Automated Testing System) test suite for the Laravel Activity package.

**Location:** `tests/bats/` (separated from PHPUnit tests in `tests/Feature/` and `tests/Unit/`)

## 📁 Structure

```bash
tests/bats/
├── README.md                       # This file
├── helpers/
│   └── test_helper.bash            # Shared test utilities and assertions
├── unit/
│   └── package-structure.bats      # Tests for package structure validation
└── integration/
    └── composer-scripts.bats       # Tests for composer scripts and dev-tools integration
```

## 🎯 Test Coverage

### Unit Tests

- **package-structure.bats**
  - Package metadata (composer.json validation)
  - Directory structure verification
  - Source files existence
  - Autoload configuration
  - Service provider registration
  - Dependencies validation
  - Configuration files presence

### Integration Tests

- **composer-scripts.bats**
  - Composer script availability (test, quality, analyse, cs, rector)
  - Vendor binaries existence (phpunit, phpstan, pint, rector, phpmd)
  - PHPUnit test suites configuration
  - Dev-tools integration (zairakai/laravel-dev-tools)
  - Configuration files installation
  - Git integration (hooks, repository)
  - Package validation (composer validate, autoload dump)

## 🚀 Running Tests

### Prerequisites

Install BATS:

```bash
# macOS
brew install bats-core

# Linux (Ubuntu/Debian)
sudo apt-get install bats

# Or via npm globally
npm install -g bats
```

### All BATS Tests

```bash
# Run all BATS tests
bats tests/bats/unit/*.bats tests/bats/integration/*.bats

# Or use find
find tests/bats -name "*.bats" -exec bats {} \;
```

### Specific Test Suites

```bash
# Unit tests only
bats tests/bats/unit/package-structure.bats

# Integration tests only
bats tests/bats/integration/composer-scripts.bats
```

### With Verbose Output

```bash
bats tests/bats/unit/package-structure.bats --verbose
```

### Filter Specific Tests

```bash
bats tests/bats/unit/package-structure.bats --filter "composer.json"
```

## 📝 Writing New Tests

### Test File Template

```bash
#!/usr/bin/env bats
#
# Tests for feature-name
#

# Load test helpers
load '../helpers/test_helper'

setup() {
    setup_test_env
}

teardown() {
    teardown_test_env
}

@test "descriptive test name" {
    # Arrange
    local input="test"

    # Act
    result=$(some_function "$input")

    # Assert
    [ "$result" = "expected" ]
}
```

### Available Assertions

From `test_helper.bash`:

```bash
# File/Directory
assert_file_exists "/path/to/file"
assert_file_not_exists "/path/to/file"
assert_dir_exists "/path/to/dir"

# Output
assert_output_contains "needle"
assert_output_equals "exact match"

# Exit Status
assert_success                       # $status -eq 0
assert_failure                       # $status -ne 0

# Standard BATS
[ "$status" -eq 0 ]                 # Command succeeded
[ "$status" -eq 1 ]                 # Command failed
[[ "$output" =~ "pattern" ]]        # Output matches regex
```

### Helper Functions

```bash
# Test Environment
setup_test_env()                    # Initialize test environment
teardown_test_env()                 # Cleanup after test

# Test Utilities
create_test_file "/path" "content"  # Create file in test temp dir
command_exists "composer"           # Check if command is available

# Variables
$TEST_TEMP_DIR                      # Temporary directory for this test
$PROJECT_ROOT                       # Package root directory
$VENDOR_BIN                         # vendor/bin/ directory
$COMPOSER_BIN                       # Composer binary path
```

## ✅ Best Practices

1. **One Assertion Per Test** (when possible)

   ```bash
   # Good
   @test "composer.json exists" {
       assert_file_exists "${PROJECT_ROOT}/composer.json"
   }

   # Avoid
   @test "package files exist" {
       assert_file_exists "composer.json"
       assert_file_exists "phpunit.xml"
       assert_file_exists "README.md"
   }
   ```

2. **Descriptive Test Names**

   ```bash
   # Good
   @test "PSR-4 autoload is configured in composer.json"

   # Avoid
   @test "test autoload"
   ```

3. **Arrange-Act-Assert Pattern**

   ```bash
   @test "clear test structure" {
       # Arrange - Setup
       local package_name="zairakai/laravel-activity"

       # Act - Execute
       run php -r "echo json_decode(file_get_contents('composer.json'))->name;"

       # Assert - Verify
       [ "$output" = "$package_name" ]
   }
   ```

4. **Use Helper Functions**

   ```bash
   # Instead of:
   [ -f "${PROJECT_ROOT}/composer.json" ]

   # Use:
   assert_file_exists "${PROJECT_ROOT}/composer.json"
   ```

## 🎨 Test Organization

### Unit Test Strategy

Focus on **package structure** and **configuration**:

- Fast execution (< 100ms per test)
- No external dependencies
- Validate metadata and file presence
- Check configuration validity

**Example:**

```bash
@test "package type is library" {
    run php -r "echo json_decode(file_get_contents('composer.json'))->type;"
    [ "$output" = "library" ]
}
```

### Integration Test Strategy

Focus on **package integration** and **tooling**:

- Test composer scripts execution
- Validate vendor binaries
- Check dev-tools integration
- Verify real filesystem operations

**Example:**

```bash
@test "composer validate succeeds" {
    run composer validate --no-check-publish --no-check-all
    assert_success
}
```

## 🔍 Debugging Tests

### Verbose Output

```bash
bats tests/bats/unit/package-structure.bats --verbose
```

### Print Debug Info

```bash
@test "my test" {
    echo "Debug: variable=$variable" >&3
    run some_command
    echo "Output: $output" >&3
}
```

### Run Single Test

```bash
bats tests/bats/unit/package-structure.bats --filter "composer.json"
```

## 🐛 Common Issues

### BATS Not Found

Install BATS:

```bash
# macOS
brew install bats-core

# Linux
sudo apt-get install bats

# npm
npm install -g bats
```

### Tests Pass Locally But Fail in CI

- Verify BATS is installed in CI environment
- Check $PROJECT_ROOT path resolution
- Ensure composer dependencies are installed

### Permission Denied Errors

Make test files executable:

```bash
chmod +x tests/bats/**/*.bats
chmod +x tests/bats/helpers/*.bash
```

## 📚 Resources

- [BATS Documentation](https://bats-core.readthedocs.io/)
- [BATS GitHub](https://github.com/bats-core/bats-core)
- [Laravel Package Development](https://laravel.com/docs/packages)
- [Spatie Laravel ActivityLog](https://spatie.be/docs/laravel-activitylog)

## 🤝 Contributing

When adding new functionality:

1. **Write BATS tests** for package structure changes
2. **Add to appropriate suite** (unit vs integration)
3. **Update this README** if adding new test files
4. **Run full suite** before committing
5. **Ensure all tests pass**

---

**Last Updated:** January 2025
**Package:** zairakai/laravel-activity
**Test Framework:** BATS (Bash Automated Testing System)
