# Agent notes

## Production baseline

The live WordPress site `https://paxdesign.at` is the official production baseline.

- Plugin version: **3.174.91**
- Git tree: `paxdesign-booking/` matches the restored 2026-08-08 site plus `deploy-patches/restored-chat-human-ui/`
- Future WordPress plugin changes must be built on this tree
- Do not merge or re-deploy GitHub `3.176.x` chat / CCS AI rewrite code

## Cursor Cloud specific instructions

- Do not run workflows that `rsync --delete` `paxdesign-booking/` to production
- Do not copy `paxdesign-booking/` from current `main` history that still contains `3.176.x` chat
- Verify live chat JS still reports `Version: 3.174.91` and does not contain `skipping stacked sync`
- Customer chat must not show **Gespräch beenden**
- CCS AI help is prompt-guided one step at a time; it does not use `class-paxdesign-cybercrime-ai-workflow.php`

## Tests

```bash
php tests/production-baseline/run.php
php tests/restored-chat-human-ui/run.php
php tests/merge-integration/run.php
php tests/ccs-ai/run.php
```
