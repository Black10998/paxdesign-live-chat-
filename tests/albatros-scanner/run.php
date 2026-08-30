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

$boot = file_get_contents($plugin . '/albatros-scanner.php');
$install = file_get_contents($plugin . '/includes/class-install.php');
$scanners = file_get_contents($plugin . '/includes/class-scanners.php');
$caps = file_get_contents($plugin . '/includes/class-capabilities.php');
$frontend = file_get_contents($plugin . '/includes/class-frontend.php');
$css = file_get_contents($plugin . '/assets/css/app.css');
$js = file_get_contents($plugin . '/assets/js/app.js');

alb_ok(strpos($boot, 'paxdesign.at') === false, 'plugin does not reference paxdesign.at');
alb_ok(strpos($frontend, 'paxdesign.at') === false, 'frontend does not reference paxdesign.at');
alb_ok(strpos($install, "table('scanners')") !== false && strpos($install, 'serial_number') !== false, 'schema creates scanners table');
alb_ok(strpos($install, "table('drivers')") !== false, 'schema creates drivers table');
alb_ok(strpos($install, "table('handovers')") !== false, 'schema creates handovers table');
alb_ok(strpos($install, "table('status_events')") !== false, 'schema creates status events table');
alb_ok(strpos($install, "table('audit_logs')") !== false, 'schema creates audit table');
alb_ok(strpos($scanners, "const IMMUTABLE = array('brand', 'model', 'serial_number')") !== false, 'brand/model/serial are immutable');
alb_ok(strpos($scanners, 'scanner.error.immutable') !== false, 'immutable update is rejected');
alb_ok(strpos($scanners, 'handovers') !== false && strpos($scanners, 'reassign') !== false, 'handovers are stored instead of overwritten');
alb_ok(strpos($scanners, 'last_assigned_driver') !== false, 'lost scanners retain last driver');
foreach (array('active', 'lost', 'defective', 'returned', 'repair') as $status) {
    alb_ok(strpos($scanners, "'" . $status . "'") !== false, 'status supported: ' . $status);
}
foreach (array('super_admin', 'administrator', 'staff') as $role) {
    alb_ok(strpos($caps, $role) !== false, 'role exists: ' . $role);
}
foreach (array('scanners.assign', 'audit.view', 'users.manage', 'reports.export') as $perm) {
    alb_ok(strpos($caps, $perm) !== false, 'permission exists: ' . $perm);
}
alb_ok(strpos($css, 'animation') === false && strpos($css, 'gradient') === false, 'css has no animations or gradients');
alb_ok(strpos($css, 'box-shadow') === false, 'css has no box shadows');
alb_ok(strpos($js, 'login.error') === false || strpos($js, 'api(') !== false, 'app talks to rest api');

$de = alb_json($plugin . '/languages/de.json');
$en = alb_json($plugin . '/languages/en.json');
$tr = alb_json($plugin . '/languages/tr.json');
alb_ok(count($de) > 80, 'german catalog is populated');
alb_ok(array_keys($de) === array_keys($en), 'english keys match german');
alb_ok(array_keys($de) === array_keys($tr), 'turkish keys match german');
alb_ok(($de['login.title'] ?? '') !== ($en['login.title'] ?? ''), 'german and english login titles differ');
alb_ok(($de['nav.scanners'] ?? '') !== ($tr['nav.scanners'] ?? ''), 'german and turkish nav labels differ');

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
