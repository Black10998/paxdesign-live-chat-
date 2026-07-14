# App Store Screenshots

German marketing screenshots for the 6.7" iPhone display (de-DE primary locale).

| File | Feature |
|------|---------|
| `01-ihre-kommandozentrale.png` | Ihre Kommandozentrale — Dashboard |
| `02-live-anfragen-sofort-beantworten.png` | Live-Anfragen sofort beantworten — Live tab |
| `03-ki-gestuetzte-kundenantworten.png` | KI-gestützte Kundenantworten — Customer chat + AI |
| `04-integrierter-team-chat.png` | Integrierter Team-Chat — Team messaging |
| `05-eine-plattform-fuer-alles.png` | Eine Plattform für alles — Platform hub |

Upload to App Store Connect (upload only — does not submit for review):

```bash
python3 scripts/appstore-connect/upload_app_store_screenshots.py
```

Or trigger CI:

```bash
# push to main with .github/triggers/app-store-screenshots-upload updated
```
