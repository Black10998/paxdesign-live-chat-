# Release Policy — TestFlight First

**Effective:** 2026-07-13

## Current phase: TestFlight Internal Testing only

| Action | Allowed now? |
|--------|--------------|
| Build & upload to App Store Connect | ✅ Yes |
| TestFlight Internal Testing | ✅ Yes |
| Prepare metadata / screenshots in ASC | ⏸ Wait for final assets from product owner |
| Submit for App Store review | ❌ **No** — until explicit confirmation |
| Public App Store release | ❌ **No** |

## How submission is gated in CI

The workflow `app-store-submit.yml` and script `submit_app_store_review.py` will **not** submit for Apple review unless:

```bash
SUBMIT_FOR_REVIEW=1
```

is set in the environment. Default behavior is metadata/build preparation only.

## Before public release, product owner must provide

1. Final App Store screenshots (6.7" iPhone)
2. Final marketing copy / metadata approval
3. Written confirmation: *"Ready for App Store review submission"*

## Trader / compliance

DSA trader details: see [DSA_TRADER_SETUP.md](DSA_TRADER_SETUP.md)  
Age rating answers: see [COMPLIANCE_REVIEW.md](COMPLIANCE_REVIEW.md)
