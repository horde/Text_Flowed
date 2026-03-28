# Upgrading Text_Flowed

## Version 3.0.0

### Breaking Changes

None. Version 3.0 is fully backward compatible.

### New Features

#### QuoteMode Enum

Added `QuoteMode` enum to control quote detection behavior:

- **`QuoteMode::EmailConvention`** (default) - Traditional email behavior where any leading `>` is treated as a quote marker
- **`QuoteMode::StrictRfc`** - RFC 3676 strict mode where `>` must be followed by space or another `>` to be a quote

#### Constructor Changes

```php
// Before (still works)
$flowed = new TextFlowed($text);

// New (optional mode parameter)
use Horde\Text\Flowed\QuoteMode;
$flowed = new TextFlowed($text, 'UTF-8', QuoteMode::StrictRfc);
```

#### New Factory Method

```php
// Convenience method for generating format=flowed from plain text
// Automatically uses StrictRfc mode
$flowed = TextFlowed::fromPlainText($text);
```

### When to Use Which Mode

**EmailConvention (default):**
- Processing existing email messages
- Working with format=flowed text
- Maximum compatibility with email clients
- Example: `>text` → quote level 1

**StrictRfc:**
- Generating format=flowed from plain text
- Strict RFC 3676 compliance required
- Handling literal `>` characters in content
- Example: `>text` → space-stuffed to ` >text`

### Migration Guide

**Just use the new class** - existing code works unchanged:

```php
// Existing code - no changes required
$flowed = new TextFlowed($text);
$result = $flowed->toFlowed();
```

**Opt-in to strict RFC mode** when generating from plain text:

```php
// Old way: not RFC-compliant for literal ">"
$flowed = new TextFlowed($text);

// New way: RFC-compliant
$flowed = TextFlowed::fromPlainText($text);
```

### RFC Compliance

Version 3.0 achieves 100% RFC 2646 and RFC 3676 compliance:

- All quote detection modes supported
- Signature separator preservation
- Empty line handling
- pace-stuffing (From, >, space)
- DelSp parameter support
- 998-character limit enforcement

