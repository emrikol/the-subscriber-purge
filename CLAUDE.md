# CLAUDE.md - Project Context for AI Assistants

This document provides context about The Subscriber Purge plugin to help AI assistants understand the codebase and make informed suggestions.

## Project Philosophy

### Core Ethos: KISS (Keep It Simple, Stupid)

**KISS ALWAYS OVERRIDES DRY** - Simplicity is more important than avoiding repetition.

- **Simplicity over cleverness**: If there's a simple solution and a complex one, choose simple
- **Avoid premature optimization**: Build for clarity first, optimize when needed
- **Question complexity**: If a solution feels complicated, step back and find the simpler path
- **One tool, one purpose**: Prefer focused tools over swiss army knives
- **Configuration over code**: Simple config changes beat code modifications
- **Boring technology**: Choose proven, stable solutions over cutting-edge complexity
- **KISS > DRY**: When KISS conflicts with DRY, choose simplicity - duplicate simple code rather than create complex abstractions

Build on 6 core principles:

Design for testability. Code that’s easy to test is usually well-designed.
Shift left. Find and fix issues early. Prevention beats detection.
Risk-based testing. Focus effort where it matters most.
Automation first. Automate repetitive checks, free humans for exploration.
Fast feedback & test economics. Choose the cheapest test that gives confidence.
Signal vs. noise. Every test should be reliable enough that we act on its failures.


---

## Language & Dependencies

### Dependency Management
- **Minimize dependencies** - favor "vanilla" implementations
- **ALWAYS ASK** before installing new dependencies
- When dependencies are necessary, choose well-established, minimal ones
- Document dependency choices and rationale in code comments

### File Loading
- **NO AUTOLOADERS** - Autoloaders hide dependencies and create surprises
- **Explicit require/include only** - Every file dependency should be visible
- **List files at top of main plugin file** - Makes dependencies crystal clear
- **No magic** - If a file is needed, require it explicitly
- **Traditional WordPress approach** - WordPress core and most plugins use explicit requires, not autoloading

---

## Code Quality & Architecture

### Readability & Maintenance
- **Write for novice developers** - assume code will be read by others
- **Favor readable code over clever shortcuts**
- **Comment extensively** about architectural and design decisions
- **Explain obtuse/obfuscated code** when unavoidable due to external constraints

### Code Organization
- **Prefer namespaced functions over classes** - Classes add complexity; use them only when truly needed (e.g., stateful objects, inheritance)
- **Use namespaces for organization** - Group related functions in namespaces, not classes
- **Classes when needed** - Only use classes when you need: state management, instantiation, inheritance, or interfaces
- **KISS > OOP** - Simple namespaced functions are better than complex class hierarchies

### SOLID Principles Focus
- **Single Responsibility Principle**: A class should have one, and only one, reason to change
- **Split by responsibility, not size**: If a class handles business logic + I/O + formatting, it's too large regardless of line count
- **Design for change**: Consider what would cause a component to need modification

### Modularity & Future-Proofing
- **Plugin architecture**: New platform parsers should integrate without core changes
- **Separation of concerns**: Keep parsing, indexing, and search as distinct modules
- **Configuration-driven**: Platform-specific settings in config files, not hardcoded
- **Options storage**: NEVER autoload plugin options. Store all plugin settings in a single non-autoloaded option (array) rather than many individual options; refactor toward this if needed.

###  WordPress Best Practices

**Database Schema Management:**
- **ALWAYS use dbDelta()** for all database table creation and updates
- **NEVER use raw CREATE TABLE queries** - dbDelta handles creating and updating tables safely
- **Abstract database schema** into a dedicated Database class, not in the main plugin file
- **Use WordPress data types** - Follow WordPress schema conventions for column types
- **Proper formatting** - dbDelta requires exact formatting: two spaces after PRIMARY KEY, correct spacing

**WordPress API Usage:**
- **Do things the WordPress way** - Use WordPress functions and APIs for all WordPress operations
- **Never bypass WordPress APIs** - Don't use raw SQL when WordPress has a function for it
- **Follow WordPress coding standards** - Use WordPress VIP coding standards for enterprise-quality code

**WordPress Hooks:**
- **Never use anonymous functions** - Anonymous functions cannot be removed by other plugins/themes
- **Always use named functions** - Use namespaced function names for all hooks
- **Allow unhooking** - Other code should be able to remove or modify our hooks with `remove_action()` and `remove_filter()`

---

## Session & Context Management

### TODO.md Usage
- **Liberal updates** to track progress
- **Session continuity** - TODO.md is the source of truth if context is lost
- **Brain dump/scratchpad** for notes and intermediate thoughts
- **Mark completed tasks** with timestamps

### Context Optimization
- **Monitor context window usage**
- **Flag when sections are complete** and can be cleared
- **Optimize for performance** and reduce context poisoning
- **Clear completed work** to make room for new tasks

---

## Development Workflow

### Task Management
1. **Update TODO.md** before starting work
2. **Mark tasks in-progress** while working
3. **Complete tasks** with completion notes
4. **Flag context optimization opportunities**

### Code Review Principles
- **Self-document architectural decisions**
- **Comment non-obvious implementations**
- **Flag potential refactoring opportunities**
- **Consider modularity for future platforms**

### Decision Making & Recommendations
- **Be opinionated**: When presenting options, research and recommend the best choice
- **Provide evidence**: Back recommendations with research, benchmarks, or best practices
- **Default to expertise**: User relies on Claude's knowledge for technical decisions
- **Present reasoning**: Explain why a particular choice is recommended
- **Research before asking**: Use available tools to gather evidence for recommendations
- **Verify facts with tools**: Use bash commands, web searches, and file reads to verify information
- **Check dates and versions**: Always use `date` command and version checks rather than assumptions

---

## Common Development Commands

### Setup and Dependencies
```bash
# Install dependencies
composer install

# All dependencies are included - no additional setup needed
```

### Code Quality

**IMPORTANT: Code Quality Process**

* Inline comments must end in full-stops, exclamation marks, or question marks
* Use Yoda Condition checks, you must
* Always add return type declrations for functions
* Always add type hinting for function arguments

After making any code changes, always run these commands in order:

**For PHP files:**
1. **Auto-fix violations**: `phpcbf --extensions=php .` or `phpcbf path/to/file.php` (fixes what it can automatically)
2. **Check remaining issues**: `phpcs --extensions=php .` or `phpcs path/to/file.php` (reports remaining violations)

**Note**: The `phpcs` and `phpcbf` commands automatically use the project's `vendor/bin/phpcs` if available in the git repository root.
3. **Fix manually**: Address any remaining PHPCS violations
4. **Never ignore**: Do not add `phpcs:ignore` comments unless the user explicitly requests it

**Example workflow:**
```bash
# Make code changes
# Then run:

# For PHP (direct commands):
phpcbf --extensions=php .               # Auto-fix formatting, spacing, etc. in current directory
phpcs --extensions=php .                # Check for remaining violations in current directory
# Or for specific files:
phpcbf includes/filename.php includes/other-filename.php
phpcs includes/filename.php includes/other-filename.php

# For PHP (using composer scripts):
composer run fix                        # Auto-fix violations
composer run lint                       # Check for remaining violations
composer stan                           # Run PHPStan (level 6) with dead-code detector
composer stan:baseline                  # Generate/update phpstan-baseline.neon if needed
# Note: composer PHPStan scripts already set --memory-limit=1G to avoid OOMs

# Fix any reported issues manually
# Ask user for guidance if unsure how to fix something
```

**Important Notes:**

**For PHP:**
- **Run from current directory**: Use `phpcs --extensions=php .` or `phpcbf --extensions=php .` from the plugin directory
- **Avoid memory issues**: The `--extensions=php` flag prevents scanning large JS files in node_modules that cause memory exhaustion
- `phpcbf` (PHP Code Beautifier and Fixer) automatically fixes many formatting issues
- `phpcs` (PHP_CodeSniffer) reports remaining violations that need manual fixing
- Only add `phpcs:ignore` comments when the user specifically instructs you to do so
- Always ask the user for guidance if you're unsure how to fix a PHPCS violation

### Basic PHP Syntax Checking
```bash
# Check PHP syntax for all files
find . -name "*.php" -exec php -l {} \;

# Check specific core files
php -l repo-plugin-updater.php
php -l includes/class-repo-plugin-updater.php
php -l includes/class-github-api.php
```

---

### Testing and Quality Assurance

**CRITICAL: Test Integrity Philosophy**

**DO THE WORK - DON'T CHEAT THE TESTS**

Tests are a critical part of our development cycle and must be treated with absolute integrity:

- **Fix the code, not the test** - When tests fail, the priority is to fix the underlying implementation
- **Tests reflect requirements** - Failing tests indicate the code doesn't meet specifications
- **No shortcuts or workarounds** - Never modify tests to pass without fixing the actual issue
- **Understand failures** - Investigate why tests fail before making any changes
- **Maintain test quality** - Tests should be as well-written and maintained as production code

**Test-Driven Development Approach:**
1. **Read the failing test** - Understand what behavior is expected
2. **Analyze the gap** - Identify what's missing or broken in the implementation
3. **Fix the implementation** - Make the minimal changes needed to satisfy the test
4. **Verify the fix** - Ensure tests pass for the right reasons
5. **Refactor if needed** - Improve code quality while keeping tests green

**When Tests Fail:**
- **Never ignore failing tests** - All tests must pass before considering work complete
- **Don't disable or skip tests** - Address the root cause instead
- **Don't modify test expectations** - unless requirements have genuinely changed
- **Document any test changes** - Explain why test modifications were necessary

### **CRITICAL: Unit Test vs Integration Test Guidelines**

**⚠️ When building unit tests DO NOT accidentally create integration tests or E2E tests by default**

**Unit Test Industry Standards:**
- **Fast execution** - Each unit test should complete in <100ms
- **No external dependencies** - Mock all HTTP requests, file system operations, databases
- **No network calls** - Never make real API calls or HTTP requests
- **No real credentials** - Use mock tokens, fake API keys only
- **Test one thing** - Focus on a single method/class behavior
- **Deterministic** - Same input always produces same output
- **Isolated** - Tests can run in any order without affecting each other

**What NOT to do in Unit Tests:**
- ❌ Real GitHub API calls - Even with valid tokens
- ❌ `wp_remote_request()` without mocking - Makes real HTTP requests
- ❌ File downloads or ZIP extraction - Use mock data instead
- ❌ Database writes without fixtures - Use WordPress test doubles
- ❌ `sleep()` or time-dependent operations - Mock time if needed

**Proper Unit Test Structure:**
```php
// ✅ GOOD - Tests method behavior in isolation
public function testConfigurationValidation(): void {
    $result = $validator->validate_config(['key' => 'value']);
    $this->assertTrue($result['success']);
}

// ❌ BAD - Triggers full workflow (integration test)
public function testUpdateProcess(): void {
    $result = $updater->run_update(); // DON'T DO THIS
}
```

**Three-Tier Test Strategy:**
1. **Unit Tests** (`tests/unit/`) - Fast, mocked, isolated behavior testing (HIGH coverage >80%)
2. **Integration Tests** (`tests/integration/`) - Real WordPress + MySQL, mocked HTTP by default (MEDIUM coverage)
3. **API Integration Tests** (`tests/integration/` with `@group external-http`) - Real API calls, skipped by default (LOW coverage)

**Integration Test Philosophy:**
- Integration tests verify components work together with real WordPress + MySQL
- Most integration tests should mock HTTP requests for speed and reliability
- Real API calls should be marked with `@group external-http` and skipped by default
- LOW code coverage for API tests is EXPECTED and CORRECT (they provide confidence, not coverage)
- Code coverage comes from unit tests, not integration/API tests
- Integration tests provide confidence that components integrate properly
- See `integration-tests.md` for complete guide

**Test Organization:**
- **Unit tests run by default** - `./vendor/bin/phpunit` or `./run-tests.sh`
- **Integration tests excluded by default** - Use `--group=integration` to run
- **API tests excluded by default** - Use `--group=external-http` to run real API calls
- **Use `@group` annotations** to organize tests:
  - `@group integration` - Integration tests (WordPress + MySQL)
  - `@group external-http` - Tests that make real HTTP/API calls (skipped by default)
  - `@group slow` - Tests that take >1 second to run

```bash
# Run PHP unit tests using composer (recommended):
composer test

# Run tests with coverage:
composer test:coverage

# Run UNIT tests (fast, default):
./run-tests.sh

# Run unit tests through Docker:
composer test:docker

# Run INTEGRATION tests (slower, with mocked HTTP):
./run-integration-tests.sh

# Run API tests (slowest, with real HTTP calls):
phpunit --configuration tests/phpunit.xml.dist --group=external-http

# Run specific test types:
phpunit --configuration tests/phpunit.xml.dist                          # Unit tests only
phpunit --configuration tests/phpunit.xml.dist --testsuite=unit         # Unit tests only
phpunit --configuration tests/phpunit.xml.dist --testsuite=integration  # Integration tests
phpunit --configuration tests/phpunit.xml.dist --group=integration      # Integration tests
phpunit --configuration tests/phpunit.xml.dist --group=external-http    # API tests (real HTTP)
```

**CRITICAL: Integration Test Output Issues**

Integration tests that call `wp_send_json_*()` functions will output JSON to stdout, which can cause PHPUnit to fail or exit early. This happens because:

1. Real WordPress is loaded in integration tests
2. `wp_send_json_success()` outputs JSON then calls `wp_die()`
3. The WordPress test framework converts `wp_die()` to a `WPAjaxDieContinueException`
4. BUT the JSON has already been output to stdout, which corrupts PHPUnit's output

**Best Practices:**
- **Always use testable wrapper classes** for AJAX tests that override `wp_send_json_*()` to throw exceptions instead
- **Never call real WordPress AJAX functions directly** in integration tests
- **Check test output** - if you see JSON like `{"success":true...}` in the test output, a test is leaking output
- **Fix leaking tests immediately** - they will cause PHPUnit to exit early and hide other test failures

---

## Plugin Architecture

### Overview

**The Subscriber Purge** is a WordPress plugin that purges WordPress subscriber accounts that have no comments after 30 days of registration, with emphasis on security, performance, and simplicity.
