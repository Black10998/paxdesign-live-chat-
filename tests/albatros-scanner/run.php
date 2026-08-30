<?php
/**
 * Static guards for the Albatros Scanner Management plugin.
 */
$root = dirname(__DIR__, 2);
$plugin = $root . '/albatros-scanner';
$fail = 0;

function alb_ok($cond, $message) {
    global $fail;
    if ($cond) {
        echo "OK  $message\n";
        return;
    }
    echo "FAIL $message\n";
    $fail++;
}

function alb_json($path) {
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : array();
}

alb_ok(is_file($plugin . '/albatros-scanner.php'), 'plugin bootstrap exists');
alb_ok(is_file($plugin . '/includes/class-install.php'), 'schema class exists');
alb_ok(is_file($plugin . '/includes/class-auth.php'), 'auth class exists');
alb_ok(is_file($plugin . '/includes/class-rest.php'), 'rest class exists');
alb_ok(is_file($plugin . '/assets/css/app.css'), 'app css exists');
alb_ok(is_file($plugin . '/assets/js/app.js'), 'app js exists');
alb_ok(is_file($plugin . '/templates/login.php'), 'login template exists');
$logo = $plugin . '/assets/img/albatros-logo.jpeg';
alb_ok(is_file($logo), 'official logo file is stored in the plugin');
alb_ok(is_readable($logo) && substr((string) file_get_contents($logo, false, null, 0, 3), 0, 2) === "\xFF\xD8", 'logo is the original JPEG file');
$login_tpl = file_get_contents($plugin . '/templates/login.php');
$app_tpl = file_get_contents($plugin . '/templates/app.php');
alb_ok(strpos($login_tpl, 'login-brand') !== false, 'login screen includes the logo');
alb_ok(strpos($app_tpl, 'header-logo') !== false, 'application header includes the logo');
alb_ok(strpos($login_tpl, 'albatros-express.at') !== false && strpos($app_tpl, 'albatros-express.at') !== false, 'official company website is linked');
alb_ok(strpos($login_tpl, 'target="_blank"') !== false && strpos($app_tpl, 'rel="noopener noreferrer"') !== false, 'official website opens in a new tab');

$boot = file_get_contents($plugin . '/albatros-scanner.php');
$install = file_get_contents($plugin . '/includes/class-install.php');
$scanners = file_get_contents($plugin . '/includes/class-scanners.php');
$caps = file_get_contents($plugin . '/includes/class-capabilities.php');
$users = file_get_contents($plugin . '/includes/class-users.php');
$auth = file_get_contents($plugin . '/includes/class-auth.php');
$plugin_class = file_get_contents($plugin . '/includes/class-plugin.php');
$frontend = file_get_contents($plugin . '/includes/class-frontend.php');
$scan_class = file_get_contents($plugin . '/includes/class-scan.php');
$rest = file_get_contents($plugin . '/includes/class-rest.php');
$css = file_get_contents($plugin . '/assets/css/app.css');
$js = file_get_contents($plugin . '/assets/js/app.js');
$scan_tpl = file_get_contents($plugin . '/templates/scan.php');
$denied = file_get_contents($plugin . '/templates/denied.php');
$otp = file_get_contents($plugin . '/includes/class-otp.php');
$employee = file_get_contents($plugin . '/includes/class-employee.php');
$photos = file_get_contents($plugin . '/includes/class-photos.php');
$users_php = is_file($plugin . '/includes/class-users.php') ? file_get_contents($plugin . '/includes/class-users.php') : '';
$drivers = file_get_contents($plugin . '/includes/class-drivers.php');
$device = $plugin . '/assets/img/handheld-device.svg';

alb_ok(strpos($boot, "ALB_SCANNER_DEVELOPER_URL', 'https://paxdesign.at/'") !== false, 'developer website is attributed once');
alb_ok(substr_count($boot, 'paxdesign.at') === 1, 'plugin mentions paxdesign.at only as the developer site');
alb_ok(strpos($frontend, 'paxdesign-booking') === false, 'scanner frontend does not load the booking plugin');
alb_ok(strpos($install, "table('scanners')") !== false && strpos($install, 'serial_number') !== false, 'schema creates scanners table');
alb_ok(strpos($install, "table('drivers')") !== false, 'schema creates drivers table');
alb_ok(strpos($install, "table('handovers')") !== false, 'schema creates handovers table');
alb_ok(strpos($install, "table('status_events')") !== false, 'schema creates status events table');
alb_ok(strpos($install, "table('audit_logs')") !== false, 'schema creates audit table');
alb_ok(strpos($install, "table('scan_events')") !== false, 'schema creates scan events table');
alb_ok(strpos($install, 'schema_ready') !== false, 'schema upgrade repairs missing scan tables');
alb_ok(strpos($install, 'deleted_at') !== false, 'schema supports soft-delete');
alb_ok(is_file($plugin . '/includes/class-scan.php'), 'scan workflow class exists');
alb_ok(is_file($plugin . '/templates/scan.php'), 'mobile scan template exists');
alb_ok(strpos($scan_tpl, 'full_name') !== false && strpos($scan_tpl, 'handover.privacy_notice') !== false, 'guest handover asks for a real name and records a privacy notice');
alb_ok(strpos($scan_tpl, 'selfie') !== false && strpos($scan_tpl, 'handover.phone') !== false, 'handover collects selfie and mobile number');
alb_ok(strpos($scan_tpl, 'scanner.take_over') !== false && strpos($scan_tpl, 'otp_code') !== false, 'employee must verify then explicitly accept the scanner');
alb_ok(strpos($scan_tpl, 'driver_name') === false || strpos($scan_tpl, 'is_manager') !== false, 'employee QR page does not publish other employees by default');
alb_ok(strpos($frontend, 'render_scan') !== false && strpos($frontend, "strpos(\$path, 's/') === 0") !== false, 'QR routes render the scanner record, not the homepage');
alb_ok(strpos($frontend, 'can_use_admin_app') !== false && is_file($plugin . '/templates/denied.php'), 'non-managers are blocked from the admin app');
alb_ok(strpos($denied, 'access.denied') !== false, 'denied page tells employees to use the QR link');
alb_ok(strpos($otp, 'send_sms') !== false && strpos($otp, 'api.twilio.com') !== false, 'phone verification uses SMS OTP');
alb_ok(strpos($otp, 'IMEI') === false && strpos($otp, 'getDevicePhone') === false, 'code does not pretend to read a phone number from the device');
alb_ok(strpos($employee, 'employee_accept') !== false, 'employee accept uses the server handover time');
alb_ok(strpos($photos, 'albatros-private') !== false && strpos($photos, 'Require all denied') !== false, 'employee photos are stored privately');
alb_ok(strpos($photos, 'alb-photo/(driver|handover|user)') !== false, 'user photos are served privately');
alb_ok(strpos($users_php, 'set_photo') !== false && strpos($users_php, 'alb_photo_path') !== false, 'user accounts store a profile photo');
alb_ok(strpos($drivers, 'upsert_for_user') !== false && strpos($drivers, 'user_id') !== false, 'user photos can sync to employee/driver records');
alb_ok(is_file($device), 'handheld device illustration exists');
$svg = (string) file_get_contents($device);
alb_ok(strpos($svg, '<svg') !== false && stripos(substr($svg, 0, 400), 'samsung') === false && stripos(substr($svg, 0, 400), 'logo') === false, 'device mark is a neutral SVG without a brand logo');
alb_ok(strpos($svg, 'viewBox="0 0 181 366"') !== false && strpos($svg, 'image/png;base64,') !== false && strpos($svg, 'gradient') === false, 'scanner device is the cleaned product photo in SVG');
alb_ok(strpos($css, 'device-visual--lost') !== false && strpos($css, 'device-visual--inactive') !== false && strpos($css, 'device-visual--assigned') !== false, 'device visual has distinct status states');
alb_ok(strpos($css, 'device-visual-slot') !== false && strpos($css, 'aspect-ratio: 181 / 366') !== false, 'device is centered in the right half with native proportions');
alb_ok(strpos($svg, '5c7a94') === false && strpos($svg, 'c9a227') === false, 'device mark has no fake screen UI or decorative gold');
alb_ok(strpos($js, 'users.photo') !== false && strpos($js, '/photo') !== false, 'admin user form can upload a photo');
alb_ok(strpos($js, 'scanner.current_holder') !== false && strpos($js, 'device-visual') !== false, 'scanner detail shows holder photo and device mark');
alb_ok(strpos($js, 'scanner.copy_qr') !== false && strpos($scan_tpl, 'scanner.copy_qr') !== false, 'managers can copy the unique QR link');
alb_ok(strpos($caps, 'can_use_admin_app') !== false, 'admin app access is role-gated');
alb_ok(strpos($caps, 'scanners.identity') !== false, 'identity permission exists');
alb_ok(strpos($caps, "PRIMARY_EMAIL = 'sarah.gta1995@gmail.com'") !== false, 'sarah is the hardcoded primary manager');
alb_ok(strpos($caps, 'SCANNER_ADMIN') !== false && strpos($caps, 'scanner_admin') !== false, 'scanner administrator role exists');
alb_ok(strpos($caps, 'privileged_keys') !== false && strpos($caps, 'ensure_primary') !== false, 'primary manager lock and privileged keys exist');
alb_ok(strpos($caps, 'SCHEMA_VERSION') !== false && strpos($caps, 'unset($overrides[$key])') !== false, 'schema upgrade strips leftover privileged overrides');
alb_ok(strpos($caps, 'extra_permission_keys') !== false, 'optional extra rights stay small');
alb_ok(strpos($caps, 'USER_PERMS') !== false, 'per-user permissions exist');
alb_ok(strpos($caps, 'assignable_roles') !== false, 'user creation roles are restricted');
alb_ok(strpos($users, 'users.error.role_forbidden') !== false, 'non-super-admins cannot create administrator accounts');
alb_ok(strpos($users, 'users.error.primary_protected') !== false, 'primary account cannot be edited by others');
alb_ok(strpos($users, 'alb_status') !== false || strpos($caps, 'set_status') !== false, 'users can be activated or deactivated');
alb_ok(strpos($auth, 'alb_last_login') !== false, 'last login is stored as a single field');
alb_ok(strpos($auth, "'action' => 'login'") === false, 'login does not append audit rows');
alb_ok(strpos($plugin_class, 'users_can_register') !== false, 'public self-registration is disabled');
alb_ok(strpos($scanners, 'scanners.identity') !== false, 'identity field changes are permission-checked');
alb_ok(strpos($scanners, 'scanner.error.phone_protected') !== false, 'scanner phone number is identity-protected');
alb_ok(strpos($frontend, "wp_safe_redirect(home_url('/scanners/'") === false, 'QR scan is not redirected away from the scanner token');
alb_ok(strpos($scan_class, 'maybe_record_open') !== false && strpos($scan_class, 'actor_name') !== false, 'scans store person, scanner and time');
alb_ok(strpos($scanners, 'soft_delete') !== false && strpos($scanners, 'restore') !== false, 'scanners can be removed without erasing history');
alb_ok(strpos($scanners, "s.current_driver_id IS NOT NULL") !== false, 'assigned scanners can be queried');
alb_ok(strpos($scanners, "'inactive'") !== false, 'inactive status is supported');
alb_ok(strpos($caps, 'scanners.delete') !== false, 'delete permission exists');
alb_ok(strpos($js, 'data-view') !== false && strpos($js, 'loadDashList') !== false, 'dashboard cards load matching scanner lists');
alb_ok(strpos($js, 'scanner.take_over') !== false && strpos($js, 'scanner.delete_confirm') !== false, 'scanner management actions are in the UI');
alb_ok(strpos($rest, '/scan/') !== false && strpos($rest, 'public_scan') !== false, 'public scan API exists');
alb_ok(strpos($scanners, "const IMMUTABLE = array('brand', 'model', 'serial_number')") !== false, 'brand/model/serial are immutable');
alb_ok(strpos($scanners, 'scanner.error.immutable') !== false, 'immutable update is rejected');
alb_ok(strpos($scanners, 'handovers') !== false && strpos($scanners, 'reassign') !== false, 'handovers are stored instead of overwritten');
alb_ok(strpos($scanners, 'last_assigned_driver') !== false, 'lost scanners retain last driver');
foreach (array('active', 'lost', 'defective', 'returned', 'repair') as $status) {
    alb_ok(strpos($scanners, "'" . $status . "'") !== false, 'status supported: ' . $status);
}
foreach (array('super_admin', 'administrator', 'scanner_admin', 'staff') as $role) {
    alb_ok(strpos($caps, $role) !== false, 'role exists: ' . $role);
}
foreach (array('scanners.assign', 'audit.view', 'users.manage', 'reports.export') as $perm) {
    alb_ok(strpos($caps, $perm) !== false, 'permission exists: ' . $perm);
}
alb_ok(strpos($css, 'animation') === false && strpos($css, 'gradient') === false, 'css has no animations or gradients');
alb_ok(strpos($css, 'box-shadow') === false, 'css has no box shadows');
alb_ok(strpos($js, 'login.error') === false || strpos($js, 'api(') !== false, 'app talks to rest api');
alb_ok(strpos($app_tpl, 'help-btn') !== false && strpos($app_tpl, 'page-context') !== false, 'header has page context, search, and help');
alb_ok(strpos($js, "items.push(['/help', 'nav.help', 'help'])") !== false, 'help is in the main navigation');
alb_ok(strpos($js, 'function icon(') !== false && strpos($js, 'nav-icon') !== false, 'navigation uses consistent SVG icons');
alb_ok(strpos($js, 'renderHelp') !== false && strpos($js, 'about.title') !== false, 'help page includes about and system information');
alb_ok(strpos($js, 'stroke="currentColor"') !== false && strpos($js, 'stroke-width="1.75"') !== false, 'icons are stroke SVGs without decorative fills');
alb_ok(strpos($frontend, "home_url('/scanners')") !== false && strpos($js, "replaceState({}, '', '/scanners')") !== false, 'login and app root open the scanner page');
alb_ok(strpos($js, "items.push(['/dashboard', 'nav.dashboard', 'dashboard'])") !== false, 'overview stays available at /dashboard');
alb_ok(strpos($js, 'topicMarks') !== false && strpos($js, 'vehicle:') !== false && strpos($js, 'package:') !== false, 'driver section has vehicle and package icons');
alb_ok(strpos($js, 'role-card') !== false && strpos($js, 'roleCards') !== false, 'user creation uses simple role cards');
alb_ok(strpos($js, 'userPermBoxes') === false, 'create-user screen does not show a full permission grid');
alb_ok(strpos($js, 'extraPermBoxes') !== false && strpos($js, 'users.extras') !== false, 'optional extra rights are collapsed');
alb_ok(strpos($js, "body.phone_number = fd.get('phone_number')") !== false, 'phone edits are sent only with identity permission');
alb_ok(strpos(file_get_contents($plugin . '/includes/class-settings.php'), 'settings.owner_locked') !== false, 'owner settings are primary-only');
alb_ok(strpos($auth, 'users.error.inactive') !== false, 'inactive users cannot sign in');

$de = alb_json($plugin . '/languages/de.json');
$en = alb_json($plugin . '/languages/en.json');
$tr = alb_json($plugin . '/languages/tr.json');
alb_ok(count($de) > 80, 'german catalog is populated');
alb_ok(array_keys($de) === array_keys($en), 'english keys match german');
alb_ok(array_keys($de) === array_keys($tr), 'turkish keys match german');
alb_ok(($de['login.title'] ?? '') !== ($en['login.title'] ?? ''), 'german and english login titles differ');
alb_ok(($de['nav.scanners'] ?? '') !== ($tr['nav.scanners'] ?? ''), 'german and turkish nav labels differ');
alb_ok(($de['official.website'] ?? '') === 'Offizielle Unternehmenswebsite', 'german official website label exists');
alb_ok(($en['official.website'] ?? '') === 'Official Company Website', 'english official website label exists');
alb_ok(($de['scan.full_name'] ?? '') === 'Name / Vollständiger Name', 'german scan identification label exists');
alb_ok(($de['handover.privacy_notice'] ?? '') !== '', 'german handover privacy notice exists');
alb_ok(($en['scanner.copy_qr'] ?? '') === 'Copy QR link', 'english copy-link label exists');
alb_ok(strpos($install, "table('otp_challenges')") !== false, 'schema creates otp table');
alb_ok(($en['scanner.take_over'] ?? '') === 'Take over scanner', 'english take-over action exists');
alb_ok(($de['dash.click_hint'] ?? '') !== ($en['dash.click_hint'] ?? ''), 'dashboard hint is translated');
alb_ok(($en['nav.help'] ?? '') === 'Help' && ($de['nav.help'] ?? '') === 'Hilfe', 'help labels exist');
alb_ok(($en['about.developer'] ?? '') !== '', 'about developer label exists');
alb_ok(($de['driver.vehicle'] ?? '') === 'Lieferfahrzeug' && ($en['driver.package'] ?? '') === 'Package', 'driver vehicle and package labels exist');
alb_ok(($de['status.assigned'] ?? '') === 'Zugewiesen' && ($en['status.assigned'] ?? '') === 'Assigned', 'assigned device state is translated');

foreach (glob($plugin . '/includes/*.php') as $file) {
    $out = array();
    $code = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    alb_ok($code === 0, 'php syntax ' . basename($file));
}

if ($fail > 0) {
    fwrite(STDERR, "$fail albatros-scanner assertion(s) failed\n");
    exit(1);
}
echo "All albatros-scanner checks passed.\n";
