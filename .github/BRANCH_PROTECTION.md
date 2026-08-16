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

Because there is no bypass actor, `github-actions[bot]` cannot push straight to
develop either (confirmed: the daily `Update Contributors` workflow, which
does exactly that, has failed with `GH013: Repository rule violations` on every
run). Any automation that needs to land a change on develop has to open a PR
like a human contributor and wait for review.

The `Security Baseline Refresh` workflow (`.github/workflows/security-baseline-refresh.yml`)
follows this: it regenerates the three security/SQL ratchet baselines on every
push to develop and opens or updates a PR from `ci/auto-refresh-baselines`
instead of pushing directly. If your own PR is only blocked by one of those
baselines, rebase onto develop once that PR merges rather than regenerating
the baseline yourself.

## Recommended, admin-applied

### 1. Require status checks (highest priority)

The ruleset does not require any status check, so a pull request can merge with
failing CI. Add a **Require status checks to pass** rule with
**Require branches to be up to date before merging** enabled, listing the
current checks:

- `CI / required`
- `cacti taint rules`
- `Semgrep taint`
- `Dependency review`
- `pest coverage gate`

`CI / required` is the stable aggregate for the PHP 8.1 locked integration,
locked runtime matrix, native dependency matrix, and CHANGELOG validation. This
keeps branch protection stable when implementation job names or matrix entries
change. Every required workflow must also listen for `merge_group` before the
repository enables merge queues.

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

### 5. Require immutable action references

Enable **Require actions to be pinned to a full-length commit SHA** under the
repository Actions policy. The checked-in workflow-policy job enforces the same
rule in review, along with fixed runner images, job timeouts, digest-pinned
service containers, and non-persistent checkout credentials for read-only jobs.

## Not changed

- 1 required approval suits the current contributor volume; raise it only if the
  team wants two-person review on protected branches.
- Copilot review remains advisory (not run on push, not run on drafts).
