# Architecture Decision Records

An Architecture Decision Record captures one significant choice: the context that
forced it, the option taken, and the consequences. Records are immutable once
accepted. A later decision supersedes an earlier one with a new record rather
than an edit.

Write a record only for a decision that is expensive to reverse or shapes work
across the codebase. Feature requests and localized fixes belong in issues.

## Format

Records use [MADR](https://adr.github.io/madr/). Copy `template.md`, take the next
number, and open it as `Proposed`. A maintainer moves it to `Accepted`.

## Index

| ADR | Title | Status | Tracking |
|-----|-------|--------|----------|
| [0001](0001-rest-api-architecture.md) | REST API architecture and framework | Proposed | #7300 |
| [0002](0002-multi-database-support.md) | Multi-database support strategy | Proposed | #7301 |
