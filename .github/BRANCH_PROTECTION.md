# Branch protection

The long-lived branches `main`, `develop`, and `1.2.x` are protected by the
organization ruleset **Default Protections**. This file records what that
ruleset enforces today and the changes recommended to close remaining gaps.

Branch protection cannot be set from a pull request. The items under
"Recommended, admin-applied" require a repository or organization admin to
change the ruleset in **Settings > Rules**. This file only documents the target
so the change is reviewable.

## Enforced today

- Pull request required, 1 approval.
- Stale approvals dismissed when new commits are pushed.
- Approval required after the most recent push (no self-approval of your own last commit).
- All review threads must be resolved before merge.
- Squash is the only merge method.
- Force-push and branch deletion are blocked.
- No bypass actors: the rules apply to everyone, admins included.

## Recommended, admin-applied

### 1. Require status checks (highest priority)

The ruleset does not require any status check, so a pull request can merge with
failing CI. Add a **Require status checks to pass** rule with
**Require branches to be up to date before merging** enabled, listing the
current checks:

- `PHP 8.4 Test on ubuntu-latest`
- `pest coverage gate`
- `Analyze (javascript-typescript)`
- `Analyze (python)`

Confirm the exact context names against a recent run before saving; a misspelled
context is treated as never-run and blocks merges until that exact context is
reported.

### 2. Require signed commits

Development already uses GPG signing and DCO sign-off. A **Require signed
commits** rule rejects unsigned commits on the protected branches and matches
the existing workflow.

### 3. Delete head branches on merge

Enable **Automatically delete head branches** in **Settings > General**. Merged
branches are removed automatically, which reduces branch clutter.

### 4. Require review from Code Owners

`.github/CODEOWNERS` routes review of authentication, database, package import,
and CI changes to `@Cacti/cacti-developers` and `@Cacti/cacti-security`. Enabling
**Require review from Code Owners** on the ruleset makes that routing a merge
requirement. Each referenced team must have write access for GitHub to accept it
as an owner.

## Not changed

- 1 required approval suits the current contributor volume; raise it only if the
  team wants two-person review on protected branches.
- Copilot review remains advisory (not run on push, not run on drafts).
