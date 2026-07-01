# PAXdesign Booking System v2.3

Professional WordPress appointment booking plugin with minimal, enterprise-style interface.

**Note:** This is NOT a live chat system. The floating button visually resembles a support/chat widget, but its purpose is **appointment booking and contact scheduling**.

## Features

✅ **Floating button** (looks like support chat, but for booking appointments)  
✅ **Professional booking dialog** (no animations, no hover effects)  
✅ **4-step appointment booking process**:
  1. Select who to contact (team member)
  2. Choose service (for Adam only - 10 pricing options)
  3. Choose appointment date & time
  4. Fill contact details & purpose

✅ **Team Management** - Full admin control:
  - Enable/disable team members
  - Drag-and-drop ordering
  - Availability status settings
  - Real-time statistics dashboard

✅ **Service selection** for Adam (Website, Web App, Android, iOS, etc.)  
✅ **Email notifications** to info@paxdesign.at with service info  
✅ **Customer confirmations**  
✅ **Admin dashboard** to view bookings  
✅ **Settings page** for email configuration  
✅ **Database storage** with service tracking  
✅ **Fully responsive**  
✅ **No external dependencies**

## Design Philosophy

- ❌ No animations
- ❌ No hover effects
- ❌ No motion or transitions
- ✅ Static, calm, professional
- ✅ Clean spacing
- ✅ Modern typography
- ✅ Enterprise-style UI

## Installation

1. Download `paxdesign-booking.zip`
2. Go to **WordPress Admin → Plugins → Add New**
3. Click **"Upload Plugin"**
4. Choose the ZIP file
5. Click **"Install Now"**
6. Click **"Activate Plugin"**

Done! The floating button appears automatically on all pages.

## Configuration

After activation:

### Email Settings
1. Go to **Booking System → Einstellungen**
2. Set email addresses (default: info@paxdesign.at)
3. Save changes

### Team Management
1. Go to **Booking System → Team Management**
2. Enable/disable team members as needed
3. Drag rows to reorder display sequence
4. Set availability status for each member
5. Click **"Save Changes"** to apply

See [TEAM-MANAGEMENT.md](TEAM-MANAGEMENT.md) for detailed documentation.

## Usage

**Frontend:**
- Small floating button appears in bottom-right corner (looks like support chat)
- Click to open appointment booking dialog
- **Step 1:** Choose who you want to contact (team member)
- **Step 2:** Select appointment date & time slot
- **Step 3:** Fill contact details & select purpose (consultation, support, project discussion, etc.)
- Submit appointment request

**Admin:**
- View all bookings in **Booking System** menu
- Manage team members in **Team Management**
- Configure emails in **Einstellungen**

## Email Notifications

**Admin receives:**
- Team member selected
- Date & time
- Customer details
- Purpose & message

**Customer receives:**
- Booking confirmation
- Team member info
- Contact information

## Technical Details

- **Version**: 2.3.0
- **Requires**: WordPress 5.0+
- **PHP**: 7.0+
- **Database**: Auto-creates table on activation with service column
- **Dependencies**: jQuery (included in WordPress)

## File Structure

```
paxdesign-booking/
├── paxdesign-booking.php
├── assets/
│   ├── css/
│   │   ├── booking-styles.css
│   │   └── admin-styles.css
│   └── js/
│       ├── booking-script.js
│       └── admin-script.js
├── templates/
│   ├── booking-widget.php
│   ├── admin-page.php
│   ├── settings-page.php
│   └── team-management-page.php
├── README.md
└── TEAM-MANAGEMENT.md
```

## Support

- Email: info@paxdesign.at
- Phone: +43 681 20543638
- Website: https://paxdesign.at

## License

GPL v2 or later

## Changelog

### 2.3.0 (2026-01-07)
- **NEW:** Team Management admin interface
- Enable/disable individual team members
- Drag-and-drop ordering system
- Availability status controls (Available, Busy, On Vacation, Unavailable)
- Real-time statistics dashboard
- Professional WordPress admin UI
- AJAX-powered settings save
- Keyboard shortcuts (Ctrl+S / Cmd+S)
- Unsaved changes warning

### 2.2.0 (2026-01-02)
- Added service selection for Adam (10 pricing options)
- Service cards with icons, prices, and descriptions
- Popular and Premium badges for highlighted services
- Service info in email notifications
- Service column in database
- 4-step booking process (team → service → date/time → details)
- Updated UI with service selection grid

### 2.0.0 (2025-12-31)
- Complete redesign: minimal, professional, enterprise-style
- Floating chat-style button (bottom-right)
- Small support dialog (not full-screen)
- Removed all animations and hover effects
- Static, calm, trustworthy UI
- Clean spacing and modern typography

### 1.0.0 (2025-12-31)
- Initial release
