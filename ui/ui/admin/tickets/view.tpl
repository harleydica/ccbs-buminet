{include file="sections/header.tpl"}
{include file="admin/tickets/style.tpl"}

<div class="ticketing-page">
<div class="row">
    <div class="col-md-12">
        <div class="box box-widget" style="margin-bottom: 15px;">
            <div class="box-header with-border">
                <div class="user-block">
                    <span class="username" style="margin-left: 0; font-size: 20px;">
                        <i class="fa fa-ticket text-primary"></i> {$t['ticket_number']|escape} &mdash; {$t['title']|escape}
                    </span>
                    <span class="description" style="margin-left: 0; margin-top: 4px;">
                        {if $t['type'] eq 'customer'}
                            <span class="label label-info"><i class="fa fa-user"></i> {Lang::T('Customer Ticket')}</span>
                        {else}
                            <span class="label label-warning"><i class="fa fa-wrench"></i> {Lang::T('Maintenance Ticket')}</span>
                        {/if}
                        &nbsp;
                        {if $t['priority'] eq 'urgent'}
                            <span class="label label-danger">{Lang::T('Urgent Priority')}</span>
                        {elseif $t['priority'] eq 'high'}
                            <span class="label label-warning">{Lang::T('High Priority')}</span>
                        {elseif $t['priority'] eq 'medium'}
                            <span class="label label-primary">{Lang::T('Medium Priority')}</span>
                        {else}
                            <span class="label label-default">{Lang::T('Low Priority')}</span>
                        {/if}
                        &nbsp;
                        {if $t['status'] eq 'open'}
                            <span class="label label-danger">{Lang::T('Status: Open')}</span>
                        {elseif $t['status'] eq 'in_progress'}
                            <span class="label label-primary">{Lang::T('Status: In Progress')}</span>
                        {elseif $t['status'] eq 'resolved'}
                            <span class="label label-success">{Lang::T('Status: Resolved')}</span>
                        {else}
                            <span class="label label-default">{Lang::T('Status: Closed')}</span>
                        {/if}
                    </span>
                </div>
                <div class="box-tools">
                    {if in_array($_admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])}
                        <a href="{Text::url('tickets/edit/', $t['id'])}" class="btn btn-warning btn-sm">
                            <i class="fa fa-pencil"></i> {Lang::T('Edit')}
                        </a>
                    {/if}
                    <a href="{Text::url('tickets')}" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> {Lang::T('Back to List')}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column: Ticket Details & Description -->
    <div class="col-md-8">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <i class="fa fa-file-text-o"></i> {Lang::T('Ticket Description')}
            </div>
            <div class="panel-body" style="min-height: 180px; font-size: 15px; line-height: 1.6; white-space: pre-wrap;">{$t['description']|escape}</div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <i class="fa fa-info-circle"></i> {Lang::T('Ticket Metadata')}
            </div>
            <div class="panel-body">
                <table class="table table-bordered table-striped" style="margin-bottom: 0;">
                    <tr>
                        <th style="width: 30%;">{Lang::T('Ticket Number')}</th>
                        <td><strong>{$t['ticket_number']|escape}</strong></td>
                    </tr>
                    <tr>
                        <th>{Lang::T('Type')}</th>
                        <td>
                            {if $t['type'] eq 'customer'}
                                <span class="label label-info">{Lang::T('Customer Ticket')}</span>
                            {else}
                                <span class="label label-warning">{Lang::T('Maintenance Ticket')}</span>
                            {/if}
                        </td>
                    </tr>
                    <tr>
                        <th>{Lang::T('Status')}</th>
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
                    </tr>
                    <tr>
                        <th>{Lang::T('Opened At')}</th>
                        <td>{if !empty($t['opened_at'])}{$t['opened_at']|escape}{else}<em>{Lang::T('Not recorded')}</em>{/if}</td>
                    </tr>
                    <tr>
                        <th>{Lang::T('Closed At')}</th>
                        <td>{if !empty($t['closed_at'])}{$t['closed_at']|escape}{else}<em>{Lang::T('Not yet closed')}</em>{/if}</td>
                    </tr>
                    <tr>
                        <th>{Lang::T('Created By')}</th>
                        <td>{if !empty($t['creator'])}{$t['creator']['fullname']|escape} ({$t['creator']['username']|escape}){else}{Lang::T('System')}{/if}</td>
                    </tr>
                    <tr>
                        <th>{Lang::T('Created At (Audit)')}</th>
                        <td>{$t['created_at']|escape}</td>
                    </tr>
                    <tr>
                        <th>{Lang::T('Last Updated')}</th>
                        <td>{if !empty($t['updated_at'])}{$t['updated_at']|escape}{else}<em>{Lang::T('Never')}</em>{/if}</td>
                    </tr>
                    <tr>
                        <th>{Lang::T('Resolved At (Audit)')}</th>
                        <td>{if !empty($t['resolved_at'])}{$t['resolved_at']|escape}{else}<em>{Lang::T('Not yet resolved')}</em>{/if}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Customer/Maintenance Details & Controls -->
    <div class="col-md-4">
        <!-- Customer or Maintenance Badge Box -->
        {if $t['type'] eq 'customer'}
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-user"></i> {Lang::T('Customer Information')}</h3>
                </div>
                <div class="box-body">
                    {if !empty($t['customer'])}
                        <p><strong>{Lang::T('Name')}:</strong> <a href="{Text::url('customers/view/', $t['customer']['id'])}">{$t['customer']['fullname']|escape}</a></p>
                        <p><strong>{Lang::T('Username')}:</strong> {$t['customer']['username']|escape}</p>
                        <p><strong>{Lang::T('Phone')}:</strong> {$t['customer']['phonenumber']|escape}</p>
                        <p><strong>{Lang::T('Email')}:</strong> {$t['customer']['email']|escape}</p>
                        <p><strong>{Lang::T('Address')}:</strong> {$t['customer']['address']|escape}</p>
                        <a href="{Text::url('customers/view/', $t['customer']['id'])}" class="btn btn-info btn-block btn-sm">
                            <i class="fa fa-external-link"></i> {Lang::T('View Customer Profile')}
                        </a>
                    {else}
                        <p class="text-muted">{Lang::T('Customer record not available')}</p>
                    {/if}
                </div>
            </div>
        {else}
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-wrench"></i> {Lang::T('Internal Maintenance')}</h3>
                </div>
                <div class="box-body text-center" style="padding: 20px;">
                    <i class="fa fa-cogs fa-3x text-warning" style="margin-bottom: 10px;"></i>
                    <p style="font-weight: 600; margin-bottom: 5px;">{Lang::T('No Customer Associated')}</p>
                    <p class="text-muted" style="font-size: 13px;">
                        {Lang::T('This ticket represents internal maintenance or infrastructure operations and contains no customer relation.')}
                    </p>
                </div>
            </div>
        {/if}

        {if in_array($_admin['user_type'], ['SuperAdmin', 'Admin', 'Agent', 'Sales'])}
            <!-- Quick Status Update Card -->
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-refresh"></i> {Lang::T('Update Status')}</h3>
                </div>
                <div class="box-body">
                    <form method="post" action="{Text::url('tickets/status-post')}">
                        <input type="hidden" name="csrf_token" value="{$csrf_token}">
                        <input type="hidden" name="id" value="{$t['id']}">
                        <div class="form-group">
                            <select name="status" id="quick_status_select" class="form-control" style="font-weight: bold;">
                                <option value="open" {if $t['status'] eq 'open'}selected{/if}>{Lang::T('Open')}</option>
                                <option value="in_progress" {if $t['status'] eq 'in_progress'}selected{/if}>{Lang::T('In Progress')}</option>
                                <option value="resolved" {if $t['status'] eq 'resolved'}selected{/if}>{Lang::T('Resolved')}</option>
                                <option value="closed" {if $t['status'] eq 'closed'}selected{/if}>{Lang::T('Closed')}</option>
                            </select>
                        </div>
                        {if in_array($_admin['user_type'], ['SuperAdmin', 'Admin'])}
                            <div class="form-group" id="quick_closed_at_group" {if !in_array($t['status'], ['resolved', 'closed'])}style="display: none;"{/if}>
                                <label><small>{Lang::T('Closed At (Optional)')}:</small></label>
                                <input type="datetime-local" name="closed_at" id="quick_closed_at" class="form-control input-sm" value="{$t['closed_at_input']}">
                            </div>
                        {/if}
                        <button type="submit" class="btn btn-primary btn-block btn-sm">
                            <i class="fa fa-check"></i> {Lang::T('Change Status')}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Quick Assign Staff Card -->
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-user-plus"></i> {Lang::T('Assign Staff')}</h3>
                </div>
                <div class="box-body">
                    <form method="post" action="{Text::url('tickets/assign-post')}">
                        <input type="hidden" name="csrf_token" value="{$csrf_token}">
                        <input type="hidden" name="id" value="{$t['id']}">
                        <div class="form-group">
                            <select name="assigned_to" class="form-control">
                                <option value="">-- {Lang::T('Unassigned')} --</option>
                                {foreach $staff as $s}
                                    <option value="{$s->id}" {if $t['assigned_to'] eq $s->id}selected{/if}>
                                        {$s->fullname|escape} ({$s->user_type|escape})
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                        <button type="submit" class="btn btn-info btn-block btn-sm">
                            <i class="fa fa-user-circle"></i> {Lang::T('Update Assignment')}
                        </button>
                    </form>
                </div>
            </div>
        {/if}
    </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var qsSelect = document.getElementById('quick_status_select');
    var qsGroup = document.getElementById('quick_closed_at_group');
    var qsInput = document.getElementById('quick_closed_at');
    if (qsSelect && qsGroup) {
        qsSelect.addEventListener('change', function() {
            if (this.value === 'resolved' || this.value === 'closed') {
                qsGroup.style.display = 'block';
                if (qsInput && !qsInput.value) {
                    var now = new Date();
                    var pad = function(n) { return (n < 10 ? '0' : '') + n; };
                    qsInput.value = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
                }
            } else {
                qsGroup.style.display = 'none';
            }
        });
    }
});
</script>

{include file="sections/footer.tpl"}
