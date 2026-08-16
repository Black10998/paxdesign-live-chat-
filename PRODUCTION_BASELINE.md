# Production baseline (live WordPress)

**Official production baseline:** GitHub `main` matches the working WordPress plugin live on `https://paxdesign.at`.

| Field | Value |
| --- | --- |
| GitHub default branch | `main` |
| Plugin version | `3.174.93` |
| Source | Restored WordPress site (2026-08-08 16:26), then surgical chat/CCS patches |
| Restored git ancestor | `2f06a39e` (`3.174.85`) |
| Live overlay copy | `deploy-patches/restored-chat-human-ui/` (must stay identical to `paxdesign-booking/` files listed below) |
| Baseline merge | PR #272 (`ebd85c3d`) |

This version is the only development baseline for future WordPress plugin work. Branch from current `main`. Do not base work on older `3.176.x` chat branches or closed PRs.

## Do not use

- GitHub `3.176.x` chat rewrites (`skipping stacked sync`, instant-open freeze work)
- Closed / superseded PRs: #262, #269, #270, #271, and any PR that restores CCS AI form-fill from chat
- CCS AI classes that submit the website form from chat:
  - `class-paxdesign-cybercrime-ai-workflow.php`
  - `class-paxdesign-cybercrime-ai-operations.php`
  - `class-paxdesign-cybercrime-ai-case.php`
  - `class-paxdesign-cybercrime-document-checks.php`
  - `class-paxdesign-cybercrime-admin-reminders.php`

Those implementations are not the live site and must not be merged back.

## Deploy rule

Do not `rsync --delete` the GitHub plugin tree onto `paxdesign.at`.

Full-plugin auto-deploys on `main` are disabled. The live site stays on this baseline unless a surgical, reviewed overlay is used on purpose.
