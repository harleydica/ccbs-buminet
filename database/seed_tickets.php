<?php

/**
 * Seeder for Ticketing System
 * Usage: php database/seed_tickets.php
 */

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'init.php';

echo "Seeding tickets...\n";

// Ensure at least one customer exists
$customer = ORM::for_table('tbl_customers')->find_one();
if (!$customer) {
    $customer = ORM::for_table('tbl_customers')->create();
    $customer->username = 'cust_demo';
    $customer->password = Password::_crypt('demo1234');
    $customer->fullname = 'Demo Customer';
    $customer->email = 'demo@example.com';
    $customer->phonenumber = '08123456789';
    $customer->status = 'Active';
    $customer->save();
    echo "Created sample customer ID: " . $customer->id . "\n";
}

// Ensure at least one admin/staff exists
$admin = ORM::for_table('tbl_users')->find_one();
$admin_id = $admin ? $admin->id : 1;

// Sample Ticket 1: Customer Ticket (Open)
$title1 = 'Internet connection down';
$t1 = ORM::for_table('tbl_tickets')->where('title', $title1)->find_one();
if (!$t1) {
    $t1 = ORM::for_table('tbl_tickets')->create();
    $t1->ticket_number = Ticket::generateTicketNumber();
    $t1->type = 'customer';
    $t1->title = $title1;
    $t1->description = 'Customer reported LOS light red on optical modem since this morning.';
    $t1->customer_id = $customer->id;
    $t1->status = 'open';
    $t1->priority = 'high';
    $t1->created_by = $admin_id;
    $t1->assigned_to = $admin_id;
    $t1->created_at = date('Y-m-d H:i:s');
    $t1->opened_at = $t1->created_at;
    $t1->save();
    echo "Seeded: " . $t1->ticket_number . " - " . $t1->title . "\n";
} else {
    echo "Already exists: " . $t1->ticket_number . " - " . $t1->title . " (skipped)\n";
}

// Sample Ticket 2: Customer Ticket (In Progress)
$title2 = 'Billing inquiry regarding renewal';
$t2 = ORM::for_table('tbl_tickets')->where('title', $title2)->find_one();
if (!$t2) {
    $t2 = ORM::for_table('tbl_tickets')->create();
    $t2->ticket_number = Ticket::generateTicketNumber();
    $t2->type = 'customer';
    $t2->title = $title2;
    $t2->description = 'Customer asking why automatic renewal did not apply to new promotion.';
    $t2->customer_id = $customer->id;
    $t2->status = 'in_progress';
    $t2->priority = 'medium';
    $t2->created_by = $admin_id;
    $t2->assigned_to = $admin_id;
    $t2->created_at = date('Y-m-d H:i:s', strtotime('-1 day'));
    $t2->opened_at = $t2->created_at;
    $t2->updated_at = date('Y-m-d H:i:s');
    $t2->save();
    echo "Seeded: " . $t2->ticket_number . " - " . $t2->title . "\n";
} else {
    echo "Already exists: " . $t2->ticket_number . " - " . $t2->title . " (skipped)\n";
}

// Sample Ticket 3: Maintenance Ticket (Resolved) - customer_id MUST be NULL
$title3 = 'ODP-04 Fiber Splicing & Maintenance';
$t3 = ORM::for_table('tbl_tickets')->where('title', $title3)->find_one();
if (!$t3) {
    $t3 = ORM::for_table('tbl_tickets')->create();
    $t3->ticket_number = Ticket::generateTicketNumber();
    $t3->type = 'maintenance';
    $t3->title = $title3;
    $t3->description = 'Scheduled fiber optic maintenance and core rearrangement at ODP Cluster 4.';
    $t3->customer_id = null;
    $t3->status = 'resolved';
    $t3->priority = 'urgent';
    $t3->created_by = $admin_id;
    $t3->assigned_to = $admin_id;
    $t3->created_at = date('Y-m-d H:i:s', strtotime('-2 days'));
    $t3->opened_at = $t3->created_at;
    $t3->updated_at = date('Y-m-d H:i:s', strtotime('-1 day'));
    $t3->resolved_at = date('Y-m-d H:i:s', strtotime('-1 day'));
    $t3->closed_at = $t3->resolved_at;
    $t3->save();
    echo "Seeded: " . $t3->ticket_number . " - " . $t3->title . "\n";
} else {
    echo "Already exists: " . $t3->ticket_number . " - " . $t3->title . " (skipped)\n";
}

// Sample Ticket 4: Maintenance Ticket (Open) - customer_id MUST be NULL
$title4 = 'Main Distribution Switch Upgrade';
$t4 = ORM::for_table('tbl_tickets')->where('title', $title4)->find_one();
if (!$t4) {
    $t4 = ORM::for_table('tbl_tickets')->create();
    $t4->ticket_number = Ticket::generateTicketNumber();
    $t4->type = 'maintenance';
    $t4->title = $title4;
    $t4->description = 'Replacing uplink SFP+ modules on Distribution Switch 01.';
    $t4->customer_id = null;
    $t4->status = 'open';
    $t4->priority = 'low';
    $t4->created_by = $admin_id;
    $t4->assigned_to = null;
    $t4->created_at = date('Y-m-d H:i:s');
    $t4->opened_at = $t4->created_at;
    $t4->save();
    echo "Seeded: " . $t4->ticket_number . " - " . $t4->title . "\n";
} else {
    echo "Already exists: " . $t4->ticket_number . " - " . $t4->title . " (skipped)\n";
}

echo "Seeding completed successfully!\n";
