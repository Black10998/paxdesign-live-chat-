## Production baseline

This PR must be based on current `main` (plugin **3.174.125**, matching live `paxdesign.at`).

- Do not reintroduce `3.176.x` chat (`skipping stacked sync`, instant-open freeze work).
- Do not restore CCS AI form-fill classes (`class-paxdesign-cybercrime-ai-workflow.php` and related).
- Do not `rsync --delete` the plugin tree onto production.

## Summary

<!-- What changed, and why it is built on the 3.174.91 baseline. -->
