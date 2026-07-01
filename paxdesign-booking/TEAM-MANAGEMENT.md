# Team Management Feature

## Overview

The Team Management feature provides full administrative control over which team members appear in the booking widget, their display order, and availability settings.

## Features

### 1. Enable/Disable Team Members
- Toggle individual team members on or off
- Disabled members are hidden from the booking widget
- Changes take effect immediately after saving

### 2. Drag-and-Drop Ordering
- Reorder team members by dragging rows
- Display order is reflected in the booking widget
- Visual feedback during drag operations

### 3. Availability Status
- Set availability for each team member:
  - **Available**: Ready to take bookings
  - **Busy**: Currently occupied
  - **On Vacation**: Away from office
  - **Unavailable**: Not accepting bookings

### 4. Real-time Statistics
- Total team members
- Enabled/disabled count
- Members with service offerings

## How to Use

### Accessing Team Management

1. Log in to WordPress Admin
2. Navigate to **Booking System → Team Management**
3. You'll see the team management interface

### Managing Team Members

#### Enable/Disable Members
- Use the toggle switch in the "Enabled" column
- Green = Enabled, Gray = Disabled
- Disabled members won't appear in the booking widget

#### Reorder Members
1. Hover over the drag handle (☰ icon) on the left
2. Click and drag the row to a new position
3. Release to drop
4. Order numbers update automatically

#### Set Availability
- Use the dropdown in the "Availability" column
- Select from: Available, Busy, On Vacation, Unavailable
- This information can be used for future features

#### Save Changes
- Click the **"Save Changes"** button at the bottom
- Wait for the success confirmation
- Changes are applied immediately

### Keyboard Shortcuts

- **Ctrl+S** (Windows) or **Cmd+S** (Mac): Save changes

## Technical Details

### Database Storage

Settings are stored in WordPress options table:
- Option name: `paxdesign_booking_team_settings`
- Format: JSON array with member settings

### Data Structure

```php
array(
    'adam' => array(
        'enabled' => true,
        'order' => 1,
        'availability' => 'available'
    ),
    'ahmad' => array(
        'enabled' => false,
        'order' => 2,
        'availability' => 'busy'
    ),
    // ...
)
```

### Frontend Integration

The booking widget automatically:
- Filters out disabled members
- Displays members in the specified order
- Respects all settings without code changes

### AJAX Endpoints

**Action**: `paxdesign_update_team_settings`
- **Method**: POST
- **Nonce**: `paxdesign_admin_nonce`
- **Parameters**: 
  - `settings`: JSON string of team settings

## Files Modified/Created

### New Files
- `templates/team-management-page.php` - Admin interface
- `assets/css/admin-styles.css` - Admin styling
- `assets/js/admin-script.js` - Admin functionality

### Modified Files
- `paxdesign-booking.php` - Core plugin file
  - Updated `get_team_members()` method
  - Added admin menu item
  - Added AJAX handler
  - Added admin asset enqueuing

## Future Enhancements

Potential additions:
- Custom working hours per team member
- Booking capacity limits
- Automatic availability based on calendar
- Email notification preferences
- Custom booking form fields per member
- Integration with external calendar systems

## Troubleshooting

### Changes Not Appearing
1. Clear browser cache
2. Check if "Save Changes" was clicked
3. Verify user has `manage_options` capability

### Drag-and-Drop Not Working
1. Ensure jQuery UI is loaded
2. Check browser console for JavaScript errors
3. Try disabling other plugins temporarily

### Settings Not Saving
1. Check WordPress debug log
2. Verify AJAX URL is correct
3. Ensure nonce is valid
4. Check database write permissions

## Support

For issues or questions:
- Email: info@paxdesign.at
- Website: https://paxdesign.at

## Version History

### Version 2.2.0
- Added Team Management feature
- Enable/disable team members
- Drag-and-drop ordering
- Availability status controls
- Real-time statistics dashboard
