{include file="sections/header.tpl"}
<!-- pool -->
<div class="row">
    <div class="col-lg-3">
        <form method="get" class="form">
            <div class="box box-primary box-solid">
                <div class="box-header" onclick="showFilter()" style=" cursor: pointer;">
                    <h3 class="box-title">{Lang::T('Filter')}</h3>
                </div>
                <div id="filter_box" class="box-body hidden-xs hidden-sm hidden-md">
                    <input type="hidden" name="_route" value="reports/activation">
                    <label>{Lang::T('Start Date')}</label>
                    <input type="date" class="form-control" name="sd" value="{$sd}">
                    <label>{Lang::T('Start time')}</label>
                    <input type="time" class="form-control" name="ts" value="{$ts}">
                    <label>{Lang::T('End Date')}</label>
                    <input type="date" class="form-control" name="ed" value="{$ed}">
                    <label>{Lang::T('End Time')}</label>
                    <input type="time" class="form-control" name="te" value="{$te}">
                    <label>{Lang::T('Username')}</label>
                    <select class="form-control select2" name="uns[]" multiple>
                        {foreach $usernames as $username}
                            <option value="{$username}" {if in_array($username, $uns)}selected{/if}>{$username}</option>
                        {/foreach}
                    </select>
                    <label>{Lang::T('Internet Plans')}</label>
                    <select class="form-control select2" name="plns[]" multiple>
                        {foreach $plans as $plan}
                            <option value="{$plan}" {if in_array($plan, $plns)}selected{/if}>{$plan}</option>
                        {/foreach}
                    </select>
                    <label>{Lang::T('Methods')}</label>
                    <select class="form-control select2" name="mts[]" multiple>
                        {foreach $methods as $method}
                            <option value="{$method}" {if in_array($method, $mts)}selected{/if}>{$method}</option>
                        {/foreach}
                    </select>
                    <label>{Lang::T('Sellers')}</label>
                    <select class="form-control select2" name="sellers[]" multiple>
                        {foreach $sellers as $seller}
                            <option value="{$seller}" {if in_array($seller, $sellers_selected)}selected{/if}>{$seller}</option>
                        {/foreach}
                    </select>
                    <input type="submit" class="btn btn-success btn-block">
                </div>
            </div>
        </form>
    </div>
    <div class="col-lg-9">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                {Lang::T('Activity Log')}
            </div>
            <div class="panel-body">
                <div class="text-center" style="padding: 15px">
                    <div class="col-md-4">
                        <form id="site-search" method="get">
                            <input type="hidden" name="_route" value="reports/activation">
                            <div class="input-group">
                                <div class="input-group-addon">
                                    <span class="fa fa-search"></span>
                                </div>
                                <input type="text" name="q" class="form-control" value="{$q}"
                                    placeholder="{Lang::T('Invoice')}...">
                                <div class="input-group-btn">
                                    <button class="btn btn-success" type="submit">{Lang::T('Search')}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-8">

                    </div>&nbsp;
                </div>
                <br>
                <div class="table-responsive">
                    <table id="datatable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>{Lang::T('Invoice')}</th>
                                <th>{Lang::T('Username')}</th>
                                <th>{Lang::T('Plan Name')}</th>
                                <th>{Lang::T('Plan Price')}</th>
                                <th>{Lang::T('Type')}</th>
                                <th>{Lang::T('Created On')}</th>
                                <th>{Lang::T('Expires On')}</th>
                                <th>{Lang::T('Method')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $activation as $ds}
                                <tr>
                                    <td onclick="window.location.href = '{Text::url('')}plan/view/{$ds['id']}'"
                                        style="cursor:pointer;">{$ds['invoice']}</td>
                                    <td onclick="window.location.href = '{Text::url('')}customers/viewu/{$ds['username']}'"
                                        style="cursor:pointer;">{$ds['username']}</td>
                                    <td>{$ds['plan_name']}</td>
                                    <td>{Lang::moneyFormat($ds['price'])}</td>
                                    <td>{$ds['type']}</td>
                                    <td class="text-success">
                                        {Lang::dateAndTimeFormat($ds['recharged_on'],$ds['recharged_time'])}
                                    </td>
                                    <td class="text-danger">{Lang::dateAndTimeFormat($ds['expiration'],$ds['time'])}</td>
                                    <td>{$ds['method']}</td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
                {include file="pagination.tpl"}
            </div>
        </div>
    </div>
</div>

{include file="sections/footer.tpl"}