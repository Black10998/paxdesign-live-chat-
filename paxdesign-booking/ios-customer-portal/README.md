# PAXDesign Customer Portal — iOS

Native SwiftUI customer portal for PAXDesign. This is **not** the staff Live Chat admin app.

## Requirements

- iOS 16+
- WordPress plugin **paxdesign-booking v3.135.0+**
- WordPress account (same registration as https://paxdesign.at)
- Application Password for API access (Users → Profile → Application Passwords)

## No purchases

This app does **not** include In-App Purchases, PayPal, billing checkout, or module purchases. It is a communication and project portal only.

## API

Base URL: `https://paxdesign.at/wp-json/pdx/v1/customer`

Authentication: WordPress Application Password (HTTP Basic Auth) — same user identity as the website.

## Build

```bash
cd ios-customer-portal
# Open in Xcode when project is generated
```

## Features (initial shell)

- Dashboard
- Account-linked live chat
- Projects and service requests
- Service center (REST-driven, no WebView)
- News and notifications
- Profile and settings
