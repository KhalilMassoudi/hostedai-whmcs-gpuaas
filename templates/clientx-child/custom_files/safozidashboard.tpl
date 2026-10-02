<div class="default-whmcs-client-area p-4 safozi-latest-clientarea">

    <div class="row">
        <div class="col-md-12">
            <div class="dasboard-top-sec">
            <div class="dsh-top-wrapper">
                <div class="dash-system-operation">
                    <p><span class="circle-dott green"></span> {$LANG.safoziDashallSystemOper}</p>
                </div>
                <div class="dash-top-cdetails">
                    <h4>{$displayTitle} <span><img src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/hand-wave.svg" alt="hello"></span></h4>
                    <p>{$LANG.safoxiDashboardoverview} -<span class="date"> {$smarty.now|date_format:"%a.
                            %d %b %Y"}</span></p>
                </div>
            </div>
                <div class="dash-top-orderbtn">
                    <a href="{$WEB_ROOT}/cart.php" class="wgs_custom-btn ">+ {lang key='ordernowbutton'}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="message-box-theme">
        {include file="$template/includes/flashmessage.tpl"}
    </div>
    <div class="tiles mb-4">
        <div class="row no-gutters">
            <div class="col-6 col-xl-3">
                <a href="clientarea.php?action=services" class="tile dash-card-tile blue-head">
                    <div class="icon"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/dash-service-ic.svg"
                            alt="dash-card"></div>
                    <!-- <i class="fas fa-cube"></i> -->
                    <div class="content">
                        <div class="d-number stat">{$clientsstats.productsnumactive}</div>
                        <div class="d-label title">{lang key='navservices'}</div>
                    </div>
                    <div class="arrow"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/right-arrow.svg" alt="arrow">
                    </div>
                </a>
            </div>
            {if $clientsstats.numdomains || $registerdomainenabled || $transferdomainenabled}
            <div class="col-6 col-xl-3">
                <a href="{$WEB_ROOT}/clientarea.php?action=masspay&all=true"
                    class="tile dash-card-tile dash-billing-card green-head">
                    <!-- <i class="fas fa-globe"></i> -->
                    <!-- <i class="fas fa-cube"></i> -->
                    <div class="icon"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/dash-card-ic.svg"
                            alt="dash-card"></div>
                    <div class="content">
                        <div class="d-number stat">{if $unpaidInvoiceBalance} {$unpaidInvoiceBalance} {else} 0 {/if}</div>
                        <div class="d-label title"><span>{$LANG.latestBilling}</span><span
                                class="pay-all-blue">{$LANG.masspayall}</span></div>
                    </div>
                    <!-- <div class="arrow"><img src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/right-arrow.svg" alt="arrow"></div> -->
                </a>
            </div>
            {elseif $condlinks.affiliates && $clientsstats.isAffiliate}
            <div class="col-6 col-xl-3">
                <a href="affiliates.php" class="tile dash-card-tile">
                    <!-- <i class="fas fa-shopping-cart"></i> -->
                    <div class="icon"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/dash-card-ic.svg"
                            alt="dash-card"></div>
                    <div class="content">
                        <div class="d-number stat">{$clientsstats.numaffiliatesignups}</div>
                        <div class="d-label title">{lang key='affiliatessignups'}</div>
                    </div>
                    <div class="arrow"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/right-arrow.svg" alt="arrow">
                    </div>
                </a>
            </div>
            {else}
            <div class="col-6 col-xl-3">
                <a href="clientarea.php?action=quotes" class="tile dash-card-tile">
                    <!-- <i class="far fa-file-alt"></i> -->
                    <div class="icon"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/dash-card-ic.svg"
                            alt="dash-card"></div>
                    <div class="content">
                        <div class="d-number stat">{$clientsstats.numquotes}</div>
                        <div class="d-label title">{lang key='quotes'}</div>
                    </div>
                    <div class="arrow"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/right-arrow.svg" alt="arrow">
                    </div>
                </a>
            </div>
            {/if}
            <div class="col-6 col-xl-3">
                <a href="clientarea.php?action=invoices" class="tile dash-card-tile black-head">
                    <!-- <i class="fas fa-credit-card"></i> -->
                    <div class="icon"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/dash-cart-ic.svg"
                            alt="dash-card"></div>
                    <div class="content">
                        <div class="d-number stat">{$clientsstats.numunpaidinvoices}</div>
                        <div class="d-label title">{lang key='navinvoices'}</div>
                    </div>
                    <div class="arrow"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/right-arrow.svg" alt="arrow">
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a href="supporttickets.php" class="tile dash-card-tile white-head">
                    <!-- <i class="fas fa-comments"></i> -->
                    <div class="icon"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/dash-contatct.svg"
                            alt="dash-card"></div>
                    <div class="content">
                        <div class="d-number stat">{$clientsstats.numactivetickets}</div>
                        <div class="d-label title">{lang key='navtickets'}</div>
                    </div>
                    <div class="arrow"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/right-arrow.svg" alt="arrow">
                    </div>
                </a>
            </div>
            {if $hostedaiWalletBalance !== null}
            <div class="col-6 col-xl-3 hostedai-wallet-tile">
                <a href="{$WEB_ROOT}/{$hostedaiWalletTopupUrl}" class="tile dash-card-tile green-head">
                    <div class="icon"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/dash-card-ic.svg"
                            alt="dash-card"></div>
                    <div class="content">
                        <div class="d-number stat">${$hostedaiWalletBalance|string_format:"%.2f"}</div>
                        <div class="d-label title">Hosted AI Wallet</div>
                        <div class="d-label hostedai-wallet-hint">Solde partagé entre tous vos services Pay-As-You-Go. Rechargez, puis choisissez votre GPU Cloud.</div>
                    </div>
                    <div class="arrow"><img
                            src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/right-arrow.svg" alt="arrow">
                    </div>
                </a>
            </div>
            {/if}

            {literal}
            <style>
            /* Hosted AI Wallet tile: on desktop it wraps onto its own new
               row (5th tile in a 4-per-row grid) with no gap from the row
               above, since .tiles .row.no-gutters only gets a row-wrap
               margin-bottom below 992px. Give it its own top margin above
               that breakpoint instead of touching the shared tile grid
               rule (which would affect every other tile too). */
            @media (min-width: 992px) {
                .hostedai-wallet-tile {
                    margin-top: 20px;
                }
            }
            .hostedai-wallet-hint {
                font-size: 11px !important;
                opacity: 0.65;
                margin-top: 2px;
                line-height: 1.3;
            }
            </style>
            {/literal}

        </div>
    </div>
    <div class="client-home-cards">
        <div class="row">
            <div class="col-lg-9">
                {function name=outputHomePanels}
                <div menuItemName="{$item->getName()}"
                    class="card card-accent-{$item->getExtra('color')}{if $item->getClass()} {$item->getClass()}{/if}"
                    {if $item->getAttribute('id')} id="{$item->getAttribute('id')}" {/if}>
                    <div class="card-header">
                        <h3 class="card-title m-0">
                            {if $item->getExtra('btn-link') && $item->getExtra('btn-text')}
                            <div class="float-right">
                                <a href="{$item->getExtra('btn-link')}"
                                    class="btn btn-default bg-color-{$item->getExtra('color')} btn-xs">
                                    {if $item->getExtra('btn-icon')}<i class="{$item->getExtra('btn-icon')}"></i>{/if}
                                    {$item->getExtra('btn-text')}
                                </a>
                            </div>
                            {/if}
                            {if $item->hasIcon()}<i class="{$item->getIcon()}"></i>&nbsp;{/if}
                            {$item->getLabel()}
                            {if $item->hasBadge()}&nbsp;<span class="badge">{$item->getBadge()}</span>{/if}
                        </h3>
                    </div>
                    {if $item->hasBodyHtml()}
                    <div class="card-body">
                        {$item->getBodyHtml()}
                    </div>
                    {/if}
                    {if $item->hasChildren()}
                    <div
                        class="list-group{if $item->getChildrenAttribute('class')} {$item->getChildrenAttribute('class')}{/if}">
                        {foreach $item->getChildren() as $childItem}
                        {if $childItem->getUri()}
                        <a menuItemName="{$childItem->getName()}" href="{$childItem->getUri()}"
                            class="list-group-item list-group-item-action{if $childItem->getClass()} {$childItem->getClass()}{/if}{if $childItem->isCurrent()} active{/if}"
                            {if $childItem->getAttribute('dataToggleTab')} data-toggle="tab"
                            {/if}{if $childItem->getAttribute('target')} target="{$childItem->getAttribute('target')}"
                            {/if} id="{$childItem->getId()}">
                            {if $childItem->hasIcon()}<i class="{$childItem->getIcon()}"></i>&nbsp;{/if}
                            {$childItem->getLabel()}
                            {if $childItem->hasBadge()}&nbsp;<span class="badge">{$childItem->getBadge()}</span>{/if}
                        </a>
                        {else}
                        <div menuItemName="{$childItem->getName()}"
                            class="list-group-item list-group-item-action{if $childItem->getClass()} {$childItem->getClass()}{/if}"
                            id="{$childItem->getId()}">
                            {if $childItem->hasIcon()}<i class="{$childItem->getIcon()}"></i>&nbsp;{/if}
                            {$childItem->getLabel()}
                            {if $childItem->hasBadge()}&nbsp;<span class="badge">{$childItem->getBadge()}</span>{/if}
                        </div>
                        {/if}
                        {/foreach}
                    </div>
                    {/if}
                    <div class="card-footer">
                        {if $item->hasFooterHtml()}
                        {$item->getFooterHtml()}
                        {/if}
                    </div>
                </div>
                {/function}

                {foreach $panels as $item}
                {if $item->getExtra('colspan')}
                {outputHomePanels}
                {assign "panels" $panels->removeChild($item->getName())}
                {/if}
                {/foreach} 

                <!-- new design -->
                <div class="active-services-card card" id="servicesPanel">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="title d-flex">
                            <span class="circle pulse blue"></span>
                           <h3 class="card-title m-0">{$LANG.clientHomePanels.activeProductsServices}</h3>
                        </div>
                        <div class="arrow">
                            <a href="clientarea.php?action=services"> <i class="fa fa-angle-right"></i></a>
                        </div>
                    </div>

                    <div class="card-body list-group">
                        <table class="table service-table">
                            <thead>
                                <tr>
                                    <th>{$LANG.navservices}</th>
                                    <th>{$LANG.orderprice}</th>
                                    <th>{$LANG.servicesRenew}</th>
                                    <th>{$LANG.domainstatus}</th>
                                    <th></th>
                                </tr>
                            </thead>

                            <tbody>
                                    {foreach $dashclientservices as $service}
                                        <tr>
                                            <td>
                                                {$service->product_name}
                                                {if $service->domain}
                                                    <div class="sub-domain">{$service->domain}</div>
                                                {/if}
                                            </td>

                                            <td>
                                               {$service->prefix}{$service->firstpaymentamount}{$service->suffix}
                                            </td>

                                            <td>
                                                {if $service->nextduedate != "0000-00-00"}
                                                    {$service->nextduedate|date_format:"%d %b %Y"}
                                                {else}
                                                    -
                                                {/if}
                                            </td>

                                            <td>
                                                <p class="label label-success"><span class="circle-dott green"></span>
                                                    {$service->domainstatus}
                                                </p>
                                            </td>

                                            <td class="productdetails-action">
                                                <a href="clientarea.php?action=productdetails&id={$service->service_id}">
                                                        <i class="fa fa-cog"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    {/foreach}

                                   {if $dashclientservices->isEmpty()}
                                        <tr><td colspan="4">{$LANG.clientHomePanels.activeProductsServicesNone}</td></tr>
                                    {/if}
                                </tbody>
                        </table>
                    </div>

                    <div class="card-footer">
                        <a href="cart.php" class="order-service">
                            <i class="fa fa-plus-circle"></i>
                            {$LANG.navservicesorder}
                        </a>
                    </div>
                </div>

            </div>
            <div class="col-lg-3 coman-box dasboard-inner-section panel-billing">
                <div class="box-100">
                    <div class="panel panel-default panel-b-height">
                        <div class="panel-heading">
                            <div class="healing-circle-wrapper d-flex">
                                <span class="circle pulse green"></span>
                                <h3>{$LANG.latestBilling}</h3>
                            </div>
                            <a href="{$WEB_ROOT}/clientarea.php?action=masspay&all=true">{$LANG.safoziDashDetailsText}</a>

                        </div>
                        <div class="panel-body">
                            {if $unpaidInvoiceBalance}
                            <div class="panel-content billing-panel">
                                <h3>{$unpaidInvoiceBalance}</h3>
                            </div>
                            <div class="avail-cred">
                                <ul class="avail-cred-list">
                                    <li>{$LANG.safoziDashcreditBalance}</li>
                                    <li class="avail-credit-blnc">{$availablecreditBalance}</li>
                                </ul>
                                <ul class="avail-cred-list">
                                    <li>{$LANG.safoziDashnextduedate}</li>
                                    <li>
                                        {if $firstdueProductdate->nextduedate != "0000-00-00"}
                                                {$firstdueProductdate->nextduedate|date_format:"%d %b %Y"}
                                        {else}
                                            -
                                        {/if}
                                    </li>
                                </ul>
                            </div>

                            <div class="panel-footer">
                                <a href="{$WEB_ROOT}/clientarea.php?action=masspay&all=true"
                                    class="panel-footer-btn wgs_custom-btn"><span>{$LANG.masspayall}</span> <i
                                        class="fa fa-plus-circle" aria-hidden="true"></i>
                                </a>
                            </div>
                            {else}
                            <div class="panel-content billing-panel">
                                <h3>{$currentCurrencySymbol}0.00</h3>
                                <p class="no-invoice-text">{$LANG.noinvoicesduemsg}</p>
                                <div class="avail-cred">
                                    <ul class="avail-cred-list">
                                        <li>{$LANG.safoziDashcreditBalance}</li>
                                    <li>{if $availablecreditBalance} {$availablecreditBalance} {else} -- {/if}</li>
                                    </ul>
                                    <ul class="avail-cred-list">
                                        <li>{$LANG.safoziDashnextduedate}</li>
                                        <li>
                                            {if $firstdueProductdate->nextduedate && $firstdueProductdate->nextduedate != "0000-00-00"}
                                                    {$firstdueProductdate->nextduedate|date_format:"%d %b %Y"}
                                            {else}
                                                --
                                            {/if}
                                        </li>
                                </ul>
                                </div>

                                <div class="panel-footer">
                                    <a href="{$WEB_ROOT}/clientarea.php?action=addfunds"
                                        class="panel-footer-btn wgs_custom-btn"><span>{$LANG.addfunds}</span> <i
                                            class="fa fa-plus-circle" aria-hidden="true"></i>
                                    </a>
                                </div>
                                {/if}
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            
            {if $panels|@count is odd || $panels|@count == 2}
                </div>
            {/if}
             <!--this div is added extra for dynamic closing div-->
            <!-- domain search section -->
            <div class="row">
                {if $registerdomainenabled || $transferdomainenabled}
                <div class="col-12 col-sm-12 col-md-6 col-lg-9 coman-box registerDomainSec">
                    <div class="card card-accent-emerald" id="registerDomainPanel">
                        <div class="card-header">
                            <h3 class="card-title m-0 d-flex">
                                <span class="circle pulse green"></span>&nbsp; {$LANG.registerdomainname}
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="check-availability-text">{$LANG.safozidomainregisDesc}</p>
                            <form method="post" action="domainchecker.php">
                                <div class="margin-10 m-0 px-2 pb-2">
                                    <div class="row dash-domain-sec">
                                        <div class="col-md-9">
                                            <input type="text" placeholder="{$LANG.exampledomain}" name="domain"
                                                class="form-control" autocapitalize="none" data-toggle="tooltip"
                                                data-placement="left" data-trigger="manual"
                                                title="{lang key='orderForm.required'}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="input-group-btn input-group-append">
                                                {if $registerdomainenabled}
                                                <input type="submit" value="Register" class="btn btn-success ">
                                                {/if}
                                                <!-- {if $transferdomainenabled}
                                            <input type="submit" name="transfer" value="Transfer" class="btn btn-default ">
                                            {/if} -->
                                            </div>
                                        </div>
                                    </div>

                                </div>
                                {if $dashboardSpotlightTlds}
                                <div class="spotlight-tlds">
                                    {foreach $dashboardSpotlightTlds as $tld}
                                    <span>{$tld}</span>
                                    {/foreach}
                                </div>
                                {/if}
                            </form>
                            <div class="card-footer">
                            </div>
                        </div>

                    </div>
                </div>
                {/if}
                <div class="client-details-sec col-12 col-sm-12 col-md-6 col-lg-3">
                    <div class="dasboard-client-details card">
                        <div class="dash-head">
                            <a href="clientarea.php?action=details" class="btn btn-success btn-sm btn-block">
                                <i class="fas fa-pencil-alt"></i> Update
                            </a>
                        </div>
                        <div class="dash-name-email d-flex">
                            <div class="short-name-wrapper">
                                <span class="short-name">{$clientsdetails.firstname|substr:0:1|upper}{$clientsdetails.lastname|substr:0:1|upper}</span>
                            </div>
                            <div class="dash-name-email-wrapper">
                                <h5>{$clientsdetails.fullname}&nbsp;{$loggedinUser->last_name}</h5>
                                <p>{$clientsdetails.email}</p>
                            </div>
                        </div>
                        <div class="dash-client-address">
                            <img src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/dashboard-map-ix.svg" alt="address1">
                            <p> {$clientsdetails.address1}</p>
                        </div>
                        <div class="dash-client-mobile">
                           <img src="{$WEB_ROOT}/templates/{$template}/images/dashboard-images/dashboard-phone-ix.svg" alt="phonenumber">
                           <p> {$clientsdetails.phonenumber}</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end domain section -->
            <div class="row">


                {foreach $panels as $item}

                {if $item@iteration is odd}
                <div class="col-md-6">
                    {outputHomePanels}
                </div>
                {/if}

                {/foreach}

                <!--this div is custom close beacouse whmcs not close tag properly-->
           


            
                {foreach $panels as $item}
                {if $item@iteration is even}
                <div class="col-md-6">
                    {outputHomePanels}
                </div>
                {/if}
                {/foreach}
            </div>
        </div>
    </div>
    {foreach $addons_html as $addon_html}
    <div>
        {$addon_html}
    </div>
    {/foreach}