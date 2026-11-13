<?php

/**
 * Ticketing System Controller
 * Handles both Admin Web UI and API requests with strict authentication, authorization, and CSRF protection.
 */

if (!isset($isApi)) {
    $isApi = false;
}

// ---------------------------------------------------------
// API Mode Handling
// ---------------------------------------------------------
if ($isApi) {
    // Authenticate Admin user for API access
    if (empty($admin) || empty($admin['id'])) {
        if (!empty($_SESSION['aid'])) {
            $admin = Admin::_info($_SESSION['aid']);
        }
    }

    if (empty($admin) || empty($admin['id']) || !in_array($admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales', 'Report'], true)) {
        showResult(false, Lang::T('Unauthorized access'));
    }

    $adminRole = $admin['user_type'];
    $adminId = (int) $admin['id'];
    $action = isset($routes[1]) ? $routes[1] : '';

    switch ($action) {
        case 'list':
            $filters = [
                'type' => _req('type'),
                'status' => _req('status'),
                'priority' => _req('priority'),
                'assigned_to' => _req('assigned_to'),
                'customer_id' => _req('customer_id'),
                'q' => _req('q'),
            ];
            $query = Ticket::buildQuery($filters);
            $limit = max(1, min(100, (int) _req('limit', 50)));
            $offset = max(0, (int) _req('offset', 0));
            $tickets = $query->limit($limit)->offset($offset)->order_by_desc('id')->find_array();

            // Enrich with customer and assignee names
            foreach ($tickets as &$t) {
                if ($t['type'] === Ticket::TYPE_CUSTOMER && !empty($t['customer_id'])) {
                    $c = ORM::for_table('tbl_customers')->select('fullname')->select('username')->find_one($t['customer_id']);
                    $t['customer_name'] = $c ? $c->fullname : '';
                    $t['customer_username'] = $c ? $c->username : '';
                } else {
                    $t['customer_name'] = null;
                    $t['customer_username'] = null;
                }

                if (!empty($t['assigned_to'])) {
                    $u = ORM::for_table('tbl_users')->select('fullname')->find_one($t['assigned_to']);
                    $t['assigned_name'] = $u ? $u->fullname : '';
                } else {
                    $t['assigned_name'] = null;
                }
            }

            showResult(true, Lang::T('Tickets retrieved'), [
                'tickets' => $tickets,
                'stats' => Ticket::getStats()
            ]);
            break;

        case 'create':
            if (!in_array($adminRole, ['SuperAdmin', 'Admin', 'Agent', 'Sales'], true)) {
                showResult(false, Lang::T('You do not have permission to create tickets'));
            }
            try {
                $ticket = Ticket::create($_POST, $adminId, $adminRole);
                showResult(true, Lang::T('Ticket created successfully'), $ticket->as_array());
            } catch (InvalidArgumentException $e) {
                showResult(false, $e->getMessage());
            } catch (PDOException $e) {
                _log('Ticketing DB Error: ' . $e->getMessage(), 'error');
                showResult(false, Lang::T('A database error occurred. Please try again.'));
            } catch (Throwable $e) {
                showResult(false, $e->getMessage());
            }
            break;

        case 'update':
            if (!in_array($adminRole, ['SuperAdmin', 'Admin', 'Agent', 'Sales'], true)) {
                showResult(false, Lang::T('You do not have permission to update tickets'));
            }
            try {
                $id = (int) _post('id');
                if ($id <= 0) {
                    showResult(false, Lang::T('Invalid ticket ID'));
                }
                $ticket = Ticket::update($id, $_POST, $adminRole);
                showResult(true, Lang::T('Ticket updated successfully'), $ticket->as_array());
            } catch (InvalidArgumentException $e) {
                showResult(false, $e->getMessage());
            } catch (RuntimeException $e) {
                showResult(false, $e->getMessage());
            } catch (PDOException $e) {
                _log('Ticketing DB Error: ' . $e->getMessage(), 'error');
                showResult(false, Lang::T('A database error occurred. Please try again.'));
            } catch (Throwable $e) {
                showResult(false, $e->getMessage());
            }
            break;

        case 'view':
            $id = (int) _req('id');
            if ($id <= 0) {
                showResult(false, Lang::T('Ticket ID is required and must be a valid integer'));
            }
            $ticket = Ticket::findWithRelations($id);
            if (!$ticket) {
                showResult(false, Lang::T('Ticket not found'));
            }
            showResult(true, Lang::T('Ticket retrieved'), $ticket);
            break;

        case 'update_status':
            if (!in_array($adminRole, ['SuperAdmin', 'Admin', 'Agent', 'Sales'], true)) {
                showResult(false, Lang::T('You do not have permission to update tickets'));
            }
            try {
                $id = (int) _post('id');
                if ($id <= 0) {
                    showResult(false, Lang::T('Invalid ticket ID'));
                }
                $status = _post('status');
                $customClosedAt = _post('closed_at');
                $ticket = Ticket::updateStatus($id, $status, $customClosedAt, $adminRole);
                showResult(true, Lang::T('Ticket status updated successfully'), $ticket->as_array());
            } catch (InvalidArgumentException $e) {
                showResult(false, $e->getMessage());
            } catch (PDOException $e) {
                _log('Ticketing DB Error: ' . $e->getMessage(), 'error');
                showResult(false, Lang::T('A database error occurred. Please try again.'));
            } catch (Throwable $e) {
                showResult(false, $e->getMessage());
            }
            break;

        case 'assign':
            if (!in_array($adminRole, ['SuperAdmin', 'Admin', 'Agent', 'Sales'], true)) {
                showResult(false, Lang::T('You do not have permission to assign tickets'));
            }
            try {
                $id = (int) _post('id');
                if ($id <= 0) {
                    showResult(false, Lang::T('Invalid ticket ID'));
                }
                $assignedTo = _post('assigned_to');
                $ticket = Ticket::assign($id, $assignedTo);
                showResult(true, Lang::T('Ticket assigned successfully'), $ticket->as_array());
            } catch (InvalidArgumentException $e) {
                showResult(false, $e->getMessage());
            } catch (PDOException $e) {
                _log('Ticketing DB Error: ' . $e->getMessage(), 'error');
                showResult(false, Lang::T('A database error occurred. Please try again.'));
            } catch (Throwable $e) {
                showResult(false, $e->getMessage());
            }
            break;

        case 'delete':
            if (!in_array($adminRole, ['SuperAdmin', 'Admin'], true)) {
                showResult(false, Lang::T('You do not have permission to delete tickets'));
            }
            try {
                $id = (int) _req('id');
                if ($id <= 0) {
                    showResult(false, Lang::T('Invalid ticket ID'));
                }
                Ticket::delete($id);
                showResult(true, Lang::T('Ticket deleted successfully'));
            } catch (RuntimeException $e) {
                showResult(false, $e->getMessage());
            } catch (PDOException $e) {
                _log('Ticketing DB Error: ' . $e->getMessage(), 'error');
                showResult(false, Lang::T('A database error occurred. Please try again.'));
            } catch (Throwable $e) {
                showResult(false, $e->getMessage());
            }
            break;

        default:
            showResult(false, Lang::T('Unknown API action'));
            break;
    }
    die();
}

// ---------------------------------------------------------
// Web UI Handling (Admin Area)
// ---------------------------------------------------------
_admin();
$admin = Admin::_info();
$ui->assign('_admin', $admin);
$ui->assign('_title', Lang::T('Ticketing'));
$ui->assign('_system_menu', 'tickets');

$adminRole = $admin['user_type'];
$action = isset($routes[1]) ? $routes[1] : '';

switch ($action) {
    case 'add':
        if (!in_array($adminRole, ['SuperAdmin', 'Admin', 'Agent', 'Sales'], true)) {
            r2(U . 'tickets', 'e', Lang::T('You do not have permission to create tickets'));
        }
        $preType = _get('type', Ticket::TYPE_CUSTOMER);
        $preCustomerId = (int) _get('customer_id', 0);

        $customers = ORM::for_table('tbl_customers')->select('id')->select('fullname')->select('username')->order_by_asc('fullname')->find_many();
        $staff = ORM::for_table('tbl_users')->select('id')->select('fullname')->select('user_type')->where_not_equal('user_type', 'Report')->order_by_asc('fullname')->find_many();

        $ui->assign('preType', $preType);
        $ui->assign('preCustomerId', $preCustomerId);
        $ui->assign('customers', $customers);
        $ui->assign('staff', $staff);
        $ui->assign('now_datetime', date('Y-m-d\TH:i'));
        $ui->assign('csrf_token', Csrf::generateAndStoreToken());
        $ui->display('admin/tickets/add.tpl');
        break;

    case 'add-post':
        if (!in_array($adminRole, ['SuperAdmin', 'Admin', 'Agent', 'Sales'], true)) {
            r2(U . 'tickets', 'e', Lang::T('You do not have permission to create tickets'));
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            r2(U . 'tickets/add', 'e', Lang::T('Method Not Allowed'));
        }
        $csrfToken = _post('csrf_token');
        if (empty($csrfToken) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            r2(U . 'tickets/add', 'e', Lang::T('Invalid or Expired CSRF Token'));
        }

        try {
            $ticket = Ticket::create($_POST, $admin['id'], $adminRole);
            r2(U . 'tickets/view/' . $ticket->id, 's', Lang::T('Ticket created successfully') . ': ' . $ticket->ticket_number);
        } catch (InvalidArgumentException $e) {
            r2(U . 'tickets/add', 'e', $e->getMessage());
        } catch (PDOException $e) {
            _log('Ticketing DB Error: ' . $e->getMessage(), 'error');
            r2(U . 'tickets/add', 'e', Lang::T('A database error occurred. Please try again.'));
        } catch (Throwable $e) {
            r2(U . 'tickets/add', 'e', $e->getMessage());
        }
        break;

    case 'view':
        $id = isset($routes[2]) ? (int) $routes[2] : 0;
        if ($id <= 0) {
            r2(U . 'tickets', 'e', Lang::T('Ticket not found'));
        }
        $ticket = Ticket::findWithRelations($id);
        if (!$ticket) {
            r2(U . 'tickets', 'e', Lang::T('Ticket not found'));
        }

        $staff = ORM::for_table('tbl_users')->select('id')->select('fullname')->select('user_type')->where_not_equal('user_type', 'Report')->order_by_asc('fullname')->find_many();

        $ui->assign('t', $ticket);
        $ui->assign('staff', $staff);
        $ui->assign('now_datetime', date('Y-m-d\TH:i'));
        $ui->assign('csrf_token', Csrf::generateAndStoreToken());
        $ui->display('admin/tickets/view.tpl');
        break;

    case 'edit':
        if (!in_array($adminRole, ['SuperAdmin', 'Admin', 'Agent', 'Sales'], true)) {
            r2(U . 'tickets', 'e', Lang::T('You do not have permission to edit tickets'));
        }
        $id = isset($routes[2]) ? (int) $routes[2] : 0;
        if ($id <= 0) {
            r2(U . 'tickets', 'e', Lang::T('Ticket not found'));
        }
        $ticket = Ticket::findWithRelations($id);
        if (!$ticket) {
            r2(U . 'tickets', 'e', Lang::T('Ticket not found'));
        }

        $customers = ORM::for_table('tbl_customers')->select('id')->select('fullname')->select('username')->order_by_asc('fullname')->find_many();
        $staff = ORM::for_table('tbl_users')->select('id')->select('fullname')->select('user_type')->where_not_equal('user_type', 'Report')->order_by_asc('fullname')->find_many();

        $ui->assign('t', $ticket);
        $ui->assign('customers', $customers);
        $ui->assign('staff', $staff);
        $ui->assign('now_datetime', date('Y-m-d\TH:i'));
        $ui->assign('csrf_token', Csrf::generateAndStoreToken());
        $ui->display('admin/tickets/edit.tpl');
        break;

    case 'edit-post':
        if (!in_array($adminRole, ['SuperAdmin', 'Admin', 'Agent', 'Sales'], true)) {
            r2(U . 'tickets', 'e', Lang::T('You do not have permission to edit tickets'));
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            r2(U . 'tickets', 'e', Lang::T('Method Not Allowed'));
        }
        $csrfToken = _post('csrf_token');
        if (empty($csrfToken) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            r2(U . 'tickets', 'e', Lang::T('Invalid or Expired CSRF Token'));
        }

        $id = (int) _post('id');
        if ($id <= 0) {
            r2(U . 'tickets', 'e', Lang::T('Ticket not found'));
        }
        try {
            Ticket::update($id, $_POST, $adminRole);
            r2(U . 'tickets/view/' . $id, 's', Lang::T('Ticket updated successfully'));
        } catch (InvalidArgumentException $e) {
            r2(U . 'tickets/edit/' . $id, 'e', $e->getMessage());
        } catch (PDOException $e) {
            _log('Ticketing DB Error: ' . $e->getMessage(), 'error');
            r2(U . 'tickets/edit/' . $id, 'e', Lang::T('A database error occurred. Please try again.'));
        } catch (Throwable $e) {
            r2(U . 'tickets/edit/' . $id, 'e', $e->getMessage());
        }
        break;

    case 'status-post':
        if (!in_array($adminRole, ['SuperAdmin', 'Admin', 'Agent', 'Sales'], true)) {
            r2(U . 'tickets', 'e', Lang::T('You do not have permission to update tickets'));
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            r2(U . 'tickets', 'e', Lang::T('Method Not Allowed'));
        }
        $csrfToken = _post('csrf_token');
        if (empty($csrfToken) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            r2(U . 'tickets', 'e', Lang::T('Invalid or Expired CSRF Token'));
        }

        $id = (int) _post('id');
        if ($id <= 0) {
            r2(U . 'tickets', 'e', Lang::T('Ticket not found'));
        }
        $status = _post('status');
        $customClosedAt = _post('closed_at');
        try {
            Ticket::updateStatus($id, $status, $customClosedAt, $adminRole);
            r2(U . 'tickets/view/' . $id, 's', Lang::T('Status updated successfully'));
        } catch (InvalidArgumentException $e) {
            r2(U . 'tickets/view/' . $id, 'e', $e->getMessage());
        } catch (PDOException $e) {
            _log('Ticketing DB Error: ' . $e->getMessage(), 'error');
            r2(U . 'tickets/view/' . $id, 'e', Lang::T('A database error occurred. Please try again.'));
        } catch (Throwable $e) {
            r2(U . 'tickets/view/' . $id, 'e', $e->getMessage());
        }
        break;

    case 'assign-post':
        if (!in_array($adminRole, ['SuperAdmin', 'Admin', 'Agent', 'Sales'], true)) {
            r2(U . 'tickets', 'e', Lang::T('You do not have permission to assign tickets'));
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            r2(U . 'tickets', 'e', Lang::T('Method Not Allowed'));
        }
        $csrfToken = _post('csrf_token');
        if (empty($csrfToken) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            r2(U . 'tickets', 'e', Lang::T('Invalid or Expired CSRF Token'));
        }

        $id = (int) _post('id');
        if ($id <= 0) {
            r2(U . 'tickets', 'e', Lang::T('Ticket not found'));
        }
        $assignedTo = _post('assigned_to');
        try {
            Ticket::assign($id, $assignedTo);
            r2(U . 'tickets/view/' . $id, 's', Lang::T('Ticket assigned successfully'));
        } catch (InvalidArgumentException $e) {
            r2(U . 'tickets/view/' . $id, 'e', $e->getMessage());
        } catch (PDOException $e) {
            _log('Ticketing DB Error: ' . $e->getMessage(), 'error');
            r2(U . 'tickets/view/' . $id, 'e', Lang::T('A database error occurred. Please try again.'));
        } catch (Throwable $e) {
            r2(U . 'tickets/view/' . $id, 'e', $e->getMessage());
        }
        break;

    case 'delete':
        if (!in_array($adminRole, ['SuperAdmin', 'Admin'], true)) {
            r2(U . 'tickets', 'e', Lang::T('You do not have permission to delete tickets'));
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            r2(U . 'tickets', 'e', Lang::T('Method Not Allowed: Delete requires HTTP POST'));
        }

        $csrfToken = _post('csrf_token') ?: _post('token');
        if (empty($csrfToken) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            r2(U . 'tickets', 'e', Lang::T('Invalid or Expired CSRF Token'));
        }

        $id = isset($routes[2]) ? (int) $routes[2] : (int) _post('id');
        if ($id <= 0) {
            r2(U . 'tickets', 'e', Lang::T('Ticket not found'));
        }
        try {
            Ticket::delete($id);
            r2(U . 'tickets', 's', Lang::T('Ticket deleted successfully'));
        } catch (RuntimeException $e) {
            r2(U . 'tickets', 'e', $e->getMessage());
        } catch (PDOException $e) {
            _log('Ticketing DB Error: ' . $e->getMessage(), 'error');
            r2(U . 'tickets', 'e', Lang::T('A database error occurred. Please try again.'));
        } catch (Throwable $e) {
            r2(U . 'tickets', 'e', $e->getMessage());
        }
        break;

    default:
        // List tickets with filters
        $filters = [
            'type' => _req('type'),
            'status' => _req('status'),
            'priority' => _req('priority'),
            'assigned_to' => _req('assigned_to'),
            'customer_id' => _req('customer_id'),
            'q' => _req('q'),
        ];

        $query = Ticket::buildQuery($filters);
        $paginator = Paginator::build($query);
        $startpoint = max(0, (int) $paginator['startpoint']);
        $limit = max(1, (int) $paginator['limit']);
        $tickets = $query->offset($startpoint)->limit($limit)->order_by_desc('id')->find_many();

        // Attach customer name and assignee name for template display
        $enrichedTickets = [];
        foreach ($tickets as $t) {
            $item = $t->as_array();
            if ($t->type === Ticket::TYPE_CUSTOMER && !empty($t->customer_id)) {
                $c = ORM::for_table('tbl_customers')->select('fullname')->select('username')->find_one($t->customer_id);
                $item['customer_name'] = $c ? $c->fullname : '-';
                $item['customer_username'] = $c ? $c->username : '';
            } else {
                $item['customer_name'] = null;
                $item['customer_username'] = null;
            }

            if (!empty($t->assigned_to)) {
                $u = ORM::for_table('tbl_users')->select('fullname')->find_one($t->assigned_to);
                $item['assigned_name'] = $u ? $u->fullname : '-';
            } else {
                $item['assigned_name'] = Lang::T('Unassigned');
            }
            $item['opened_at_formatted'] = !empty($t->opened_at) ? date('Y-m-d H:i', strtotime($t->opened_at)) : '-';
            $item['closed_at_formatted'] = !empty($t->closed_at) ? date('Y-m-d H:i', strtotime($t->closed_at)) : '-';
            $enrichedTickets[] = $item;
        }

        $staff = ORM::for_table('tbl_users')->select('id')->select('fullname')->where_not_equal('user_type', 'Report')->order_by_asc('fullname')->find_many();
        $customers = ORM::for_table('tbl_customers')->select('id')->select('fullname')->select('username')->order_by_asc('fullname')->find_many();
        $stats = Ticket::getStats();

        $ui->assign('tickets', $enrichedTickets);
        $ui->assign('paginator', $paginator);
        $ui->assign('filters', $filters);
        $ui->assign('staff', $staff);
        $ui->assign('customers', $customers);
        $ui->assign('stats', $stats);
        $ui->assign('csrf_token', Csrf::generateAndStoreToken());
        $ui->display('admin/tickets/list.tpl');
        break;
}
