# Agent notes

## Production baseline

GitHub `main` and the live WordPress site `https://paxdesign.at` are the same official production baseline.

- Plugin version: **3.174.122**
- Branch all WordPress plugin work from current `main`
- Git tree: `paxdesign-booking/` matches the restored 2026-08-08 site plus `deploy-patches/restored-chat-human-ui/`
- Do not merge or re-deploy GitHub `3.176.x` chat / CCS AI rewrite code
- Do not use closed chat PRs (#262, #269, #270, #271) or older `cursor/*` chat branches as a base

## Cursor Cloud specific instructions

- Do not run workflows that `rsync --delete` `paxdesign-booking/` to production
- Do not copy `paxdesign-booking/` from current `main` history that still contains `3.176.x` chat
- Verify live chat JS still reports `Version: 3.174.122` and does not contain `skipping stacked sync`
- Customer chat must not show **Gespräch beenden**
- CCS AI help is prompt-guided one step at a time; it does not use `class-paxdesign-cybercrime-ai-workflow.php`

## Tests

```bash
php tests/production-baseline/run.php
php tests/restored-chat-human-ui/run.php
php tests/merge-integration/run.php
php tests/ccs-ai/run.php
```
