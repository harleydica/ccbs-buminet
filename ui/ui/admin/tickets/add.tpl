{include file="sections/header.tpl"}
{include file="admin/tickets/style.tpl"}

<div class="ticketing-page">
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                <div class="btn-group pull-right">
                    <a href="{Text::url('tickets')}" class="btn btn-default btn-xs">
                        <i class="fa fa-arrow-left"></i> {Lang::T('Back to List')}
                    </a>
                </div>
                <i class="fa fa-plus-circle"></i> {Lang::T('Create New Ticket')}
            </div>
            <div class="panel-body">
                <form class="form-horizontal" method="post" action="{Text::url('tickets/add-post')}">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">

                    <!-- Ticket Type Selection -->
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Ticket Type')} <span class="text-danger">*</span></label>
                        <div class="col-md-9">
                            <label class="radio-inline" style="font-weight: 600;">
                                <input type="radio" name="type" id="type_customer" value="customer" {if $preType neq 'maintenance'}checked{/if} onchange="toggleTicketType()">
                                <i class="fa fa-user text-info"></i> {Lang::T('Customer Ticket')}
                            </label>
                            <label class="radio-inline" style="font-weight: 600; margin-left: 20px;">
                                <input type="radio" name="type" id="type_maintenance" value="maintenance" {if $preType eq 'maintenance'}checked{/if} onchange="toggleTicketType()">
                                <i class="fa fa-wrench text-warning"></i> {Lang::T('Maintenance Ticket')}
                            </label>
                            <p class="help-block" id="type_help_text">
                                <small class="text-muted">{Lang::T('Customer tickets track customer complaints, outages, or inquiries and require customer data.')}</small>
                            </p>
                        </div>
                    </div>

                    <!-- Customer Selection (Customer tickets only) -->
                    <div class="form-group" id="customer_group">
                        <label class="col-md-3 control-label">{Lang::T('Customer')} <span class="text-danger">*</span></label>
                        <div class="col-md-9">
                            <select name="customer_id" id="customer_id" class="form-control" style="width: 100%;">
                                <option value="">-- {Lang::T('Select Customer')} --</option>
                                {foreach $customers as $c}
                                    <option value="{$c->id}" {if $preCustomerId eq $c->id}selected{/if}>
                                        {$c->fullname|escape} ({$c->username|escape})
                                    </option>
                                {/foreach}
                            </select>
                            <p class="help-block"><small class="text-muted">{Lang::T('Choose the affected customer account.')}</small></p>
                        </div>
                    </div>

                    <!-- Maintenance Notice (Maintenance tickets only) -->
                    <div class="form-group" id="maintenance_notice" style="display: none;">
                        <div class="col-md-9 col-md-offset-3">
                            <div class="alert alert-warning" style="margin-bottom: 0; padding: 10px 15px;">
                                <i class="fa fa-info-circle"></i> 
                                <strong>{Lang::T('Internal Maintenance Ticket')}:</strong> 
                                {Lang::T('Maintenance tickets do not accept customer data and are recorded exclusively for internal network, POP, or infrastructure tasks.')}
                            </div>
                        </div>
                    </div>

                    <hr style="margin: 15px 0;">

                    <!-- Title -->
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Title')} <span class="text-danger">*</span></label>
                        <div class="col-md-9">
                            <input type="text" name="title" id="title" class="form-control" placeholder="{Lang::T('e.g. Optical fiber cut on Cluster 3 / Router reboot loop')}" required>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Description')} <span class="text-danger">*</span></label>
                        <div class="col-md-9">
                            <textarea name="description" id="description" rows="5" class="form-control" placeholder="{Lang::T('Describe the issue, troubleshooting notes, or scope of maintenance work...')}" required></textarea>
                        </div>
                    </div>

                    <!-- Priority & Assignment -->
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Priority')}</label>
                        <div class="col-md-4">
                            <select name="priority" id="priority" class="form-control">
                                <option value="low">{Lang::T('Low')}</option>
                                <option value="medium" selected>{Lang::T('Medium')}</option>
                                <option value="high">{Lang::T('High')}</option>
                                <option value="urgent">{Lang::T('Urgent')}</option>
                            </select>
                        </div>
                        <label class="col-md-2 control-label">{Lang::T('Assign To')}</label>
                        <div class="col-md-3">
                            <select name="assigned_to" id="assigned_to" class="form-control">
                                <option value="">-- {Lang::T('Unassigned')} --</option>
                                {foreach $staff as $s}
                                    <option value="{$s->id}">{$s->fullname|escape} ({$s->user_type|escape})</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>

                    {if in_array($_admin['user_type'], ['SuperAdmin', 'Admin'])}
                        <!-- Custom Opened At (Admin/SuperAdmin only) -->
                        <div class="form-group">
                            <label class="col-md-3 control-label">{Lang::T('Opened At')}</label>
                            <div class="col-md-5">
                                <input type="datetime-local" name="opened_at" id="opened_at" class="form-control" value="{$now_datetime}">
                                <p class="help-block"><small class="text-muted">{Lang::T('Optional custom opened timestamp. Defaults to current time if left empty.')}</small></p>
                            </div>
                        </div>
                    {/if}

                    <div class="form-group">
                        <div class="col-md-9 col-md-offset-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save"></i> {Lang::T('Create Ticket')}
                            </button>
                            <a href="{Text::url('tickets')}" class="btn btn-default">{Lang::T('Cancel')}</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<script>
function toggleTicketType() {
    var isMaintenance = document.getElementById('type_maintenance').checked;
    var customerGroup = document.getElementById('customer_group');
    var customerSelect = document.getElementById('customer_id');
    var maintenanceNotice = document.getElementById('maintenance_notice');
    var helpText = document.getElementById('type_help_text');

    if (isMaintenance) {
        customerGroup.style.display = 'none';
        customerSelect.disabled = true;
        customerSelect.value = '';
        customerSelect.removeAttribute('required');
        maintenanceNotice.style.display = 'block';
        helpText.innerHTML = '<small class="text-warning"><strong>' + "{Lang::T('Maintenance tickets are for internal team operations (repairs, migrations, hardware replacement).')|escape:'javascript'}" + '</strong></small>';
    } else {
        customerGroup.style.display = 'block';
        customerSelect.disabled = false;
        customerSelect.setAttribute('required', 'required');
        maintenanceNotice.style.display = 'none';
        helpText.innerHTML = '<small class="text-muted">' + "{Lang::T('Customer tickets track customer complaints, outages, or inquiries and require customer data.')|escape:'javascript'}" + '</small>';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleTicketType();
});
</script>

{include file="sections/footer.tpl"}
