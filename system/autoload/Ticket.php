<?php

/**
 * Ticketing System Model & Domain Service
 * Handles business rules, validation, number generation, concurrency, and queries for tickets.
 */

class Ticket
{
    const TYPE_CUSTOMER = 'customer';
    const TYPE_MAINTENANCE = 'maintenance';

    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Generate unique sequential ticket number: TCK-YYYYMM-XXXX
     */
    public static function generateTicketNumber()
    {
        $prefix = 'TCK-' . date('Ym') . '-';
        $lastTicket = ORM::for_table('tbl_tickets')
            ->where_like('ticket_number', $prefix . '%')
            ->order_by_desc('ticket_number')
            ->find_one();

        if ($lastTicket && !empty($lastTicket->ticket_number)) {
            $lastSeq = (int) substr($lastTicket->ticket_number, strlen($prefix));
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Parse and normalize datetime input
     * Supported formats: Y-m-d H:i:s, Y-m-d\TH:i:s, Y-m-d H:i, Y-m-d\TH:i
     * Returns string Y-m-d H:i:s on success, null on empty, false on invalid format or overflow
     */
    public static function parseDateTime($val)
    {
        if (!is_string($val)) {
            return false;
        }
        $val = trim($val);
        if ($val === '') {
            return null;
        }

        $formats = [
            '!Y-m-d H:i:s',
            '!Y-m-d\TH:i:s',
            '!Y-m-d H:i',
            '!Y-m-d\TH:i',
        ];

        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $val);
            if ($dt !== false) {
                $errs = DateTime::getLastErrors();
                if ($errs === false || ($errs['warning_count'] === 0 && $errs['error_count'] === 0)) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }
        }

        return false;
    }

    /**
     * Validate ticket input against business rules
     */
    public static function validate(array $data, $isUpdate = false, $existingTicket = null, $userRole = null)
    {
        $errors = [];
        $sanitized = [];

        if ($userRole === null && !empty($_SESSION['aid'])) {
            $currentAdmin = ORM::for_table('tbl_users')->find_one($_SESSION['aid']);
            if ($currentAdmin) {
                $userRole = $currentAdmin->user_type;
            }
        }

        // Authorization check for roles
        if ($userRole === 'Report') {
            $errors[] = Lang::T('Report users cannot create or modify tickets');
        } elseif (in_array($userRole, ['Agent', 'Sales'], true)) {
            if (!$isUpdate) {
                if (isset($data['opened_at']) && trim((string)$data['opened_at']) !== '') {
                    $errors[] = Lang::T('Only SuperAdmin and Admin can set custom opened_at or closed_at timestamps');
                }
                if (isset($data['closed_at']) && trim((string)$data['closed_at']) !== '') {
                    $errors[] = Lang::T('Only SuperAdmin and Admin can set custom opened_at or closed_at timestamps');
                }
            } else {
                if (isset($data['opened_at']) && trim((string)$data['opened_at']) !== '') {
                    $parsed = self::parseDateTime($data['opened_at']);
                    if ($parsed === false) {
                        $errors[] = Lang::T('Invalid opened_at datetime format. Supported formats: Y-m-d H:i:s, Y-m-d H:i, Y-m-d\TH:i:s, Y-m-d\TH:i');
                    } elseif ($existingTicket && $parsed !== $existingTicket->opened_at) {
                        $errors[] = Lang::T('Only SuperAdmin and Admin can set or change opened_at or closed_at timestamps');
                    }
                }
                if (isset($data['closed_at']) && trim((string)$data['closed_at']) !== '') {
                    $parsed = self::parseDateTime($data['closed_at']);
                    if ($parsed === false) {
                        $errors[] = Lang::T('Invalid closed_at datetime format. Supported formats: Y-m-d H:i:s, Y-m-d H:i, Y-m-d\TH:i:s, Y-m-d\TH:i');
                    } elseif ($existingTicket && $parsed !== $existingTicket->closed_at) {
                        $errors[] = Lang::T('Only SuperAdmin and Admin can set or change opened_at or closed_at timestamps');
                    }
                }
            }
        }

        // Title
        $title = (isset($data['title']) && is_string($data['title'])) ? trim($data['title']) : '';
        if ($title === '') {
            $errors[] = Lang::T('Title is required');
        } elseif (mb_strlen($title) > 255) {
            $errors[] = Lang::T('Title must not exceed 255 characters');
        }
        $sanitized['title'] = $title;

        // Description
        $description = (isset($data['description']) && is_string($data['description'])) ? trim($data['description']) : '';
        if ($description === '') {
            $errors[] = Lang::T('Description is required');
        } elseif (mb_strlen($description) > 65535) {
            $errors[] = Lang::T('Description must not exceed 65535 characters');
        }
        $sanitized['description'] = $description;

        // Type
        if ($isUpdate && $existingTicket) {
            if (isset($data['type']) && is_string($data['type']) && $data['type'] !== $existingTicket->type) {
                $errors[] = Lang::T('Ticket type cannot be changed after creation');
            }
            $type = $existingTicket->type;
        } else {
            $type = (isset($data['type']) && is_string($data['type'])) ? trim($data['type']) : '';
            if (!in_array($type, [self::TYPE_CUSTOMER, self::TYPE_MAINTENANCE], true)) {
                $errors[] = Lang::T('Ticket type must be either customer or maintenance');
            }
        }
        $sanitized['type'] = $type;

        // Business Rule: customer_id
        if ($type === self::TYPE_CUSTOMER) {
            if (isset($data['customer_id']) && is_numeric($data['customer_id'])) {
                $customerId = (int) $data['customer_id'];
                if ($customerId <= 0) {
                    $errors[] = Lang::T('Customer is required for customer tickets');
                } else {
                    $customerExists = ORM::for_table('tbl_customers')->find_one($customerId);
                    if (!$customerExists) {
                        $errors[] = Lang::T('Selected customer does not exist');
                    } else {
                        $sanitized['customer_id'] = $customerId;
                    }
                }
            } elseif ($isUpdate && $existingTicket && !empty($existingTicket->customer_id)) {
                $sanitized['customer_id'] = (int) $existingTicket->customer_id;
            } else {
                $errors[] = Lang::T('Customer is required for customer tickets');
            }
        } elseif ($type === self::TYPE_MAINTENANCE) {
            // Maintenance ticket must NOT accept or populate customer_id
            if (isset($data['customer_id']) && $data['customer_id'] !== '' && $data['customer_id'] !== null && $data['customer_id'] != 0) {
                $errors[] = Lang::T('Maintenance tickets cannot have a customer assigned');
            }
            $sanitized['customer_id'] = null;
        }

        // Priority
        if (isset($data['priority'])) {
            $priority = is_string($data['priority']) ? trim($data['priority']) : '';
            if (!in_array($priority, [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_URGENT], true)) {
                $errors[] = Lang::T('Invalid priority value');
            }
            $sanitized['priority'] = $priority;
        } else {
            $sanitized['priority'] = ($isUpdate && $existingTicket) ? $existingTicket->priority : self::PRIORITY_MEDIUM;
        }

        // Status
        if (isset($data['status'])) {
            $status = is_string($data['status']) ? trim($data['status']) : '';
            if (!in_array($status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_CLOSED], true)) {
                $errors[] = Lang::T('Invalid status value');
            }
            $sanitized['status'] = $status;
        } else {
            $sanitized['status'] = ($isUpdate && $existingTicket) ? $existingTicket->status : self::STATUS_OPEN;
        }

        // Assigned To
        if (isset($data['assigned_to']) && $data['assigned_to'] !== '' && $data['assigned_to'] !== null && $data['assigned_to'] !== 'unassigned') {
            if (!is_numeric($data['assigned_to'])) {
                $errors[] = Lang::T('Invalid assigned staff member');
            } else {
                $assignedTo = (int) $data['assigned_to'];
                $staffExists = ORM::for_table('tbl_users')->find_one($assignedTo);
                if (!$staffExists || $staffExists->user_type === 'Report') {
                    $errors[] = Lang::T('Assigned staff member does not exist or cannot handle tickets');
                } else {
                    $sanitized['assigned_to'] = $assignedTo;
                }
            }
        } elseif (array_key_exists('assigned_to', $data)) {
            $sanitized['assigned_to'] = null;
        } else {
            $sanitized['assigned_to'] = ($isUpdate && $existingTicket) ? $existingTicket->assigned_to : null;
        }

        $minDate = '2000-01-01 00:00:00';
        $maxTime = time() + 300; // 5 minutes clock-skew tolerance

        // Opened At
        $openedAt = null;
        if (isset($data['opened_at']) && trim((string)$data['opened_at']) !== '') {
            $parsedOpenedAt = self::parseDateTime($data['opened_at']);
            if ($parsedOpenedAt === false) {
                $errors[] = Lang::T('Invalid opened_at datetime format. Supported formats: Y-m-d H:i:s, Y-m-d H:i, Y-m-d\TH:i:s, Y-m-d\TH:i');
            } else {
                if ($parsedOpenedAt < $minDate) {
                    $errors[] = Lang::T('Opened at timestamp cannot be earlier than 2000-01-01');
                } elseif (strtotime($parsedOpenedAt) > $maxTime) {
                    $errors[] = Lang::T('Opened at timestamp cannot be in the future');
                } else {
                    $openedAt = $parsedOpenedAt;
                }
            }
        } else {
            if ($isUpdate && $existingTicket && !empty($existingTicket->opened_at)) {
                $openedAt = $existingTicket->opened_at;
            } else {
                $openedAt = date('Y-m-d H:i:s');
            }
        }
        $sanitized['opened_at'] = $openedAt;

        // Closed At
        $closedAt = null;
        $isOpenOrInProgress = in_array($sanitized['status'], [self::STATUS_OPEN, self::STATUS_IN_PROGRESS], true);
        $isResolvedOrClosed = in_array($sanitized['status'], [self::STATUS_RESOLVED, self::STATUS_CLOSED], true);

        if ($isOpenOrInProgress) {
            if (isset($data['closed_at']) && trim((string)$data['closed_at']) !== '') {
                $errors[] = Lang::T('Closed at timestamp is only allowed when ticket status is resolved or closed');
            }
            $sanitized['closed_at'] = null;
        } elseif ($isResolvedOrClosed) {
            if (isset($data['closed_at']) && trim((string)$data['closed_at']) !== '') {
                $parsedClosedAt = self::parseDateTime($data['closed_at']);
                if ($parsedClosedAt === false) {
                    $errors[] = Lang::T('Invalid closed_at datetime format. Supported formats: Y-m-d H:i:s, Y-m-d H:i, Y-m-d\TH:i:s, Y-m-d\TH:i');
                } else {
                    if ($parsedClosedAt < $minDate) {
                        $errors[] = Lang::T('Closed at timestamp cannot be earlier than 2000-01-01');
                    } elseif (strtotime($parsedClosedAt) > $maxTime) {
                        $errors[] = Lang::T('Closed at timestamp cannot be in the future');
                    } else {
                        $closedAt = $parsedClosedAt;
                    }
                }
            } else {
                // Not supplied or empty
                if ($isUpdate && $existingTicket && in_array($existingTicket->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true) && !empty($existingTicket->closed_at) && (!isset($data['status']) || $data['status'] === $existingTicket->status)) {
                    if (!array_key_exists('closed_at', $data)) {
                        $closedAt = $existingTicket->closed_at;
                    } else {
                        $closedAt = date('Y-m-d H:i:s');
                    }
                } else {
                    $closedAt = date('Y-m-d H:i:s');
                }
            }
            $sanitized['closed_at'] = $closedAt;
        }

        // Order check: closed_at >= opened_at
        if (!empty($sanitized['closed_at']) && !empty($sanitized['opened_at'])) {
            if (strtotime($sanitized['closed_at']) < strtotime($sanitized['opened_at'])) {
                $errors[] = Lang::T('Closed at date cannot be earlier than opened at date');
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $sanitized
        ];
    }

    /**
     * Create a new ticket with concurrency locking
     */
    public static function create(array $data, $createdByAdminId = 1, $userRole = null)
    {
        if ($userRole === null && $createdByAdminId > 0) {
            $creator = ORM::for_table('tbl_users')->find_one($createdByAdminId);
            if ($creator) {
                $userRole = $creator->user_type;
            }
        }

        $validation = self::validate($data, false, null, $userRole);
        if (!$validation['valid']) {
            throw new InvalidArgumentException(implode(', ', $validation['errors']));
        }

        $v = $validation['data'];
        $db = ORM::get_db();
        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $lockAcquired = false;
            try {
                $lockStmt = $db->query("SELECT GET_LOCK('ticket_seq_lock', 10)");
                $lockAcquired = $lockStmt && ($lockStmt->fetchColumn() == 1);

                $ticket = ORM::for_table('tbl_tickets')->create();
                $ticket->ticket_number = self::generateTicketNumber();
                $ticket->type = $v['type'];
                $ticket->title = $v['title'];
                $ticket->description = $v['description'];
                $ticket->customer_id = $v['customer_id'];
                $ticket->status = $v['status'];
                $ticket->priority = $v['priority'];
                $ticket->created_by = (int) $createdByAdminId;
                $ticket->assigned_to = $v['assigned_to'];
                $ticket->created_at = date('Y-m-d H:i:s');
                $ticket->updated_at = null;
                $ticket->resolved_at = in_array($v['status'], [self::STATUS_RESOLVED, self::STATUS_CLOSED], true) ? date('Y-m-d H:i:s') : null;
                $ticket->opened_at = $v['opened_at'];
                $ticket->closed_at = $v['closed_at'];

                $ticket->save();

                if ($lockAcquired) {
                    $db->query("SELECT RELEASE_LOCK('ticket_seq_lock')");
                    $lockAcquired = false;
                }

                if (function_exists('run_hook')) {
                    run_hook('ticket_created', $ticket);
                }

                return $ticket;
            } catch (PDOException $e) {
                if ($lockAcquired) {
                    @$db->query("SELECT RELEASE_LOCK('ticket_seq_lock')");
                    $lockAcquired = false;
                }
                if (($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) && $attempt < $maxAttempts) {
                    usleep(rand(20000, 80000));
                    continue;
                }
                throw $e;
            } finally {
                if ($lockAcquired) {
                    @$db->query("SELECT RELEASE_LOCK('ticket_seq_lock')");
                }
            }
        }

        throw new RuntimeException(Lang::T('Failed to generate unique ticket number after multiple attempts'));
    }

    /**
     * Update an existing ticket
     */
    public static function update($id, array $data, $userRole = null)
    {
        $id = (int) $id;
        if ($id <= 0) {
            throw new RuntimeException(Lang::T('Ticket not found'));
        }

        $ticket = ORM::for_table('tbl_tickets')->find_one($id);
        if (!$ticket) {
            throw new RuntimeException(Lang::T('Ticket not found'));
        }

        if ($userRole === null && !empty($_SESSION['aid'])) {
            $currentAdmin = ORM::for_table('tbl_users')->find_one($_SESSION['aid']);
            if ($currentAdmin) {
                $userRole = $currentAdmin->user_type;
            }
        }

        $validation = self::validate($data, true, $ticket, $userRole);
        if (!$validation['valid']) {
            throw new InvalidArgumentException(implode(', ', $validation['errors']));
        }

        $v = $validation['data'];
        $oldStatus = $ticket->status;
        $oldAssigned = $ticket->assigned_to;

        $ticket->title = $v['title'];
        $ticket->description = $v['description'];
        if ($ticket->type === self::TYPE_CUSTOMER && isset($v['customer_id'])) {
            $ticket->customer_id = $v['customer_id'];
        } else {
            $ticket->customer_id = null;
        }

        $ticket->status = $v['status'];
        $ticket->priority = $v['priority'];
        $ticket->assigned_to = $v['assigned_to'];
        $ticket->updated_at = date('Y-m-d H:i:s');

        // Manage resolved_at timestamp (immutable audit)
        if (in_array($v['status'], [self::STATUS_RESOLVED, self::STATUS_CLOSED], true) && empty($ticket->resolved_at)) {
            $ticket->resolved_at = date('Y-m-d H:i:s');
        } elseif (!in_array($v['status'], [self::STATUS_RESOLVED, self::STATUS_CLOSED], true)) {
            $ticket->resolved_at = null;
        }

        // Manage custom opened_at & closed_at timestamps
        $ticket->opened_at = $v['opened_at'];
        $ticket->closed_at = $v['closed_at'];

        $ticket->save();

        if (function_exists('run_hook')) {
            run_hook('ticket_updated', $ticket);
            if ($oldStatus !== $ticket->status) {
                run_hook('ticket_status_changed', [
                    'ticket' => $ticket,
                    'old_status' => $oldStatus,
                    'new_status' => $ticket->status
                ]);
            }
            if ($oldAssigned !== $ticket->assigned_to) {
                run_hook('ticket_assigned', [
                    'ticket' => $ticket,
                    'old_assigned_to' => $oldAssigned,
                    'new_assigned_to' => $ticket->assigned_to
                ]);
            }
        }

        return $ticket;
    }

    /**
     * Quick status transition
     */
    public static function updateStatus($id, $newStatus, $customClosedAt = null, $userRole = null)
    {
        $id = (int) $id;
        if ($id <= 0) {
            throw new RuntimeException(Lang::T('Ticket not found'));
        }

        $ticket = ORM::for_table('tbl_tickets')->find_one($id);
        if (!$ticket) {
            throw new RuntimeException(Lang::T('Ticket not found'));
        }

        if (!in_array($newStatus, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_CLOSED], true)) {
            throw new InvalidArgumentException(Lang::T('Invalid status value'));
        }

        if ($userRole === null && !empty($_SESSION['aid'])) {
            $currentAdmin = ORM::for_table('tbl_users')->find_one($_SESSION['aid']);
            if ($currentAdmin) {
                $userRole = $currentAdmin->user_type;
            }
        }

        $minDate = '2000-01-01 00:00:00';
        $maxTime = time() + 300;

        $hasCustomClosedAt = ($customClosedAt !== null && trim((string)$customClosedAt) !== '');

        if (in_array($newStatus, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS], true)) {
            if ($hasCustomClosedAt) {
                throw new InvalidArgumentException(Lang::T('Closed at timestamp is only allowed when ticket status is resolved or closed'));
            }
            $ticket->closed_at = null;
            $ticket->resolved_at = null;
        } else {
            // Status is resolved or closed
            if ($hasCustomClosedAt) {
                if ($userRole !== null && !in_array($userRole, ['SuperAdmin', 'Admin'], true)) {
                    throw new InvalidArgumentException(Lang::T('Only SuperAdmin and Admin can set custom opened_at or closed_at timestamps'));
                }
                $parsed = self::parseDateTime($customClosedAt);
                if ($parsed === false) {
                    throw new InvalidArgumentException(Lang::T('Invalid closed_at datetime format. Supported formats: Y-m-d H:i:s, Y-m-d H:i, Y-m-d\TH:i:s, Y-m-d\TH:i'));
                }
                if ($parsed < $minDate) {
                    throw new InvalidArgumentException(Lang::T('Closed at timestamp cannot be earlier than 2000-01-01'));
                }
                if (strtotime($parsed) > $maxTime) {
                    throw new InvalidArgumentException(Lang::T('Closed at timestamp cannot be in the future'));
                }
                if (!empty($ticket->opened_at) && strtotime($parsed) < strtotime($ticket->opened_at)) {
                    throw new InvalidArgumentException(Lang::T('Closed at date cannot be earlier than opened at date'));
                }
                $ticket->closed_at = $parsed;
            } else {
                if (empty($ticket->closed_at)) {
                    $now = date('Y-m-d H:i:s');
                    if (!empty($ticket->opened_at) && strtotime($now) < strtotime($ticket->opened_at)) {
                        $ticket->closed_at = $ticket->opened_at;
                    } else {
                        $ticket->closed_at = $now;
                    }
                }
            }

            if (empty($ticket->resolved_at)) {
                $ticket->resolved_at = date('Y-m-d H:i:s');
            }
        }

        $oldStatus = $ticket->status;
        $ticket->status = $newStatus;
        $ticket->updated_at = date('Y-m-d H:i:s');

        $ticket->save();

        if (function_exists('run_hook')) {
            run_hook('ticket_status_changed', [
                'ticket' => $ticket,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);
        }

        return $ticket;
    }

    /**
     * Quick staff assignment
     */
    public static function assign($id, $assignedTo)
    {
        $id = (int) $id;
        if ($id <= 0) {
            throw new RuntimeException(Lang::T('Ticket not found'));
        }

        $ticket = ORM::for_table('tbl_tickets')->find_one($id);
        if (!$ticket) {
            throw new RuntimeException(Lang::T('Ticket not found'));
        }

        $staffId = (isset($assignedTo) && $assignedTo !== '' && $assignedTo !== null && $assignedTo !== 'unassigned') ? (int) $assignedTo : null;
        if ($staffId !== null) {
            $staff = ORM::for_table('tbl_users')->find_one($staffId);
            if (!$staff || $staff->user_type === 'Report') {
                throw new InvalidArgumentException(Lang::T('Staff member not found or invalid'));
            }
        }

        $oldAssigned = $ticket->assigned_to;
        $ticket->assigned_to = $staffId;
        $ticket->updated_at = date('Y-m-d H:i:s');
        $ticket->save();

        if (function_exists('run_hook')) {
            run_hook('ticket_assigned', [
                'ticket' => $ticket,
                'old_assigned_to' => $oldAssigned,
                'new_assigned_to' => $staffId
            ]);
        }

        return $ticket;
    }

    /**
     * Delete a ticket
     */
    public static function delete($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            throw new RuntimeException(Lang::T('Ticket not found'));
        }

        $ticket = ORM::for_table('tbl_tickets')->find_one($id);
        if (!$ticket) {
            throw new RuntimeException(Lang::T('Ticket not found'));
        }

        $ticketData = $ticket->as_array();
        $ticket->delete();

        if (function_exists('run_hook')) {
            run_hook('ticket_deleted', $ticketData);
        }

        return true;
    }

    /**
     * Find ticket with attached customer and staff relations (sanitizes sensitive data)
     */
    public static function findWithRelations($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        $ticket = ORM::for_table('tbl_tickets')->find_one($id);
        if (!$ticket) {
            return null;
        }

        $data = $ticket->as_array();

        if ($ticket->type === self::TYPE_CUSTOMER && !empty($ticket->customer_id)) {
            $customer = ORM::for_table('tbl_customers')->find_one($ticket->customer_id);
            if ($customer) {
                // Return only non-sensitive customer profile fields (never passwords/tokens)
                $data['customer'] = [
                    'id' => (int) $customer->id,
                    'username' => $customer->username,
                    'fullname' => $customer->fullname,
                    'address' => $customer->address,
                    'phonenumber' => $customer->phonenumber,
                    'email' => $customer->email,
                    'status' => $customer->status,
                    'account_type' => $customer->account_type,
                ];
            } else {
                $data['customer'] = null;
            }
        } else {
            $data['customer'] = null;
        }

        if (!empty($ticket->created_by)) {
            $creator = ORM::for_table('tbl_users')->find_one($ticket->created_by);
            $data['creator'] = $creator ? ['id' => (int) $creator->id, 'fullname' => $creator->fullname, 'username' => $creator->username] : null;
        } else {
            $data['creator'] = null;
        }

        if (!empty($ticket->assigned_to)) {
            $assignee = ORM::for_table('tbl_users')->find_one($ticket->assigned_to);
            $data['assignee'] = $assignee ? ['id' => (int) $assignee->id, 'fullname' => $assignee->fullname, 'username' => $assignee->username] : null;
        } else {
            $data['assignee'] = null;
        }

        $data['opened_at_formatted'] = !empty($ticket->opened_at) ? date('Y-m-d H:i:s', strtotime($ticket->opened_at)) : '-';
        $data['closed_at_formatted'] = !empty($ticket->closed_at) ? date('Y-m-d H:i:s', strtotime($ticket->closed_at)) : '-';
        $data['opened_at_input'] = !empty($ticket->opened_at) ? date('Y-m-d\TH:i', strtotime($ticket->opened_at)) : '';
        $data['closed_at_input'] = !empty($ticket->closed_at) ? date('Y-m-d\TH:i', strtotime($ticket->closed_at)) : '';

        return $data;
    }

    /**
     * Build filtered query for listing tickets with strict input sanitization
     */
    public static function buildQuery(array $filters = [])
    {
        $query = ORM::for_table('tbl_tickets');

        if (!empty($filters['type']) && is_string($filters['type']) && in_array($filters['type'], [self::TYPE_CUSTOMER, self::TYPE_MAINTENANCE], true)) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status']) && is_string($filters['status']) && in_array($filters['status'], [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_CLOSED], true)) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority']) && is_string($filters['priority']) && in_array($filters['priority'], [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_URGENT], true)) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['assigned_to']) && $filters['assigned_to'] !== '') {
            if ($filters['assigned_to'] === 'unassigned') {
                $query->where_null('assigned_to');
            } elseif (is_numeric($filters['assigned_to'])) {
                $query->where('assigned_to', (int) $filters['assigned_to']);
            }
        }

        if (!empty($filters['customer_id']) && is_numeric($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        if (!empty($filters['q']) && is_string($filters['q'])) {
            $q = trim($filters['q']);
            if ($q !== '') {
                $query->where_raw("(`ticket_number` LIKE ? OR `title` LIKE ? OR `description` LIKE ?)", [
                    '%' . $q . '%',
                    '%' . $q . '%',
                    '%' . $q . '%'
                ]);
            }
        }

        return $query;
    }

    /**
     * Get statistics summary for dashboard / filter badges
     */
    public static function getStats()
    {
        $total = ORM::for_table('tbl_tickets')->count();
        $open = ORM::for_table('tbl_tickets')->where('status', self::STATUS_OPEN)->count();
        $inProgress = ORM::for_table('tbl_tickets')->where('status', self::STATUS_IN_PROGRESS)->count();
        $resolved = ORM::for_table('tbl_tickets')->where('status', self::STATUS_RESOLVED)->count();
        $closed = ORM::for_table('tbl_tickets')->where('status', self::STATUS_CLOSED)->count();

        $customer = ORM::for_table('tbl_tickets')->where('type', self::TYPE_CUSTOMER)->count();
        $maintenance = ORM::for_table('tbl_tickets')->where('type', self::TYPE_MAINTENANCE)->count();

        return [
            'total' => $total,
            'open' => $open,
            'in_progress' => $inProgress,
            'resolved' => $resolved,
            'closed' => $closed,
            'customer' => $customer,
            'maintenance' => $maintenance,
        ];
    }
}
