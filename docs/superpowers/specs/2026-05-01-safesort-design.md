# Design Document: Automatic Sorting Protection (SafeSort)

**Date:** 2026-05-01
**Topic:** Mitigating SQL Injection in Sorting Parameters (GHSA-72vr-jr4v-55vf, GHSA-q9xg_p762_9jm3)

## 1. Problem Statement
Cacti ubiquitously uses `sort_column` and `sort_direction` request variables to build `ORDER BY` clauses in SQL queries. While some sanitization exists, it is insufficient to prevent sophisticated SQL injection attacks, particularly when columns from joined tables are manipulated or when direction parameters are used for comment injection (e.g., `ASC --`).

## 2. Proposed Architecture

### 2.1 Universal Direction Enforcement
The `sort_direction` parameter will be strictly limited to `ASC` or `DESC`. 
- **Affected File:** `lib/html_utility.php` in `update_order_string()`.
- **Logic:** `strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC'`.

### 2.2 Automatic Column Registration
Standard table header functions will act as "registrars" for valid sort columns.
- **Affected File:** `lib/html.php` in `html_header_sort()` and `html_header_sort_checkbox()`.
- **Logic:** Extract keys from the `$header_items` array and store them in `$_SESSION['valid_sort_columns'][$page]`.

### 2.3 Session-Based Allowlist Enforcement
The `update_order_string()` function will verify the requested column against the session allowlist.
- **Affected File:** `lib/html_utility.php`.
- **Logic:** 
  - If `$_SESSION['valid_sort_columns'][$page]` exists and the requested column is present, allow it.
  - If not present or session is empty (e.g., first load), apply strict regex sanitization.

### 2.4 Strict Column Sanitization
For cases where a session allowlist is not yet available, a restrictive regex will be used.
- **Affected File:** `lib/functions.php` (new function `sanitize_sql_column()`).
- **Logic:** `preg_replace('/[^a-zA-Z0-9_.-]/', '', $string)`.

## 3. Implementation Details

### 3.1 New Helpers
- `lib/html_utility.php`: `set_allowed_sort_columns(array $columns)`
- `lib/functions.php`: `sanitize_sql_column(string $column)`

### 3.2 Integration
- `validate_store_request_vars()` will be updated to automatically trigger sort validation if `sort_column` or `sort_direction` are present in the filters.

## 4. Testing Strategy
- **Unit Tests:** Verify `sanitize_sql_column()` with various malicious inputs.
- **Integration Tests:** Use existing GHSA reproduction scripts to confirm the generated SQL is now safe.
- **Regression Tests:** Ensure standard table sorting still works across multiple pages (User Admin, Device Management, etc.).

## 5. Scope & Limitations
This design covers all web-based table sorting. It does not cover manual SQL construction in CLI scripts unless they explicitly call `get_order_string()`.
