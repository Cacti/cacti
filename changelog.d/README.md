# CHANGELOG fragments

Add a file here instead of editing `CHANGELOG` directly. Every pull request
that edits `CHANGELOG` lands on one of two lines, the top of the issue block or
the end of the feature block, so merging one pull request conflicts the rest.
A fragment is a new file per change, which nothing else touches.

Name the file after the issue or pull request number. Security entries take
either a number or a GHSA identifier:

    changelog.d/7634.feature
    changelog.d/7692.issue
    changelog.d/7476.security
    changelog.d/GHSA-wpjq-m269-mghj.security

Put the description on a single line, with no `-feature#` or `-issue#` prefix:

    Add indexes for host-template and session lookup queries

The release fold turns that into:

    -feature#7634: Add indexes for host-template and session lookup queries

A release section runs security, then issue, then feature. Security and issue
entries are newest first, features oldest first, and the fold places each
entry accordingly.

`tests` in CI validate fragment names and contents. Run the fold when cutting a
release, which rewrites `CHANGELOG` and removes the fragments:

    python3 .github/scripts/fold-changelog.py

Use `--check` to see what would be folded without writing anything.
