<?php

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/../app/application.php';

$root = dirname(__DIR__);
$counts = [
    'agencies' => (int) dbFetchOne("SELECT COUNT(*) n FROM agencies WHERE name LIKE 'P5A\\_TEST\\_%' ESCAPE '\\\\'")['n'],
    'users' => (int) dbFetchOne("SELECT COUNT(*) n FROM users WHERE email_normalized LIKE 'p5a_test_%'")['n'],
    'allocations' => (int) dbFetchOne(
        "SELECT COUNT(*) n
         FROM financial_number_allocations n
         JOIN agencies a ON a.id = n.agency_id
         WHERE a.name LIKE 'P5A\\_TEST\\_%' ESCAPE '\\\\'"
    )['n'],
];

$artifacts = [];
foreach (glob($root . '/storage/p5a_test_*') ?: [] as $path) {
    $artifacts[] = $path;
}
foreach (glob($root . '/storage/uploads/p5a_*/*') ?: [] as $path) {
    $artifacts[] = $path;
}
$counts['artifacts'] = count($artifacts);

$residue = array_filter($counts);
if ($residue) {
    foreach ($residue as $name => $count) {
        fwrite(STDERR, "FAIL: P5A_TEST cleanup residue {$name}={$count}\n");
    }
    exit(1);
}

echo "P5A_TEST cleanup audit: users=0, agencies=0, allocations=0, artifacts=0.\n";
