{include file="sections/header.tpl"}
{include file="admin/tickets/style.tpl"}

<div class="ticketing-page">
<!-- Stat Badges -->
<div class="row">
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-aqua"><i class="fa fa-ticket"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">{Lang::T('Total Tickets')}</span>
                <span class="info-box-number">{$stats['total']}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-red"><i class="fa fa-exclamation-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">{Lang::T('Open')}</span>
                <span class="info-box-number">{$stats['open']}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-clock-o"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">{Lang::T('In Progress')}</span>
                <span class="info-box-number">{$stats['in_progress']}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-check-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">{Lang::T('Resolved / Closed')}</span>
                <span class="info-box-number">{$stats['resolved'] + $stats['closed']}</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                {if in_array($_admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])}
                    <div class="btn-group pull-right">
                        <a href="{Text::url('tickets/add')}" class="btn btn-success btn-xs">
                            <i class="fa fa-plus"></i> {Lang::T('New Ticket')}
                        </a>
                    </div>
                {/if}
                {Lang::T('Tickets')}
            </div>
            <div class="panel-body">
                <!-- Filters Form -->
                <form id="filter-form" method="get" action="{Text::url('tickets')}" class="ticketing-filter-panel">
                    <input type="hidden" name="_route" value="tickets">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label>{Lang::T('Search')}</label>
                                <input type="text" name="q" class="form-control input-sm" placeholder="{Lang::T('Search ticket, title, description')}..." value="{$filters['q']|escape}">
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label>{Lang::T('Type')}</label>
                                <select name="type" class="form-control input-sm">
                                    <option value="">-- {Lang::T('All Types')} --</option>
                                    <option value="customer" {if $filters['type'] eq 'customer'}selected{/if}>{Lang::T('Customer Ticket')}</option>
                                    <option value="maintenance" {if $filters['type'] eq 'maintenance'}selected{/if}>{Lang::T('Maintenance Ticket')}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label>{Lang::T('Status')}</label>
                                <select name="status" class="form-control input-sm">
                                    <option value="">-- {Lang::T('All Status')} --</option>
                                    <option value="open" {if $filters['status'] eq 'open'}selected{/if}>{Lang::T('Open')}</option>
                                    <option value="in_progress" {if $filters['status'] eq 'in_progress'}selected{/if}>{Lang::T('In Progress')}</option>
                                    <option value="resolved" {if $filters['status'] eq 'resolved'}selected{/if}>{Lang::T('Resolved')}</option>
                                    <option value="closed" {if $filters['status'] eq 'closed'}selected{/if}>{Lang::T('Closed')}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label>{Lang::T('Priority')}</label>
                                <select name="priority" class="form-control input-sm">
                                    <option value="">-- {Lang::T('All Priorities')} --</option>
                                    <option value="low" {if $filters['priority'] eq 'low'}selected{/if}>{Lang::T('Low')}</option>
                                    <option value="medium" {if $filters['priority'] eq 'medium'}selected{/if}>{Lang::T('Medium')}</option>
                                    <option value="high" {if $filters['priority'] eq 'high'}selected{/if}>{Lang::T('High')}</option>
                                    <option value="urgent" {if $filters['priority'] eq 'urgent'}selected{/if}>{Lang::T('Urgent')}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label>{Lang::T('Assigned To')}</label>
                                <select name="assigned_to" class="form-control input-sm">
                                    <option value="">-- {Lang::T('All Staff')} --</option>
                                    <option value="unassigned" {if $filters['assigned_to'] eq 'unassigned'}selected{/if}>{Lang::T('Unassigned')}</option>
                                    {foreach $staff as $s}
                                        <option value="{$s->id}" {if $filters['assigned_to'] eq $s->id}selected{/if}>{$s->fullname|escape}</option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 text-right">
                            <a href="{Text::url('tickets')}" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> {Lang::T('Reset')}</a>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> {Lang::T('Apply Filter')}</button>
                        </div>
                    </div>
                </form>

                <!-- Tickets Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr class="ticketing-table-header">
                                <th>{Lang::T('Ticket Number')}</th>
                                <th>{Lang::T('Type')}</th>
                                <th>{Lang::T('Title')}</th>
                                <th>{Lang::T('Customer')}</th>
                                <th>{Lang::T('Priority')}</th>
                                <th>{Lang::T('Status')}</th>
                                <th>{Lang::T('Assigned To')}</th>
                                <th>{Lang::T('Opened / Closed')}</th>
                                <th class="text-center" style="width: 140px;">{Lang::T('Action')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if empty($tickets)}
                                <tr>
                                    <td colspan="9" class="text-center ticketing-empty-state">
                                        <i class="fa fa-inbox fa-3x"></i><br>
                                        {Lang::T('No tickets found matching your criteria.')}
                                    </td>
                                </tr>
                            {else}
                                {foreach $tickets as $t}
                                    <tr>
                                        <td>
                                            <a href="{Text::url('tickets/view/', $t['id'])}" style="font-weight: bold;">
                                                {$t['ticket_number']|escape}
                                            </a>
                                        </td>
                                        <td>
                                            {if $t['type'] eq 'customer'}
                                                <span class="label label-info"><i class="fa fa-user"></i> {Lang::T('Customer')}</span>
                                            {else}
                                                <span class="label label-warning"><i class="fa fa-wrench"></i> {Lang::T('Maintenance')}</span>
                                            {/if}
                                        </td>
                                        <td>
                                            <a href="{Text::url('tickets/view/', $t['id'])}">{$t['title']|escape}</a>
                                        </td>
                                        <td>
                                            {if $t['type'] eq 'customer'}
                                                {if !empty($t['customer_name'])}
                                                    <a href="{Text::url('customers/view/', $t['customer_id'])}">
                                                        <i class="fa fa-user"></i> {$t['customer_name']|escape}
                                                    </a>
                                                {else}
                                                    <span class="text-muted">ID: {$t['customer_id']|escape}</span>
                                                {/if}
                                            {else}
                                                <span class="text-muted"><i class="fa fa-minus"></i> {Lang::T('Internal')}</span>
                                            {/if}
                                        </td>
                                        <td>
                                            {if $t['priority'] eq 'urgent'}
                                                <span class="label label-danger">{Lang::T('Urgent')}</span>
                                            {elseif $t['priority'] eq 'high'}
                                                <span class="label label-warning">{Lang::T('High')}</span>
                                            {elseif $t['priority'] eq 'medium'}
                                                <span class="label label-primary">{Lang::T('Medium')}</span>
                                            {else}
                                                <span class="label label-default">{Lang::T('Low')}</span>
                                            {/if}
                                        </td>
                                        <td>
                                            {if $t['status'] eq 'open'}
                                                <span class="label label-danger">{Lang::T('Open')}</span>
                                            {elseif $t['status'] eq 'in_progress'}
                                                <span class="label label-primary">{Lang::T('In Progress')}</span>
                                            {elseif $t['status'] eq 'resolved'}
                                                <span class="label label-success">{Lang::T('Resolved')}</span>
                                            {else}
                                                <span class="label label-default">{Lang::T('Closed')}</span>
                                            {/if}
                                        </td>
                                        <td>
                                            {if !empty($t['assigned_name']) && $t['assigned_name'] neq Lang::T('Unassigned')}
                                                <i class="fa fa-user-circle-o text-primary"></i> {$t['assigned_name']|escape}
                                            {else}
                                                <span class="text-muted"><em>{Lang::T('Unassigned')}</em></span>
                                            {/if}
                                        </td>
                                        <td>
                                            <span title="{Lang::T('Opened At')}"><i class="fa fa-calendar-o text-muted"></i> {$t['opened_at_formatted']|escape}</span>
                                            {if !empty($t['closed_at'])}
                                                <br><small class="text-success" title="{Lang::T('Closed At')}"><i class="fa fa-check-circle"></i> {$t['closed_at_formatted']|escape}</small>
                                            {/if}
                                        </td>
                                        <td class="text-center" style="white-space: nowrap;">
                                            <a href="{Text::url('tickets/view/', $t['id'])}" class="btn btn-info btn-xs" title="{Lang::T('View')}">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            {if in_array($_admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])}
                                                <a href="{Text::url('tickets/edit/', $t['id'])}" class="btn btn-warning btn-xs" title="{Lang::T('Edit')}">
                                                    <i class="fa fa-pencil"></i>
                                                </a>
                                            {/if}
                                            {if in_array($_admin['user_type'], ['SuperAdmin', 'Admin'])}
                                                <form method="post" action="{Text::url('tickets/delete/', $t['id'])}" style="display:inline-block; margin:0;" onsubmit="return confirm('{Lang::T('Are you sure you want to delete this ticket?')|escape:'javascript'}');">
                                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                                    <input type="hidden" name="id" value="{$t['id']}">
                                                    <button type="submit" class="btn btn-danger btn-xs" title="{Lang::T('Delete')}">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
                                            {/if}
                                        </td>
                                    </tr>
                                {/foreach}
                            {/if}
                        </tbody>
                    </table>
                </div>

                {include file="pagination.tpl"}
            </div>
        </div>
    </div>
</div>
</div>

{include file="sections/footer.tpl"}
