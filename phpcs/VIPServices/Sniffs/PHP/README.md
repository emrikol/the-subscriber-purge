# StrictTypesSniff

## Overview

The `StrictTypesSniff` enforces that all PHP files contain the `declare(strict_types=1);` declaration immediately after the opening `<?php` tag.

## Purpose

PHP's strict types mode provides better type safety by:
- Preventing automatic type coercion that can lead to unexpected behavior
- Making type errors more explicit and easier to debug
- Improving code reliability and predictability

## What it checks

This sniff validates that:
1. The file starts with a `<?php` tag
2. The next meaningful token (ignoring whitespace and comments) is a `declare` statement
3. The declare statement contains `strict_types=1`

## Examples

### ✅ Valid (passes the sniff)

```php
<?php

declare(strict_types=1);

/**
 * Class documentation
 */
class MyClass {
    // Class content
}
```

### ❌ Invalid (fails the sniff)

```php
<?php

/**
 * Missing strict types declaration
 */
class MyClass {
    // Class content
}
```

```php
<?php

declare(strict_types=0); // Wrong value

class MyClass {
    // Class content
}
```

```php
<?php

declare(encoding='UTF-8'); // Different declare, missing strict_types

class MyClass {
    // Class content
}
```

## Usage

### Command Line

Test a specific file:
```bash
phpcs --standard=.phpcs.xml.dist --sniffs=VIPServices.PHP.StrictTypes path/to/file.php
```

Test multiple files:
```bash
phpcs --standard=.phpcs.xml.dist --sniffs=VIPServices.PHP.StrictTypes includes/ *.php
```

### Configuration

The sniff is automatically included when using the custom `.phpcs.xml.dist` ruleset that references the VIPServices ruleset.

To use this sniff in other projects, either:
1. Copy the sniff file to your custom sniffs directory
2. Reference it in your PHPCS ruleset XML:

```xml
<rule ref="./phpcs/VIPServices/Sniffs/PHP/StrictTypesSniff.php"/>
```

## Error Message

When the sniff detects a missing or incorrect strict types declaration, it will show:

```
ERROR | PHP file must start with declare(strict_types=1); after the opening <?php tag
      | (VIPServices.PHP.StrictTypes.MissingStrictTypes)
```

## Integration

This sniff integrates seamlessly with:
- WordPress VIP Go coding standards
- Existing PHPCS workflows
- CI/CD pipelines
- IDE integrations that support PHPCS