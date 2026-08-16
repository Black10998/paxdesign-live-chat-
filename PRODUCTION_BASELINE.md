# Production baseline (live WordPress)

**Official production baseline:** the working WordPress plugin currently live on `https://paxdesign.at`.

| Field | Value |
| --- | --- |
| Plugin version | `3.174.91` |
| Source | Restored WordPress site (2026-08-08 16:26), then surgical chat/CCS patches |
| Restored git ancestor | `2f06a39e` (`3.174.85`) |
| Live overlay | `deploy-patches/restored-chat-human-ui/` |

This version is the only development baseline for future WordPress plugin work.

## Do not use

- GitHub `3.176.x` chat rewrites (`skipping stacked sync`, instant-open freeze work)
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
