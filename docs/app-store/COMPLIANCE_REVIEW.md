# App Store Connect Compliance Review — Age Ratings & EU DSA

**App:** PAXDesign Live Chat (`at.paxdesign.livechat`)  
**Legal entity (metadata):** PAXdesign / PrimoJob GmbH  
**Last reviewed:** 2026-07-13 (codebase audit + Apple documentation)

> **Important:** I will not submit inaccurate compliance data. Age Rating and EU Trader answers below are based on the actual app codebase. DSA trader verification requires the **Account Holder** in App Store Connect (2FA + document upload).

---

## Notice 1 — Age Ratings (Social Media & Messaging)

### What Apple is asking (July 2026 update)

Apple added questions about **Social Media capabilities**:

> *"The ability to redistribute, amplify, or interact with user-generated content through a social feed or similar discovery method."*

Examples: public feeds, reposting, likes, comments, reactions, or tools that make UGC visible to many users.

**Messaging and Chat** is a separate capability:

> *"Users can directly communicate with one another through features within the app (text, voice, video, direct/group messaging, public posting)."*

**User-Generated Content** is also separate:

> *"Broad distribution of content created by users as a component of the app's intended user experience."*

### What the app actually does (verified in code)

| Feature | Present? | Details |
|---------|----------|---------|
| Staff-only login | ✅ | WordPress admin + Application Password |
| Customer live chat | ✅ | Staff ↔ website visitors (support workflow) |
| Team chat | ✅ | Staff ↔ staff (directory contacts only) |
| Text messages | ✅ | Customer + team chat |
| Voice messages | ✅ | Team chat only |
| Photo sharing | ✅ | Customer + team chat |
| Location sharing | ✅ | Team chat only |
| AI reply suggestions | ✅ | Staff tool for customer replies |
| Social feed / timeline | ❌ | No public feed |
| Friend lists / followers | ❌ | Staff directory only |
| Public profiles | ❌ | Internal CRM profiles only |
| Likes/comments/shares on public feed | ❌ | No social amplification |
| Video calls | ❌ | Not implemented |
| In-app ads | ❌ | None |
| Unrestricted web browsing | ❌ | Safari only for legal URLs |
| User report / block UI (consumer) | ❌ | Enterprise admin tooling only |

### Recommended accurate answers

| Question / Capability | Recommended answer | Reason |
|----------------------|-------------------|--------|
| **Social Media capabilities** | **No** | No social feed, no redistribution/amplification to many users |
| **Social Media disabled for users under 13** | **N/A** (if Social Media = No) | Not applicable |
| **Messaging and Chat** | **Yes** | Staff text chat with customers and coworkers; voice in team chat |
| **User-Generated Content** | **Yes — Infrequent / Limited** | Staff send text, photos, voice, location in 1:1 or small team threads — not broad public distribution |
| **Unrestricted Web Access** | **No** | No embedded browser; legal links open in Safari |
| **Advertising** | **No** | No ads |
| **Gambling / Loot boxes / etc.** | **No** | Not applicable |

### Expected resulting rating

With the answers above, the app should remain in the **Business / Productivity** category and is unlikely to receive the **Social Media** content descriptor or the **13+ minimum** tied to social media.

Likely rating: **4+** or similar (messaging + limited UGC are allowed at 4+ per Apple's tables).

**Do not** answer "Yes" to Social Media just because the app has chat — Apple defines social media specifically as feed-based amplification, which this app does not have.

### ASC links to review/update

- Age Rating: https://appstoreconnect.apple.com/apps/6790031845/distribution/agerating
- App Information: https://appstoreconnect.apple.com/apps/6790031845/distribution/info

---

## Notice 2 — EU Trader Status / Digital Services Act (DSA)

### What Apple requires

**Every developer** must declare trader status — even if the app is not distributed in the EU.

If you are a **trader** distributing in the EU, Apple must **verify and publicly display** on your EU App Store page:

- Business address (from D-U-N-S for organizations)
- Phone number
- Email address

Since **February 17, 2025**, apps without verified trader status can be **removed from EU App Store** territories.

### Is PAXDesign Live Chat a "trader"?

Based on available metadata, **most likely YES**:

| Indicator | Applies? |
|-----------|----------|
| App developed for business/professional use | ✅ Staff support tool |
| Legal entity: **PrimoJob GmbH** | ✅ Commercial organization |
| App linked to trade/business (PAXdesign) | ✅ |
| Free app with no IAP/ads | ✅ (still can be a trader) |
| Hobby/personal app | ❌ |

Apple cannot determine trader status for you — **you must confirm** with your legal advisor if uncertain.

### What can be completed developer-side vs. what needs you

| Task | Who | Status |
|------|-----|--------|
| Declare trader / non-trader | **Account Holder or Admin** | ❌ Requires ASC login |
| Enter & verify phone (2FA) | **Account Holder** | ❌ Needs your input |
| Enter & verify email (2FA) | **Account Holder** | ❌ Needs your input |
| Upload business verification document | **Account Holder** | ❌ Needs your document |
| Payment/banking details | **Account Holder** | ❌ Verify in ASC |
| App-specific DSA toggle | **Account Holder or Admin** | App Information → Digital Services Act |
| Labels and Markings URL (optional) | **Account Holder or Admin** | Only if EU law requires |

### ASC links

- Business compliance: https://appstoreconnect.apple.com/business/compliance
- App-specific DSA: https://appstoreconnect.apple.com/apps/6790031845/distribution/info (scroll to **App Store Regulations and Permits → Digital Services Act**)

---

## Will these notices block submission?

| Notice | Blocks submission if incomplete? | Blocks EU distribution? |
|--------|-------------------------------|------------------------|
| Age Ratings (new social/messaging questions) | **Yes** — required from September 2026; may already show as notice now | No (global requirement) |
| EU Trader Status / DSA | **Yes** — required for all submissions; EU removal risk since Feb 2025 | **Yes** if trader info not verified |

---

## Information status (updated 2026-07-13)

### A. Age Ratings confirmation — pending explicit OK

1. Confirm you agree with the recommended answers in the table above (**especially Social Media = No**).
2. If App Store Connect shows different wording, send a **screenshot** of the exact questions and I will map answers line by line.

### B. EU Trader / DSA — **details received** (Account Holder must complete ASC UI)

| # | Item | Value / status |
|---|------|----------------|
| 1 | **Trader status** | ✅ **Trader** (confirmed by product owner) |
| 2 | **Public phone** | ✅ +43 681 20543638 |
| 3 | **Public email** | ✅ info@paxdesign.at |
| 4 | **Public address** | ✅ Franzensbrückenstraße 14, 1020 Wien, Austria |
| 5 | **Business verification document** | ⏳ Account Holder must upload in ASC |
| 6 | **Email/phone 2FA** | ⏳ Account Holder must verify in ASC |
| 7 | **Payment/banking** in ASC | ⏳ Confirm Agreements, Tax, and Banking are complete |
| 8 | **Labels and Markings URL** (optional) | Not required unless EU law mandates |

Copy-paste guide for Account Holder: [DSA_TRADER_SETUP.md](DSA_TRADER_SETUP.md)

### C. If NOT a trader (unlikely for GmbH)

- Explicit written confirmation: *"Declare as not a trader"* — I will document but recommend legal review first.

---

## What I will do after you provide the above

1. Map your answers to the exact ASC questionnaire (no guessing).
2. Update age rating via API only after your confirmation.
3. Guide Account Holder through DSA verification steps (cannot be done via API — requires 2FA + document upload).
4. Re-run compliance status check and confirm both notices are cleared before final submission.

---

## References

- [Apple — Age rating social media questions (July 2026)](https://developer.apple.com/news/?id=tlur8uvi)
- [Apple — Age ratings values and definitions](https://developer.apple.com/help/app-store-connect/reference/age-ratings-values-and-definitions)
- [Apple — EU DSA trader requirements](https://developer.apple.com/help/app-store-connect/manage-compliance-information/manage-european-union-digital-services-act-trader-requirements)
