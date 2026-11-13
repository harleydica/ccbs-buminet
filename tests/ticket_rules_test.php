<?php

/**
 * Automated Test Suite for Ticketing System Rules & Constraints
 * Run via: php tests/ticket_rules_test.php
 */

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'init.php';

class TestRunner
{
    private $passed = 0;
    private $failed = 0;
    private $tests = [];

    public function assert($condition, $testName, $failureDetails = '')
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$testName}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$testName}\n";
            if (!empty($failureDetails)) {
                echo "         Details: {$failureDetails}\n";
            }
        }
    }

    public function summary()
    {
        echo "\n==================================================\n";
        echo "TEST RESULTS: Total: " . ($this->passed + $this->failed) . " | Passed: {$this->passed} | Failed: {$this->failed}\n";
        echo "==================================================\n";
        return $this->failed === 0;
    }
}

$t = new TestRunner();
echo "==================================================\n";
echo "Running Ticketing System Rules & Validation Tests\n";
echo "==================================================\n\n";

// Ensure a customer exists for testing
$customer = ORM::for_table('tbl_customers')->find_one();
if (!$customer) {
    $customer = ORM::for_table('tbl_customers')->create();
    $customer->username = 'test_cust';
    $customer->password = Password::_crypt('test1234');
    $customer->fullname = 'Test Customer';
    $customer->email = 'test@example.com';
    $customer->save();
}

// Ensure an admin/staff exists for testing
$admin = ORM::for_table('tbl_users')->find_one();
$adminId = $admin ? $admin->id : 1;

// -------------------------------------------------------------
// Test 1: Customer ticket without customer_id is rejected
// -------------------------------------------------------------
$caught = false;
$errorMsg = '';
try {
    Ticket::create([
        'type' => 'customer',
        'title' => 'No customer provided',
        'description' => 'Should fail validation',
        'customer_id' => null
    ], $adminId);
} catch (InvalidArgumentException $e) {
    $caught = true;
    $errorMsg = $e->getMessage();
}
$t->assert($caught && (strpos($errorMsg, 'Customer is required') !== false || strpos($errorMsg, Lang::T('Customer is required for customer tickets')) !== false), 
    "Customer ticket without customer_id is rejected by application validation",
    $errorMsg
);

// -------------------------------------------------------------
// Test 2: Customer ticket with non-existent customer_id is rejected
// -------------------------------------------------------------
$caught = false;
$errorMsg = '';
try {
    Ticket::create([
        'type' => 'customer',
        'title' => 'Invalid customer id',
        'description' => 'Should fail customer existence check',
        'customer_id' => 99999999
    ], $adminId);
} catch (InvalidArgumentException $e) {
    $caught = true;
    $errorMsg = $e->getMessage();
}
$t->assert($caught && (strpos($errorMsg, 'does not exist') !== false || strpos($errorMsg, Lang::T('Selected customer does not exist')) !== false),
    "Customer ticket with non-existent customer_id is rejected",
    $errorMsg
);

// -------------------------------------------------------------
// Test 3: Maintenance ticket with customer_id is rejected
// -------------------------------------------------------------
$caught = false;
$errorMsg = '';
try {
    Ticket::create([
        'type' => 'maintenance',
        'title' => 'Maintenance with customer attached',
        'description' => 'Should be rejected because maintenance has no customer',
        'customer_id' => $customer->id
    ], $adminId);
} catch (InvalidArgumentException $e) {
    $caught = true;
    $errorMsg = $e->getMessage();
}
$t->assert($caught && (strpos($errorMsg, 'Maintenance tickets cannot have a customer assigned') !== false || strpos($errorMsg, Lang::T('Maintenance tickets cannot have a customer assigned')) !== false),
    "Maintenance ticket with customer_id is strictly rejected by application validation",
    $errorMsg
);

// -------------------------------------------------------------
// Test 4: Valid Customer Ticket is created with required customer
// -------------------------------------------------------------
$customerTicket = null;
try {
    $customerTicket = Ticket::create([
        'type' => 'customer',
        'title' => 'Fiber line broken by road construction',
        'description' => 'Customer reported LOS blinking red.',
        'customer_id' => $customer->id,
        'priority' => 'high',
        'status' => 'open'
    ], $adminId);
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
$t->assert(
    $customerTicket && 
    $customerTicket->type === 'customer' && 
    (int)$customerTicket->customer_id === (int)$customer->id &&
    preg_match('/^TCK-\d{6}-\d{4}$/', $customerTicket->ticket_number),
    "Valid Customer Ticket successfully created with auto ticket number: " . ($customerTicket ? $customerTicket->ticket_number : 'none')
);

// -------------------------------------------------------------
// Test 5: Valid Maintenance Ticket is created with NULL customer
// -------------------------------------------------------------
$maintTicket = null;
try {
    $maintTicket = Ticket::create([
        'type' => 'maintenance',
        'title' => 'Core Router OSPF Reconfiguration',
        'description' => 'Upgrading routing protocol metrics across main POP.',
        'customer_id' => null,
        'priority' => 'medium',
        'status' => 'open'
    ], $adminId);
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
$t->assert(
    $maintTicket && 
    $maintTicket->type === 'maintenance' && 
    $maintTicket->customer_id === null &&
    preg_match('/^TCK-\d{6}-\d{4}$/', $maintTicket->ticket_number),
    "Valid Maintenance Ticket successfully created with NULL customer_id: " . ($maintTicket ? $maintTicket->ticket_number : 'none')
);

// -------------------------------------------------------------
// Test 6: Database CHECK constraint enforcement at MySQL level
// -------------------------------------------------------------
$db = ORM::get_db();

// 6a: MySQL CHECK constraint rejects customer ticket with NULL customer_id
$dbCheck1 = false;
try {
    $stmt = $db->prepare("INSERT INTO tbl_tickets (ticket_number, type, title, description, customer_id, created_by, created_at) VALUES (?, 'customer', 'Raw DB Test', 'Test', NULL, ?, NOW())");
    $stmt->execute(['TCK-RAW-FAIL1', $adminId]);
} catch (PDOException $e) {
    // MySQL error 3819 is CHECK constraint violation
    if ($e->getCode() == 'HY000' || strpos($e->getMessage(), 'chk_ticket_customer') !== false) {
        $dbCheck1 = true;
    }
}
$t->assert($dbCheck1, "MySQL 8 CHECK constraint rejects raw INSERT with type='customer' and customer_id=NULL");

// 6b: MySQL CHECK constraint rejects maintenance ticket with populated customer_id
$dbCheck2 = false;
try {
    $stmt = $db->prepare("INSERT INTO tbl_tickets (ticket_number, type, title, description, customer_id, created_by, created_at) VALUES (?, 'maintenance', 'Raw DB Test', 'Test', ?, ?, NOW())");
    $stmt->execute(['TCK-RAW-FAIL2', $customer->id, $adminId]);
} catch (PDOException $e) {
    if ($e->getCode() == 'HY000' || strpos($e->getMessage(), 'chk_ticket_customer') !== false) {
        $dbCheck2 = true;
    }
}
$t->assert($dbCheck2, "MySQL 8 CHECK constraint rejects raw INSERT with type='maintenance' and customer_id!=NULL");

// -------------------------------------------------------------
// Test 7: Sequential Ticket Number Generation
// -------------------------------------------------------------
$tNum1 = Ticket::generateTicketNumber();
$dummy = ORM::for_table('tbl_tickets')->create();
$dummy->ticket_number = $tNum1;
$dummy->type = 'maintenance';
$dummy->title = 'Dummy Seq Test';
$dummy->description = 'Test';
$dummy->customer_id = null;
$dummy->created_by = $adminId;
$dummy->created_at = date('Y-m-d H:i:s');
$dummy->save();

$tNum2 = Ticket::generateTicketNumber();
$seq1 = (int) substr($tNum1, -4);
$seq2 = (int) substr($tNum2, -4);
$dummy->delete();

$t->assert($seq2 === $seq1 + 1, "Ticket numbers increment sequentially ({$tNum1} -> {$tNum2})");

// -------------------------------------------------------------
// Test 8: Status Transition and resolved_at Lifecycle
// -------------------------------------------------------------
$ticketForStatus = Ticket::create([
    'type' => 'customer',
    'title' => 'Status transition lifecycle test',
    'description' => 'Verifying resolved_at handling',
    'customer_id' => $customer->id,
    'status' => 'open'
], $adminId);

$t->assert($ticketForStatus->status === 'open' && empty($ticketForStatus->resolved_at), "Initial ticket has status=open and resolved_at=NULL");

Ticket::updateStatus($ticketForStatus->id, 'in_progress');
$ticketReloaded = ORM::for_table('tbl_tickets')->find_one($ticketForStatus->id);
$t->assert($ticketReloaded->status === 'in_progress' && empty($ticketReloaded->resolved_at), "Transition to 'in_progress' maintains resolved_at=NULL");

Ticket::updateStatus($ticketForStatus->id, 'resolved');
$ticketReloaded = ORM::for_table('tbl_tickets')->find_one($ticketForStatus->id);
$t->assert($ticketReloaded->status === 'resolved' && !empty($ticketReloaded->resolved_at), "Transition to 'resolved' automatically sets resolved_at timestamp");

Ticket::updateStatus($ticketForStatus->id, 'open');
$ticketReloaded = ORM::for_table('tbl_tickets')->find_one($ticketForStatus->id);
$t->assert($ticketReloaded->status === 'open' && empty($ticketReloaded->resolved_at), "Reopening ticket resets resolved_at back to NULL");

// Clean up test ticket
$ticketForStatus->delete();

// -------------------------------------------------------------
// Test 9: Staff Assignment
// -------------------------------------------------------------
$ticketForAssign = Ticket::create([
    'type' => 'maintenance',
    'title' => 'Assignment test',
    'description' => 'Verifying staff assignment',
    'customer_id' => null,
    'assigned_to' => null
], $adminId);

Ticket::assign($ticketForAssign->id, $adminId);
$ticketReloaded = ORM::for_table('tbl_tickets')->find_one($ticketForAssign->id);
$t->assert((int)$ticketReloaded->assigned_to === (int)$adminId, "Ticket successfully assigned to staff ID: {$adminId}");

Ticket::assign($ticketForAssign->id, null);
$ticketReloaded = ORM::for_table('tbl_tickets')->find_one($ticketForAssign->id);
$t->assert($ticketReloaded->assigned_to === null, "Ticket assignment successfully cleared to unassigned (NULL)");

$ticketForAssign->delete();

// -------------------------------------------------------------
// Test 10: Filtering & Querying by Type and Customer
// -------------------------------------------------------------
$queryCust = Ticket::buildQuery(['type' => 'customer']);
$hasOnlyCust = true;
foreach ($queryCust->find_many() as $row) {
    if ($row->type !== 'customer' || empty($row->customer_id)) {
        $hasOnlyCust = false;
        break;
    }
}
$t->assert($hasOnlyCust, "Ticket::buildQuery(['type' => 'customer']) returns only customer tickets with valid customer_id");

$queryMaint = Ticket::buildQuery(['type' => 'maintenance']);
$hasOnlyMaint = true;
foreach ($queryMaint->find_many() as $row) {
    if ($row->type !== 'maintenance' || $row->customer_id !== null) {
        $hasOnlyMaint = false;
        break;
    }
}
$t->assert($hasOnlyMaint, "Ticket::buildQuery(['type' => 'maintenance']) returns only maintenance tickets with customer_id=NULL");

// Clean up test tickets
if ($customerTicket) $customerTicket->delete();
if ($maintTicket) $maintTicket->delete();

// -------------------------------------------------------------
// Test 11: opened_at defaults to NOW() when not supplied on create
// -------------------------------------------------------------
$tDefaultOpened = Ticket::create([
    'type' => 'customer',
    'title' => 'Default opened_at test',
    'description' => 'Test default opened_at',
    'customer_id' => $customer->id,
    'status' => 'open'
], $adminId);
$t->assert(
    !empty($tDefaultOpened->opened_at) && abs(strtotime($tDefaultOpened->opened_at) - time()) < 10,
    "opened_at defaults to NOW() when omitted on create ({$tDefaultOpened->opened_at})"
);

// -------------------------------------------------------------
// Test 12: Custom valid opened_at (SuperAdmin/Admin) is accepted
// -------------------------------------------------------------
$customOpenedTime = date('Y-m-d H:i:s', strtotime('-2 hours'));
$tCustomOpened = Ticket::create([
    'type' => 'maintenance',
    'title' => 'Custom opened_at test',
    'description' => 'Test custom opened_at',
    'opened_at' => $customOpenedTime,
    'status' => 'open'
], $adminId, 'Admin');
$t->assert(
    $tCustomOpened->opened_at === $customOpenedTime,
    "SuperAdmin/Admin can supply custom opened_at on create ({$tCustomOpened->opened_at})"
);

// -------------------------------------------------------------
// Test 13: closed_at supplied when status is open or in_progress is strictly rejected
// -------------------------------------------------------------
$caughtClosedOpen = false;
$msgClosedOpen = '';
try {
    Ticket::create([
        'type' => 'customer',
        'title' => 'closed_at on open status test',
        'description' => 'Should fail',
        'customer_id' => $customer->id,
        'status' => 'open',
        'closed_at' => date('Y-m-d H:i:s')
    ], $adminId, 'Admin');
} catch (InvalidArgumentException $e) {
    $caughtClosedOpen = true;
    $msgClosedOpen = $e->getMessage();
}
$t->assert(
    $caughtClosedOpen && (strpos($msgClosedOpen, 'only allowed when ticket status is resolved or closed') !== false || strpos($msgClosedOpen, Lang::T('Closed at timestamp is only allowed when ticket status is resolved or closed')) !== false),
    "Supplying closed_at when status is open is rejected with clear error"
);

// -------------------------------------------------------------
// Test 14: Transition to resolved defaults closed_at to NOW() if omitted
// -------------------------------------------------------------
$tTrans = Ticket::create([
    'type' => 'customer',
    'title' => 'Transition resolve closed_at default',
    'description' => 'Testing closed_at default on resolve',
    'customer_id' => $customer->id,
    'status' => 'open'
], $adminId);
Ticket::updateStatus($tTrans->id, 'resolved');
$tTransReload = ORM::for_table('tbl_tickets')->find_one($tTrans->id);
$t->assert(
    !empty($tTransReload->closed_at) && abs(strtotime($tTransReload->closed_at) - time()) < 10,
    "Transition to 'resolved' defaults closed_at to NOW() ({$tTransReload->closed_at})"
);

// -------------------------------------------------------------
// Test 15: Transition to resolved accepts custom closed_at
// -------------------------------------------------------------
$tTrans->opened_at = date('Y-m-d H:i:s', strtotime('-1 hour'));
$tTrans->save();
$customClosedTime = date('Y-m-d H:i:s', strtotime('-30 minutes'));
Ticket::updateStatus($tTrans->id, 'open'); // reopen
Ticket::updateStatus($tTrans->id, 'resolved', $customClosedTime, 'Admin');
$tTransReload2 = ORM::for_table('tbl_tickets')->find_one($tTrans->id);
$t->assert(
    $tTransReload2->closed_at === $customClosedTime,
    "Transition to 'resolved' accepts custom closed_at from Admin ({$tTransReload2->closed_at})"
);

// -------------------------------------------------------------
// Test 16: closed_at < opened_at is strictly rejected
// -------------------------------------------------------------
$caughtOrder = false;
$msgOrder = '';
try {
    Ticket::create([
        'type' => 'customer',
        'title' => 'Order check test',
        'description' => 'Testing closed_at < opened_at',
        'customer_id' => $customer->id,
        'status' => 'resolved',
        'opened_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'closed_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
    ], $adminId, 'Admin');
} catch (InvalidArgumentException $e) {
    $caughtOrder = true;
    $msgOrder = $e->getMessage();
}
$t->assert(
    $caughtOrder && (strpos($msgOrder, 'earlier than opened at date') !== false || strpos($msgOrder, Lang::T('Closed at date cannot be earlier than opened at date')) !== false),
    "closed_at earlier than opened_at is strictly rejected"
);

// -------------------------------------------------------------
// Test 17: Future timestamp (> 5 min tolerance) is rejected
// -------------------------------------------------------------
$caughtFuture = false;
$msgFuture = '';
try {
    Ticket::create([
        'type' => 'customer',
        'title' => 'Future date test',
        'description' => 'Testing future opened_at',
        'customer_id' => $customer->id,
        'opened_at' => date('Y-m-d H:i:s', strtotime('+10 minutes'))
    ], $adminId, 'Admin');
} catch (InvalidArgumentException $e) {
    $caughtFuture = true;
    $msgFuture = $e->getMessage();
}
$t->assert(
    $caughtFuture && (strpos($msgFuture, 'cannot be in the future') !== false || strpos($msgFuture, Lang::T('Opened at timestamp cannot be in the future')) !== false),
    "opened_at beyond 5-minute skew tolerance is rejected"
);

// -------------------------------------------------------------
// Test 18: Timestamp earlier than 2000-01-01 is rejected
// -------------------------------------------------------------
$caughtPast = false;
$msgPast = '';
try {
    Ticket::create([
        'type' => 'customer',
        'title' => 'Past date lower bound test',
        'description' => 'Testing opened_at < 2000-01-01',
        'customer_id' => $customer->id,
        'opened_at' => '1999-12-31 23:59:59'
    ], $adminId, 'Admin');
} catch (InvalidArgumentException $e) {
    $caughtPast = true;
    $msgPast = $e->getMessage();
}
$t->assert(
    $caughtPast && (strpos($msgPast, 'earlier than 2000-01-01') !== false || strpos($msgPast, Lang::T('Opened at timestamp cannot be earlier than 2000-01-01')) !== false),
    "opened_at earlier than 2000-01-01 lower bound is rejected"
);

// -------------------------------------------------------------
// Test 19: Strict format validation rejects malformed / invalid dates
// -------------------------------------------------------------
$badDates = ['2026-02-31 10:00:00', '2026-13-40 00:00:00', 'not-a-date', '2026/10/31 10:00'];
$allBadRejected = true;
foreach ($badDates as $bd) {
    $res = Ticket::validate(['opened_at' => $bd, 'type' => 'maintenance', 'title' => 'T', 'description' => 'D']);
    if ($res['valid'] || (strpos(implode(', ', $res['errors']), 'Invalid opened_at datetime format') === false && strpos(implode(', ', $res['errors']), Lang::T('Invalid opened_at datetime format. Supported formats: Y-m-d H:i:s, Y-m-d H:i, Y-m-d\TH:i:s, Y-m-d\TH:i')) === false)) {
        $allBadRejected = false;
        break;
    }
}
$t->assert($allBadRejected, "Strict datetime validation rejects invalid dates (e.g. Feb 31, month 13, garbage formats)");

// -------------------------------------------------------------
// Test 20: Reopening ticket automatically clears closed_at to NULL
// -------------------------------------------------------------
Ticket::updateStatus($tTrans->id, 'in_progress');
$tReopened = ORM::for_table('tbl_tickets')->find_one($tTrans->id);
$t->assert(
    $tReopened->status === 'in_progress' && $tReopened->closed_at === null,
    "Reopening ticket (transition to in_progress) automatically clears closed_at to NULL"
);

// -------------------------------------------------------------
// Test 21: Rules apply equally to maintenance tickets
// -------------------------------------------------------------
$maintWithTime = Ticket::create([
    'type' => 'maintenance',
    'title' => 'Maintenance timestamp test',
    'description' => 'Checking maintenance timestamps',
    'opened_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
    'status' => 'resolved',
    'closed_at' => date('Y-m-d H:i:s', strtotime('-10 minutes'))
], $adminId, 'Admin');
$t->assert(
    $maintWithTime && $maintWithTime->customer_id === null &&
    !empty($maintWithTime->opened_at) && !empty($maintWithTime->closed_at),
    "Timestamps (opened_at, closed_at) apply cleanly to maintenance tickets while keeping customer_id=NULL"
);

// -------------------------------------------------------------
// Test 22: created_at and resolved_at remain immutable audit timestamps
// -------------------------------------------------------------
$originalCreatedAt = $maintWithTime->created_at;
$originalResolvedAt = $maintWithTime->resolved_at;
Ticket::update($maintWithTime->id, [
    'title' => 'Edited maintenance title',
    'description' => 'Checking audit immutability',
    'created_at' => '2005-01-01 00:00:00',
    'resolved_at' => '2005-01-02 00:00:00'
], 'Admin');
$reloadedAudit = ORM::for_table('tbl_tickets')->find_one($maintWithTime->id);
$t->assert(
    $reloadedAudit->created_at === $originalCreatedAt &&
    $reloadedAudit->resolved_at === $originalResolvedAt,
    "created_at and resolved_at remain completely immutable audit timestamps when updating tickets"
);

// Clean up test tickets
$tDefaultOpened->delete();
$tCustomOpened->delete();
$tTrans->delete();
$maintWithTime->delete();

// Final Test Suite Summary
$success = $t->summary();
exit($success ? 0 : 1);
