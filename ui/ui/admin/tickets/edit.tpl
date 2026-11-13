{include file="sections/header.tpl"}
{include file="admin/tickets/style.tpl"}

<div class="ticketing-page">
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                <div class="btn-group pull-right">
                    <a href="{Text::url('tickets/view/', $t['id'])}" class="btn btn-default btn-xs">
                        <i class="fa fa-arrow-left"></i> {Lang::T('Back to Ticket')}
                    </a>
                </div>
                <i class="fa fa-pencil"></i> {Lang::T('Edit Ticket')} &mdash; {$t['ticket_number']|escape}
            </div>
            <div class="panel-body">
                <form class="form-horizontal" method="post" action="{Text::url('tickets/edit-post')}">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <input type="hidden" name="id" value="{$t['id']}">

                    <!-- Ticket Number & Type (Read-only) -->
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Ticket Information')}</label>
                        <div class="col-md-9" style="padding-top: 7px;">
                            <strong>{$t['ticket_number']|escape}</strong> &nbsp;
                            {if $t['type'] eq 'customer'}
                                <span class="label label-info"><i class="fa fa-user"></i> {Lang::T('Customer Ticket')}</span>
                            {else}
                                <span class="label label-warning"><i class="fa fa-wrench"></i> {Lang::T('Maintenance Ticket')}</span>
                            {/if}
                        </div>
                    </div>

                    <!-- Customer (Only for Customer Ticket) -->
                    {if $t['type'] eq 'customer'}
                        <div class="form-group">
                            <label class="col-md-3 control-label">{Lang::T('Customer')} <span class="text-danger">*</span></label>
                            <div class="col-md-9">
                                <select name="customer_id" id="customer_id" class="form-control" required>
                                    <option value="">-- {Lang::T('Select Customer')} --</option>
                                    {foreach $customers as $c}
                                        <option value="{$c->id}" {if $t['customer_id'] eq $c->id}selected{/if}>
                                            {$c->fullname|escape} ({$c->username|escape})
                                        </option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                    {/if}

                    <hr style="margin: 15px 0;">

                    <!-- Title -->
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Title')} <span class="text-danger">*</span></label>
                        <div class="col-md-9">
                            <input type="text" name="title" id="title" class="form-control" value="{$t['title']|escape}" required>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Description')} <span class="text-danger">*</span></label>
                        <div class="col-md-9">
                            <textarea name="description" id="description" rows="6" class="form-control" required>{$t['description']|escape}</textarea>
                        </div>
                    </div>

                    <!-- Priority & Status -->
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Priority')}</label>
                        <div class="col-md-4">
                            <select name="priority" id="priority" class="form-control">
                                <option value="low" {if $t['priority'] eq 'low'}selected{/if}>{Lang::T('Low')}</option>
                                <option value="medium" {if $t['priority'] eq 'medium'}selected{/if}>{Lang::T('Medium')}</option>
                                <option value="high" {if $t['priority'] eq 'high'}selected{/if}>{Lang::T('High')}</option>
                                <option value="urgent" {if $t['priority'] eq 'urgent'}selected{/if}>{Lang::T('Urgent')}</option>
                            </select>
                        </div>
                        <label class="col-md-2 control-label">{Lang::T('Status')}</label>
                        <div class="col-md-3">
                            <select name="status" id="status" class="form-control">
                                <option value="open" {if $t['status'] eq 'open'}selected{/if}>{Lang::T('Open')}</option>
                                <option value="in_progress" {if $t['status'] eq 'in_progress'}selected{/if}>{Lang::T('In Progress')}</option>
                                <option value="resolved" {if $t['status'] eq 'resolved'}selected{/if}>{Lang::T('Resolved')}</option>
                                <option value="closed" {if $t['status'] eq 'closed'}selected{/if}>{Lang::T('Closed')}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Assigned Staff -->
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Assign To')}</label>
                        <div class="col-md-9">
                            <select name="assigned_to" id="assigned_to" class="form-control">
                                <option value="">-- {Lang::T('Unassigned')} --</option>
                                {foreach $staff as $s}
                                    <option value="{$s->id}" {if $t['assigned_to'] eq $s->id}selected{/if}>
                                        {$s->fullname|escape} ({$s->user_type|escape})
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                    </div>

                    {if in_array($_admin['user_type'], ['SuperAdmin', 'Admin'])}
                        <!-- Custom Opened At & Closed At (Admin/SuperAdmin only) -->
                        <div class="form-group">
                            <label class="col-md-3 control-label">{Lang::T('Opened At')}</label>
                            <div class="col-md-4">
                                <input type="datetime-local" name="opened_at" id="opened_at" class="form-control" value="{$t['opened_at_input']}">
                            </div>
                        </div>

                        <div class="form-group" id="closed_at_group" {if !in_array($t['status'], ['resolved', 'closed'])}style="display: none;"{/if}>
                            <label class="col-md-3 control-label">{Lang::T('Closed At')}</label>
                            <div class="col-md-4">
                                <input type="datetime-local" name="closed_at" id="closed_at" class="form-control" value="{$t['closed_at_input']}">
                                <p class="help-block"><small class="text-muted">{Lang::T('Only valid when status is Resolved or Closed. Must be >= Opened At.')}</small></p>
                            </div>
                        </div>
                    {/if}

                    <div class="form-group">
                        <div class="col-md-9 col-md-offset-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save"></i> {Lang::T('Save Changes')}
                            </button>
                            <a href="{Text::url('tickets/view/', $t['id'])}" class="btn btn-default">
                                {Lang::T('Cancel')}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var statusSelect = document.getElementById('status');
    var closedAtGroup = document.getElementById('closed_at_group');
    var closedAtInput = document.getElementById('closed_at');

    if (statusSelect && closedAtGroup) {
        function checkStatus() {
            var val = statusSelect.value;
            if (val === 'resolved' || val === 'closed') {
                closedAtGroup.style.display = 'block';
                if (closedAtInput && !closedAtInput.value) {
                    var now = new Date();
                    var pad = function(n) { return (n < 10 ? '0' : '') + n; };
                    closedAtInput.value = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
                }
            } else {
                closedAtGroup.style.display = 'none';
            }
        }
        statusSelect.addEventListener('change', checkStatus);
    }
});
</script>

{include file="sections/footer.tpl"}
