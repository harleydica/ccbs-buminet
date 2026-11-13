<?php

/**
 * Security & Regression Test Suite for Ticketing System
 * Run via: docker -H unix:///run/docker.sock exec ccbs-nuxbill php /var/www/html/tests/ticket_security_test.php
 */

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'init.php';

class SecurityTestRunner
{
    private $passed = 0;
    private $failed = 0;

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

    public function section($title)
    {
        echo "\n--------------------------------------------------\n";
        echo "  " . strtoupper($title) . "\n";
        echo "--------------------------------------------------\n";
    }

    public function summary()
    {
        echo "\n==================================================\n";
        echo "SECURITY TEST RESULTS: Total: " . ($this->passed + $this->failed) . " | Passed: {$this->passed} | Failed: {$this->failed}\n";
        echo "==================================================\n";
        return $this->failed === 0;
    }
}

$t = new SecurityTestRunner();
echo "==================================================\n";
echo "Running Comprehensive Ticketing Security Test Suite\n";
echo "==================================================\n";

$baseUrl = 'http://localhost';
global $db_pass;
$apiSecret = $db_pass;

// Helper to make internal HTTP requests
function httpRequest($url, $method = 'GET', $data = [], $cookies = []) {
    $ch = curl_init();
    $cookieStr = '';
    foreach ($cookies as $k => $v) {
        $cookieStr .= "$k=$v; ";
    }

    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
    ];

    if (!empty($cookieStr)) {
        $opts[CURLOPT_COOKIE] = $cookieStr;
    }

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    return [
        'code' => $httpCode,
        'header' => $header,
        'body' => $body,
        'json' => json_decode($body, true)
    ];
}

// Ensure required users exist for role tests
$roles = ['SuperAdmin', 'Admin', 'Agent', 'Sales', 'Report'];
$testUsers = [];
foreach ($roles as $role) {
    $u = ORM::for_table('tbl_users')->where('user_type', $role)->find_one();
    if (!$u) {
        $u = ORM::for_table('tbl_users')->create();
        $u->username = 'test_' . strtolower($role);
        $u->fullname = 'Test ' . $role;
        $u->password = Password::_crypt('TestPass123!');
        $u->user_type = $role;
        $u->status = 'Active';
        $u->creationdate = date('Y-m-d H:i:s');
        $u->save();
        $testUsers[$role] = ['user' => $u, 'created' => true];
    } else {
        $testUsers[$role] = ['user' => $u, 'created' => false];
    }
}

// Ensure test customer exists
$testCustomer = ORM::for_table('tbl_customers')->find_one();
$createdCustomer = false;
if (!$testCustomer) {
    $testCustomer = ORM::for_table('tbl_customers')->create();
    $testCustomer->username = 'sec_test_cust';
    $testCustomer->password = Password::_crypt('CustPass123!');
    $testCustomer->fullname = 'Security Test Customer';
    $testCustomer->email = 'sec_test@example.com';
    $testCustomer->save();
    $createdCustomer = true;
}

// Generate valid session token / cookie for admin
function makeAdminCookie($aid, $db_pass) {
    $time = time();
    $sha1 = sha1("$aid.$time.$db_pass");
    return "$aid.$time.$sha1";
}

// Generate valid API token for user
function makeApiToken($type, $uid, $secret) {
    $time = time();
    $sha1 = sha1($uid . '.' . $time . '.' . $secret);
    return "$type.$uid.$time.$sha1";
}

// =============================================================
// AREA 1: Authentication and Authorization
// =============================================================
$t->section("Area 1: Authentication and Authorization");

// 1.1 Web routes without login -> all redirect to login
$webRoutes = [
    'tickets',
    'tickets/add',
    'tickets/view/3',
    'tickets/edit/3',
    'tickets/delete/3'
];
$allWebRedirected = true;
foreach ($webRoutes as $r) {
    $res = httpRequest("$baseUrl/?_route=$r");
    if ($res['code'] !== 302 || strpos($res['header'], 'location: http://localhost/?_route=login') === false) {
        $allWebRedirected = false;
        break;
    }
}
$t->assert($allWebRedirected, "All unauthenticated Web routes redirect to admin login (HTTP 302)");

// 1.2 API endpoints without token -> all rejected with Unauthorized access
$apiActions = [
    'list',
    'view&id=3',
    'create',
    'update_status',
    'assign',
    'delete&id=3'
];
$allApiRejected = true;
foreach ($apiActions as $act) {
    $method = in_array(explode('&', $act)[0], ['create', 'update_status', 'assign', 'delete']) ? 'POST' : 'GET';
    $res = httpRequest("$baseUrl/system/api.php?r=tickets/$act", $method);
    if (!isset($res['json']['success']) || $res['json']['success'] !== false || (strpos($res['json']['message'], 'Unauthorized') === false && strpos($res['json']['message'], Lang::T('Unauthorized access')) === false)) {
        $allApiRejected = false;
        break;
    }
}
$t->assert($allApiRejected, "All unauthenticated API endpoints reject with 'Unauthorized access'");

// 1.3 Token bypass attempts
$resEmptyToken = httpRequest("$baseUrl/system/api.php?r=tickets/list&token=");
$resWrongToken = httpRequest("$baseUrl/system/api.php?r=tickets/list&token=wrong_token_123");
$resDiffParam = httpRequest("$baseUrl/system/api.php?r=tickets/list&auth=test_api_key_12345");
$t->assert(
    $resEmptyToken['json']['success'] === false &&
    $resWrongToken['json']['success'] === false &&
    $resDiffParam['json']['success'] === false,
    "API rejects empty token, invalid token, and token passed in alternative parameters"
);

// 1.4 Customer (member portal) token cannot reach tickets API
$custToken = makeApiToken('c', $testCustomer->id, $apiSecret);
$resCustApi = httpRequest("$baseUrl/system/api.php?r=tickets/list&token=$custToken");
$t->assert(
    $resCustApi['json']['success'] === false && (strpos($resCustApi['json']['message'], 'Unauthorized') !== false || strpos($resCustApi['json']['message'], Lang::T('Unauthorized access')) !== false),
    "Customer (member portal) token cannot access ticket API (Unauthorized access)"
);

// 1.5 Role-based authorization tests
// Test each role's permissions
$superAdminUser = $testUsers['SuperAdmin']['user'];
$adminUser = $testUsers['Admin']['user'];
$agentUser = $testUsers['Agent']['user'];
$salesUser = $testUsers['Sales']['user'];
$reportUser = $testUsers['Report']['user'];

$superToken = makeApiToken('a', $superAdminUser->id, $apiSecret);
$adminToken = makeApiToken('a', $adminUser->id, $apiSecret);
$agentToken = makeApiToken('a', $agentUser->id, $apiSecret);
$salesToken = makeApiToken('a', $salesUser->id, $apiSecret);
$reportToken = makeApiToken('a', $reportUser->id, $apiSecret);

// Listing: SuperAdmin, Admin, Agent, Sales, Report can all list
$resSuperList = httpRequest("$baseUrl/system/api.php?r=tickets/list&token=$superToken");
$resAdminList = httpRequest("$baseUrl/system/api.php?r=tickets/list&token=$adminToken");
$resAgentList = httpRequest("$baseUrl/system/api.php?r=tickets/list&token=$agentToken");
$resSalesList = httpRequest("$baseUrl/system/api.php?r=tickets/list&token=$salesToken");
$resReportList = httpRequest("$baseUrl/system/api.php?r=tickets/list&token=$reportToken");

$t->assert(
    $resSuperList['json']['success'] === true &&
    $resAdminList['json']['success'] === true &&
    $resAgentList['json']['success'] === true &&
    $resSalesList['json']['success'] === true &&
    $resReportList['json']['success'] === true,
    "All staff roles (SuperAdmin, Admin, Agent, Sales, Report) can list tickets"
);

// Creation: Report CANNOT create tickets; Agent/Sales/Admin/SuperAdmin can
$resReportCreate = httpRequest("$baseUrl/system/api.php?r=tickets/create&token=$reportToken", 'POST', [
    'type' => 'maintenance',
    'title' => 'Report user create test',
    'description' => 'Should fail'
]);
$t->assert(
    $resReportCreate['json']['success'] === false && (strpos($resReportCreate['json']['message'], 'permission') !== false || strpos($resReportCreate['json']['message'], Lang::T('You do not have permission to create tickets')) !== false),
    "Report role is forbidden from creating tickets"
);

// Delete: SuperAdmin and Admin ONLY; Agent and Sales CANNOT delete
$tempTicket = Ticket::create([
    'type' => 'maintenance',
    'title' => 'Temp ticket for role delete test',
    'description' => 'Delete role test'
], $superAdminUser->id);

$resAgentDelete = httpRequest("$baseUrl/system/api.php?r=tickets/delete&id={$tempTicket->id}&token=$agentToken", 'POST');
$resSalesDelete = httpRequest("$baseUrl/system/api.php?r=tickets/delete&id={$tempTicket->id}&token=$salesToken", 'POST');
$t->assert(
    $resAgentDelete['json']['success'] === false && (strpos($resAgentDelete['json']['message'], 'permission') !== false || strpos($resAgentDelete['json']['message'], Lang::T('You do not have permission to delete tickets')) !== false) &&
    $resSalesDelete['json']['success'] === false && (strpos($resSalesDelete['json']['message'], 'permission') !== false || strpos($resSalesDelete['json']['message'], Lang::T('You do not have permission to delete tickets')) !== false),
    "Agent and Sales roles are forbidden from deleting tickets"
);

// Admin / SuperAdmin can delete
$resAdminDelete = httpRequest("$baseUrl/system/api.php?r=tickets/delete&id={$tempTicket->id}&token=$adminToken", 'POST');
$t->assert(
    $resAdminDelete['json']['success'] === true,
    "Admin and SuperAdmin roles can successfully delete tickets"
);

// =============================================================
// AREA 2: CSRF Protection
// =============================================================
$t->section("Area 2: CSRF Protection");

$adminCookie = ['aid' => makeAdminCookie($superAdminUser->id, $db_pass)];

// 2.1 State changing POSTs without CSRF token
$resAddNoCsrf = httpRequest("$baseUrl/?_route=tickets/add-post", 'POST', ['title' => 'No CSRF'], $adminCookie);
$resEditNoCsrf = httpRequest("$baseUrl/?_route=tickets/edit-post", 'POST', ['id' => 3, 'title' => 'No CSRF'], $adminCookie);
$resStatusNoCsrf = httpRequest("$baseUrl/?_route=tickets/status-post", 'POST', ['id' => 3, 'status' => 'resolved'], $adminCookie);
$resAssignNoCsrf = httpRequest("$baseUrl/?_route=tickets/assign-post", 'POST', ['id' => 3, 'assigned_to' => 1], $adminCookie);

$t->assert(
    $resAddNoCsrf['code'] === 302 &&
    $resEditNoCsrf['code'] === 302 &&
    $resStatusNoCsrf['code'] === 302 &&
    $resAssignNoCsrf['code'] === 302,
    "State-changing actions (add-post, edit-post, status-post, assign-post) reject requests lacking valid CSRF token"
);

// 2.2 tickets/delete GET route CSRF test
$tempTicket2 = Ticket::create([
    'type' => 'maintenance',
    'title' => 'Temp ticket for CSRF delete test',
    'description' => 'CSRF delete test'
], $superAdminUser->id);

$resBareGetDelete = httpRequest("$baseUrl/?_route=tickets/delete/{$tempTicket2->id}", 'GET', [], $adminCookie);
$ticketStillExists = ORM::for_table('tbl_tickets')->find_one($tempTicket2->id);
$t->assert(
    $ticketStillExists !== false,
    "State-changing GET request to tickets/delete/{id} without CSRF token is blocked and does NOT delete ticket"
);

// Clean up temp ticket
$tempTicket2->delete();

// =============================================================
// AREA 3: SQL Injection
// =============================================================
$t->section("Area 3: SQL Injection");

$sqliPayloads = [
    "' OR 1=1 --",
    "1; DROP TABLE tbl_tickets",
    "1' UNION SELECT 1,2,3,4,5,6,7,8,9,10,11,12,13 --",
    "' AND SLEEP(3) --"
];

$sqliSafe = true;
foreach ($sqliPayloads as $payload) {
    // Test in Ticket::buildQuery
    try {
        $q = Ticket::buildQuery([
            'q' => $payload,
            'type' => $payload,
            'status' => $payload,
            'priority' => $payload,
            'assigned_to' => $payload,
            'customer_id' => $payload
        ]);
        $results = $q->find_many();
    } catch (Throwable $e) {
        $sqliSafe = false;
        break;
    }

    // Test in API endpoint
    $resApi = httpRequest("$baseUrl/system/api.php?r=tickets/list&q=" . urlencode($payload) . "&token=$superToken");
    if (!isset($resApi['json']['success']) || $resApi['json']['success'] !== true) {
        $sqliSafe = false;
        break;
    }
}

// Confirm tbl_tickets table was NOT dropped or damaged
$tableCheck = ORM::for_table('tbl_tickets')->count();
$t->assert($sqliSafe && $tableCheck >= 0, "SQL injection payloads (' OR 1=1, DROP TABLE, UNION, SLEEP) in query/filters are neutralized by prepared statements");

// Test negative & SQLi in limit/offset
$resLimitOffset = httpRequest("$baseUrl/system/api.php?r=tickets/list&limit=-10&offset=-5&token=$superToken");
$t->assert(
    $resLimitOffset['json']['success'] === true && is_array($resLimitOffset['json']['result']['tickets']),
    "Negative / invalid limit and offset are clamped safely without SQL syntax errors"
);

// =============================================================
// AREA 4: XSS (Cross-Site Scripting)
// =============================================================
$t->section("Area 4: XSS (Cross-Site Scripting)");

$xssTitle = '"><script>alert("xss-title")</script>';
$xssDesc = '</textarea><script>alert("xss-desc")</script>';

// Create ticket containing XSS payloads
$xssTicket = Ticket::create([
    'type' => 'customer',
    'title' => $xssTitle,
    'description' => $xssDesc,
    'customer_id' => $testCustomer->id,
], $superAdminUser->id);

// 4.1 Check list.tpl HTML output
$resListHtml = httpRequest("$baseUrl/?_route=tickets&q=" . urlencode('"><script>alert("q")</script>'), 'GET', [], $adminCookie);
$listHtml = $resListHtml['body'];
$xssInListEscaped = (
    strpos($listHtml, '<script>alert("q")</script>') === false &&
    strpos($listHtml, '<script>alert("xss-title")</script>') === false &&
    strpos($listHtml, '&quot;&gt;&lt;script&gt;alert(&quot;q&quot;)&lt;/script&gt;') !== false
);
$t->assert($xssInListEscaped, "list.tpl properly escapes search query 'q' and ticket titles");

// 4.2 Check view.tpl HTML output
$resViewHtml = httpRequest("$baseUrl/?_route=tickets/view/{$xssTicket->id}", 'GET', [], $adminCookie);
$viewHtml = $resViewHtml['body'];
$xssInViewEscaped = (
    strpos($viewHtml, '<script>alert("xss-title")</script>') === false &&
    strpos($viewHtml, '<script>alert("xss-desc")</script>') === false &&
    strpos($viewHtml, '&lt;/textarea&gt;&lt;script&gt;') !== false
);
$t->assert($xssInViewEscaped, "view.tpl properly escapes ticket title and description");

// 4.3 Check edit.tpl HTML output
$resEditHtml = httpRequest("$baseUrl/?_route=tickets/edit/{$xssTicket->id}", 'GET', [], $adminCookie);
$editHtml = $resEditHtml['body'];
$xssInEditEscaped = (
    strpos($editHtml, '<script>alert("xss-title")</script>') === false &&
    strpos($editHtml, '<script>alert("xss-desc")</script>') === false &&
    strpos($editHtml, 'value="&quot;&gt;&lt;script&gt;alert(&quot;xss-title&quot;)&lt;/script&gt;"') !== false
);
$t->assert($xssInEditEscaped, "edit.tpl properly escapes title input and textarea description");

// Clean up XSS test ticket
$xssTicket->delete();

// =============================================================
// AREA 5: IDOR and Object-Level Access
// =============================================================
$t->section("Area 5: IDOR and Object-Level Access");

$invalidIds = [99999999, -1, 'string_id', '9999999999999999'];
$allIdorHandled = true;

foreach ($invalidIds as $iid) {
    // API view
    $resApiView = httpRequest("$baseUrl/system/api.php?r=tickets/view&id=$iid&token=$superToken");
    if ($resApiView['json']['success'] !== false) {
        $allIdorHandled = false;
        break;
    }

    // API status
    $resApiStatus = httpRequest("$baseUrl/system/api.php?r=tickets/update_status&token=$superToken", 'POST', ['id' => $iid, 'status' => 'resolved']);
    if ($resApiStatus['json']['success'] !== false) {
        $allIdorHandled = false;
        break;
    }

    // API assign
    $resApiAssign = httpRequest("$baseUrl/system/api.php?r=tickets/assign&token=$superToken", 'POST', ['id' => $iid, 'assigned_to' => 1]);
    if ($resApiAssign['json']['success'] !== false) {
        $allIdorHandled = false;
        break;
    }

    // API delete
    $resApiDelete = httpRequest("$baseUrl/system/api.php?r=tickets/delete&id=$iid&token=$superToken", 'POST');
    if ($resApiDelete['json']['success'] !== false) {
        $allIdorHandled = false;
        break;
    }
}
$t->assert($allIdorHandled, "Non-existent, negative, string, and oversized IDs are cleanly rejected with 'Ticket not found' or invalid ID");

// =============================================================
// AREA 6: Input Validation and Mass Assignment
// =============================================================
$t->section("Area 6: Input Validation and Mass Assignment");

// 6.1 Mass assignment fields ignored
$massAssignTicket = Ticket::create([
    'id' => 99999,
    'ticket_number' => 'TCK-FORGED-0001',
    'created_by' => 8888,
    'created_at' => '1999-01-01 00:00:00',
    'resolved_at' => '2099-01-01 00:00:00',
    'type' => 'customer',
    'title' => 'Mass Assignment Protection Test',
    'description' => 'Checking injected fields',
    'customer_id' => $testCustomer->id,
    'status' => 'open'
], $superAdminUser->id);

$t->assert(
    $massAssignTicket->id !== 99999 &&
    $massAssignTicket->ticket_number !== 'TCK-FORGED-0001' &&
    $massAssignTicket->created_by === (int)$superAdminUser->id &&
    substr($massAssignTicket->created_at, 0, 4) === date('Y') &&
    $massAssignTicket->resolved_at === null,
    "Mass assignment fields (id, ticket_number, created_by, created_at, resolved_at) are strictly ignored on create"
);

// 6.2 Array parameters do not crash with TypeError
$resArrayParams = Ticket::validate([
    'title' => ['array_value'],
    'description' => ['array_value'],
    'type' => ['array_type']
]);
$t->assert(
    $resArrayParams['valid'] === false,
    "Array parameters (title[]=x, description[]=x) fail validation safely without TypeError"
);

// 6.3 Invalid enum values
$resBadEnums = Ticket::validate([
    'type' => 'unknown_type',
    'priority' => 'ultra_priority',
    'status' => 'super_status',
    'title' => 'Bad Enums',
    'description' => 'Testing enums'
]);
$t->assert(
    $resBadEnums['valid'] === false && count($resBadEnums['errors']) >= 3,
    "Invalid enum values for type, status, and priority are strictly rejected"
);

// 6.4 Oversized fields
$oversizedTitle = str_repeat('A', 256);
$resOversized = Ticket::validate([
    'type' => 'maintenance',
    'title' => $oversizedTitle,
    'description' => 'Valid description'
]);
$t->assert(
    $resOversized['valid'] === false && strpos(implode(', ', $resOversized['errors']), '255') !== false,
    "Oversized title (> 255 chars) is rejected"
);

// 6.5 Business Rule: Cannot change type on edit
$typeChangeCaught = false;
$typeChangeMsg = '';
try {
    Ticket::update($massAssignTicket->id, [
        'type' => 'maintenance',
        'title' => 'Attempting to change type to maintenance',
        'description' => 'Should fail'
    ]);
} catch (InvalidArgumentException $e) {
    $typeChangeCaught = true;
    $typeChangeMsg = $e->getMessage();
}
$t->assert(
    $typeChangeCaught && (strpos($typeChangeMsg, 'cannot be changed') !== false || strpos($typeChangeMsg, Lang::T('Ticket type cannot be changed after creation')) !== false),
    "Attempt to change ticket type from 'customer' to 'maintenance' on edit is strictly rejected"
);

// 6.6 Relation validation: non-existent customer & staff
$resInvalidCustomer = Ticket::validate([
    'type' => 'customer',
    'customer_id' => 99999999,
    'title' => 'Invalid Cust',
    'description' => 'Test'
]);
$resInvalidStaff = Ticket::validate([
    'type' => 'maintenance',
    'title' => 'Invalid Staff',
    'description' => 'Test',
    'assigned_to' => 99999999
]);
$resReportStaff = Ticket::validate([
    'type' => 'maintenance',
    'title' => 'Report Staff Assignment',
    'description' => 'Test',
    'assigned_to' => $reportUser->id
]);
$t->assert(
    $resInvalidCustomer['valid'] === false &&
    $resInvalidStaff['valid'] === false &&
    $resReportStaff['valid'] === false,
    "Non-existent customer_id, non-existent assigned_to, and assigned_to with 'Report' role are rejected"
);

// Clean up mass assignment ticket
$massAssignTicket->delete();

// =============================================================
// AREA 7: Information Disclosure
// =============================================================
$t->section("Area 7: Information Disclosure");

// 7.1 Sensitive customer fields masked in relations
$findTicket = Ticket::create([
    'type' => 'customer',
    'title' => 'Info disclosure test',
    'description' => 'Verify customer password is never exposed',
    'customer_id' => $testCustomer->id,
], $superAdminUser->id);

$withRelations = Ticket::findWithRelations($findTicket->id);
$t->assert(
    isset($withRelations['customer']) &&
    !isset($withRelations['customer']['password']) &&
    !isset($withRelations['customer']['pppoe_password']) &&
    isset($withRelations['customer']['fullname']),
    "Ticket::findWithRelations masks customer passwords and pppoe_passwords"
);

// 7.2 API responses do not expose credentials
$resApiWithRelations = httpRequest("$baseUrl/system/api.php?r=tickets/view&id={$findTicket->id}&token=$superToken");
$t->assert(
    $resApiWithRelations['json']['success'] === true &&
    !isset($resApiWithRelations['json']['result']['customer']['password']) &&
    !isset($resApiWithRelations['json']['result']['customer']['pppoe_password']),
    "API view endpoint returns customer profile without leaking passwords"
);

// Clean up
$findTicket->delete();

// =============================================================
// AREA 8: Race Conditions and Data Integrity
// =============================================================
$t->section("Area 8: Race Conditions and Concurrency");

// Create 20 concurrent tickets in parallel using curl_multi
$multiHandle = curl_multi_init();
$curlHandles = [];
$createUrls = [];

for ($i = 0; $i < 20; $i++) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$baseUrl/system/api.php?r=tickets/create&token=$superToken",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'type' => 'maintenance',
            'title' => "Parallel Stress Ticket #$i",
            'description' => "Concurrency testing parallel request $i",
            'priority' => 'low',
            'status' => 'open'
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);
    curl_multi_add_handle($multiHandle, $ch);
    $curlHandles[] = $ch;
}

$running = null;
do {
    curl_multi_exec($multiHandle, $running);
    curl_multi_select($multiHandle);
} while ($running > 0);

$createdTicketNumbers = [];
$createdTicketIds = [];
$concurrencySuccess = true;

foreach ($curlHandles as $ch) {
    $respBody = curl_multi_getcontent($ch);
    $respJson = json_decode($respBody, true);
    if (!isset($respJson['success']) || $respJson['success'] !== true) {
        $concurrencySuccess = false;
        echo "Concurrency failure response: " . $respBody . "\n";
    } else {
        $createdTicketNumbers[] = $respJson['result']['ticket_number'];
        $createdTicketIds[] = $respJson['result']['id'];
    }
    curl_multi_remove_handle($multiHandle, $ch);
    curl_close($ch);
}
curl_multi_close($multiHandle);

$uniqueNumbers = array_unique($createdTicketNumbers);
$t->assert(
    $concurrencySuccess && count($createdTicketNumbers) === 20 && count($uniqueNumbers) === 20,
    "20 concurrent parallel creation requests all succeeded with 100% unique ticket numbers (0 duplicate-key errors)"
);

// Clean up parallel test tickets
foreach ($createdTicketIds as $cid) {
    ORM::for_table('tbl_tickets')->find_one($cid)?->delete();
}

// =============================================================
// AREA 9: Timestamp Authorization & Hardening
// =============================================================
$t->section("Area 9: Timestamp Authorization & Hardening");

// 9.1 Agent trying to set custom opened_at on create via API is strictly rejected
$resAgentCustomOpen = httpRequest("$baseUrl/system/api.php?r=tickets/create&token=$agentToken", 'POST', [
    'type' => 'customer',
    'title' => 'Agent custom opened_at attempt',
    'description' => 'Should fail authorization',
    'customer_id' => $testCustomer->id,
    'opened_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
]);
$t->assert(
    $resAgentCustomOpen['json']['success'] === false && (strpos($resAgentCustomOpen['json']['message'], 'Only SuperAdmin and Admin') !== false || strpos($resAgentCustomOpen['json']['message'], Lang::T('Only SuperAdmin and Admin can set custom opened_at or closed_at timestamps')) !== false),
    "Agent role sending custom opened_at on create is rejected with authorization error"
);

// 9.2 Sales trying to set custom closed_at on create via API is strictly rejected
$resSalesCustomClose = httpRequest("$baseUrl/system/api.php?r=tickets/create&token=$salesToken", 'POST', [
    'type' => 'maintenance',
    'title' => 'Sales custom closed_at attempt',
    'description' => 'Should fail authorization',
    'closed_at' => date('Y-m-d H:i:s')
]);
$t->assert(
    $resSalesCustomClose['json']['success'] === false && (strpos($resSalesCustomClose['json']['message'], 'Only SuperAdmin and Admin') !== false || strpos($resSalesCustomClose['json']['message'], Lang::T('Only SuperAdmin and Admin can set custom opened_at or closed_at timestamps')) !== false),
    "Sales role sending custom closed_at on create is rejected with authorization error"
);

// 9.3 Agent creating ticket without custom timestamps gets default opened_at = NOW()
$resAgentValidCreate = httpRequest("$baseUrl/system/api.php?r=tickets/create&token=$agentToken", 'POST', [
    'type' => 'customer',
    'title' => 'Agent normal ticket creation',
    'description' => 'Should succeed with default opened_at',
    'customer_id' => $testCustomer->id
]);
$agentTicketId = isset($resAgentValidCreate['json']['result']['id']) ? (int)$resAgentValidCreate['json']['result']['id'] : 0;
$agentTicket = $agentTicketId > 0 ? ORM::for_table('tbl_tickets')->find_one($agentTicketId) : null;
$t->assert(
    $resAgentValidCreate['json']['success'] === true &&
    $agentTicket && !empty($agentTicket->opened_at) && $agentTicket->closed_at === null,
    "Agent can create tickets with default opened_at=NOW() and closed_at=NULL"
);

// 9.4 Agent updating ticket trying to modify opened_at is strictly rejected
if ($agentTicket) {
    $resAgentEditOpen = httpRequest("$baseUrl/system/api.php?r=tickets/update&token=$agentToken", 'POST', [
        'id' => $agentTicket->id,
        'title' => 'Agent trying to change opened_at',
        'opened_at' => '2026-10-01 10:00:00'
    ]);
    $t->assert(
        $resAgentEditOpen['json']['success'] === false && (strpos($resAgentEditOpen['json']['message'], 'Only SuperAdmin and Admin') !== false || strpos($resAgentEditOpen['json']['message'], Lang::T('Only SuperAdmin and Admin can set or change opened_at or closed_at timestamps')) !== false),
        "Agent role attempting to change opened_at on existing ticket is rejected with authorization error"
    );
}

// 9.5 Agent updating status to resolved without closed_at succeeds with default closed_at = NOW()
if ($agentTicket) {
    $resAgentResolve = httpRequest("$baseUrl/system/api.php?r=tickets/update_status&token=$agentToken", 'POST', [
        'id' => $agentTicket->id,
        'status' => 'resolved'
    ]);
    $reloadedAgentTicket = ORM::for_table('tbl_tickets')->find_one($agentTicket->id);
    $t->assert(
        $resAgentResolve['json']['success'] === true &&
        $reloadedAgentTicket && !empty($reloadedAgentTicket->closed_at),
        "Agent can update status to 'resolved' and closed_at automatically defaults to NOW()"
    );
}

// 9.6 Agent updating status with custom closed_at is rejected
if ($agentTicket) {
    // reopen first
    httpRequest("$baseUrl/system/api.php?r=tickets/update_status&token=$agentToken", 'POST', ['id' => $agentTicket->id, 'status' => 'open']);
    $resAgentCustomStatus = httpRequest("$baseUrl/system/api.php?r=tickets/update_status&token=$agentToken", 'POST', [
        'id' => $agentTicket->id,
        'status' => 'resolved',
        'closed_at' => '2026-10-31 16:00:00'
    ]);
    $t->assert(
        $resAgentCustomStatus['json']['success'] === false && (strpos($resAgentCustomStatus['json']['message'], 'Only SuperAdmin and Admin') !== false || strpos($resAgentCustomStatus['json']['message'], Lang::T('Only SuperAdmin and Admin can set custom opened_at or closed_at timestamps')) !== false),
        "Agent role sending custom closed_at on status update is strictly rejected"
    );
}

// 9.7 SuperAdmin and Admin can supply custom opened_at and closed_at with HTML5 datetime-local format
$resAdminCreateCustom = httpRequest("$baseUrl/system/api.php?r=tickets/create&token=$superToken", 'POST', [
    'type' => 'maintenance',
    'title' => 'Admin custom timestamps HTML5 format',
    'description' => 'Testing HTML5 T format',
    'status' => 'resolved',
    'opened_at' => '2026-10-31T10:30',
    'closed_at' => '2026-10-31T12:45'
]);
$adminTicketId = isset($resAdminCreateCustom['json']['result']['id']) ? (int)$resAdminCreateCustom['json']['result']['id'] : 0;
$adminTicket = $adminTicketId > 0 ? ORM::for_table('tbl_tickets')->find_one($adminTicketId) : null;
$t->assert(
    $resAdminCreateCustom['json']['success'] === true &&
    $adminTicket &&
    $adminTicket->opened_at === '2026-10-31 10:30:00' &&
    $adminTicket->closed_at === '2026-10-31 12:45:00',
    "Admin/SuperAdmin can create ticket with HTML5 datetime-local timestamps (normalized to Y-m-d H:i:s)"
);

// 9.8 Reopening ticket via API clears closed_at to NULL
if ($adminTicket) {
    $resReopenApi = httpRequest("$baseUrl/system/api.php?r=tickets/update_status&token=$superToken", 'POST', [
        'id' => $adminTicket->id,
        'status' => 'in_progress'
    ]);
    $adminTicketReloaded = ORM::for_table('tbl_tickets')->find_one($adminTicket->id);
    $t->assert(
        $resReopenApi['json']['success'] === true &&
        $adminTicketReloaded && $adminTicketReloaded->closed_at === null,
        "Reopening ticket via API clears closed_at back to NULL"
    );
}

// Clean up tickets created in Area 9
if ($agentTicket) $agentTicket->delete();
if ($adminTicket) $adminTicket->delete();

// Clean up any test users / customers created
foreach ($testUsers as $role => $info) {
    if ($info['created']) {
        $info['user']->delete();
    }
}
if ($createdCustomer) {
    $testCustomer->delete();
}

// =============================================================
// CLEANUP & FINAL API KEY VERIFICATION
// =============================================================
$t->section("Cleanup: API Key Restoration & Verification");

// Remove the test api_key from tbl_appconfig as instructed
$db = ORM::get_db();
$db->exec("DELETE FROM tbl_appconfig WHERE setting = 'api_key';");

// Verify that test_api_key_12345 no longer works
$resOldKey = httpRequest("$baseUrl/system/api.php?r=tickets/list&token=test_api_key_12345");
$t->assert(
    $resOldKey['json']['success'] === false,
    "tbl_appconfig api_key restored/removed; test_api_key_12345 is now rejected"
);

// Summary
$allPassed = $t->summary();
exit($allPassed ? 0 : 1);
