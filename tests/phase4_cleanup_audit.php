<?php

require_once __DIR__ . '/../app/application.php';

$clean = in_array('--clean', $argv, true);
$userIds = array_map('intval', array_column(
    dbFetchAll("SELECT id FROM users WHERE LEFT(email_normalized,8)='p4_test_'"),
    'id'
));
$agencyIds = array_map('intval', array_column(
    dbFetchAll("SELECT id FROM agencies WHERE LEFT(name,8)='P4_TEST_'"),
    'id'
));

if ($clean) {
    try {
        if ($userIds) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            dbExecute("DELETE FROM audit_logs WHERE user_id IN ($placeholders)", $userIds);
            dbExecute("DELETE FROM user_agencies WHERE user_id IN ($placeholders)", $userIds);
            dbExecute("DELETE FROM users WHERE id IN ($placeholders)", $userIds);
        }
        if ($agencyIds) {
            $placeholders = implode(',', array_fill(0, count($agencyIds), '?'));
            $dependent = 0;
            foreach (['customers', 'vehicles', 'reservations'] as $table) {
                $row = dbFetchOne("SELECT COUNT(*) total FROM $table WHERE agency_id IN ($placeholders)", $agencyIds);
                $dependent += (int) $row['total'];
            }
            if ($dependent !== 0) {
                fwrite(STDERR, "FAIL: P4_TEST agencies still have dependent fixtures; automatic orphan cleanup stopped safely.\n");
                exit(1);
            }
            dbExecute("DELETE FROM audit_logs WHERE agency_id IN ($placeholders)", $agencyIds);
            dbExecute("DELETE FROM agencies WHERE id IN ($placeholders)", $agencyIds);
        }
    } catch (Throwable $exception) {
        fwrite(STDERR, "FAIL: P4_TEST orphan cleanup failed safely.\n");
        exit(1);
    }
}

$remainingUsers = (int) dbFetchOne("SELECT COUNT(*) total FROM users WHERE LEFT(email_normalized,8)='p4_test_'")['total'];
$remainingAgencies = (int) dbFetchOne("SELECT COUNT(*) total FROM agencies WHERE LEFT(name,8)='P4_TEST_'")['total'];
echo 'P4_TEST fixture audit: users=' . $remainingUsers . ', agencies=' . $remainingAgencies . PHP_EOL;
exit(($remainingUsers === 0 && $remainingAgencies === 0) ? 0 : 1);
