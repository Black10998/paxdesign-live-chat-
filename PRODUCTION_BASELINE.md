# Production baseline (live WordPress)

**Official production baseline:** GitHub `main` matches the working WordPress plugin live on `https://paxdesign.at`.

| Field | Value |
| --- | --- |
| GitHub default branch | `main` |
| Plugin version | `3.174.125` |
| Source | Restored WordPress site (2026-08-08 16:26), then surgical chat/CCS patches |
| Restored git ancestor | `2f06a39e` (`3.174.85`) |
| Live overlay copy | `deploy-patches/restored-chat-human-ui/` (must stay identical to `paxdesign-booking/` files listed below) |
| Baseline merge | PR #282 (`5f37d88d`) |

This version is the only development baseline for future WordPress plugin work. Branch from current `main`. Do not base work on older `3.176.x` chat branches or closed PRs.

## Do not use

- GitHub `3.176.x` chat rewrites (`skipping stacked sync`, instant-open freeze work)
- Closed / superseded PRs: #262, #269, #270, #271, and any PR that restores CCS AI form-fill from chat
- Superseded chat branches: `cursor/chat-ui-compact-9e84`, `cursor/chat-whatsapp-speed-9e84`, `cursor/desktop-chat-regression-fix-7c3f` (merged; delete after sync)
- CCS AI classes that submit the website form from chat:
  - `class-paxdesign-cybercrime-ai-workflow.php`
  - `class-paxdesign-cybercrime-ai-operations.php`
  - `class-paxdesign-cybercrime-ai-case.php`
  - `class-paxdesign-cybercrime-document-checks.php`
  - `class-paxdesign-cybercrime-admin-reminders.php`

Those implementations are not the live site and must not be merged back.

## Deploy rule

Do not `rsync --delete` the GitHub plugin tree onto `paxdesign.at`.

Production chat deploys use the surgical workflow `deploy-restored-chat-human-ui.yml` from `main` or approved feature branches. Only the files listed in that workflow are copied to the live plugin directory.
