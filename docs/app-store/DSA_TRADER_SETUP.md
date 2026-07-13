# EU DSA Trader Setup — PAXDesign Live Chat

**Status:** Trader  
**Legal entity:** PAXdesign / PrimoJob GmbH  
**Prepared:** 2026-07-13

> Apple does **not** expose DSA trader verification via API. The **Account Holder** must complete this in App Store Connect (2FA + document upload). Values below are ready to copy-paste.

---

## Trader contact information (for EU App Store display)

| Field | Value |
|-------|-------|
| **Trader status** | This is a **trader** account |
| **Email** | info@paxdesign.at |
| **Phone** | +43 681 20543638 |
| **Address** | Franzensbrückenstraße 14, 1020 Wien, Austria |

---

## Account Holder steps (required)

### 1. Account-level DSA compliance

1. Sign in to [App Store Connect](https://appstoreconnect.apple.com) as **Account Holder**
2. Go to **Business** (top navigation)
3. Open **Agreements** tab
4. Scroll to **Compliance** → **Digital Services Act**
5. Click **Complete Compliance Requirements**
6. Select **“This is a trader account”** → **Next**
7. Enter contact details exactly as in the table above
8. **Verify email** (2FA code sent to info@paxdesign.at)
9. **Verify phone** (2FA SMS/call to +43 681 20543638)
10. Upload **business verification document** (company registration / Gewerbeschein / legal record showing business name and address)
11. Review and **Confirm**
12. Wait for Apple verification (may take 24–48 hours)

Direct link: https://appstoreconnect.apple.com/business/compliance

### 2. App-specific DSA setting

1. Open app: https://appstoreconnect.apple.com/apps/6790031845/distribution/info
2. Scroll to **App Store Regulations and Permits**
3. Under **Digital Services Act**, click **Edit**
4. Confirm trader status is **enabled** for this app
5. Save

### 3. Confirm compliance notice cleared

After verification, the DSA notice on the App Store Connect dashboard should disappear. Re-check before final submission.

---

## What the automation agent cannot do

| Step | Reason |
|------|--------|
| Email/phone 2FA | Requires Account Holder inbox/SMS access |
| Document upload | Requires Account Holder login |
| Business address override | Organizations use D-U-N-S address; confirm it matches Franzensbrückenstraße 14, 1020 Wien |

If D-U-N-S address differs from the address above, either update D-U-N-S via Apple/Dun & Bradstreet or contact Apple Support to align records.

---

## Release policy reminder

- **TestFlight Internal Testing only** until final screenshots and metadata are confirmed
- **No App Store public release** until explicit approval
- CI is gated: `SUBMIT_FOR_REVIEW` must be set to submit for Apple review

See [RELEASE_POLICY.md](RELEASE_POLICY.md).
