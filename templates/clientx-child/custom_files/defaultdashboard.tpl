<div class="them-default-home-page bg-white">
    {if $hostedaiWalletBalance !== null}
    <div class="account-credit-banner">
        <div class="account-credit-banner-info">
            <span class="account-credit-banner-label">GPU Credits</span>
            <span class="account-credit-banner-amount {if $hostedaiWalletBalance > 0}account-credit-positive{else}account-credit-zero{/if}">
                {$hostedaiWalletBalance|string_format:"%.2f"} DT
            </span>
            <span class="account-credit-banner-note" style="display:block;font-size:11px;opacity:.7;margin-top:2px">
                Shared balance across all your Pay-As-You-Go GPU services — separate from any other account balance. Top up, then choose your GPU Cloud.
            </span>
        </div>
        <a href="{$WEB_ROOT}/{$hostedaiWalletTopupUrl}" class="account-credit-banner-btn">Recharge GPU Credits</a>
    </div>
    {/if}
    {if $showAnouncementHomePage eq 'on'}
        {if $announcementsHomePages|@count gt '0'}
            <section class="announcements-section">
                <div class="announcements-home-page {if $announcementsHomePages|@count gt '3'}announcements-slider-home-page{/if}">
                    {foreach from=$announcementsHomePages item=announcmentsData}
                        <div class="announcement-slider-item col-md-4" onclick="window.location.href = 'index.php/announcements/{$announcmentsData.id}/{$announcmentsData.urlfriendlytitle}.html'">
                            <div class="item-announcement-section">
                                <div class="left-section-announcement">
                                    <div class="url-announcement-section-left">
                                        <div class="an-icon-left"><i class="fa fa-bullhorn"></i></div>
                                        <div class="an-text-right">
                                        <a href="index.php/announcements/{$announcmentsData.id}/{$announcmentsData.urlfriendlytitle}.html">{$announcmentsData.title}</a> 
                                        <p class="announcement-date">{$announcmentsData.date}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    {/foreach}
                </div>
            </section>
        {/if}
    {/if}
    <section class="default-home-page-tiles">
        <div class="row">
            <div class="col-md-3" onclick="window.location = 'clientarea.php?action=services'">
                <div class="hosting-panal"> <span class="host-user">{$clientsstats.productsnumactive}</span>
                    <div class="panel-icon"><img src="{$WEB_ROOT}/templates/{$template}/images/host-icone.png" alt="#"></div>
                    <h2>{$LANG.activehosting_title}</h2>
                    <p>{$LANG.activehosting_description}</p>
                </div>
            </div>
            <div class="col-md-3" onclick="window.location = 'supporttickets.php'">
                <div class="hosting-panal tick"> <span class="host-user">{$clientsstats.numactivetickets}</span>
                    <div class="panel-icon"><img src="{$WEB_ROOT}/templates/{$template}/images/ticket.png" alt="#"></div>
                    <h2>{$LANG.activetickets_title}</h2>
                    <p>{$LANG.activetickets_description}</p>
                </div>
            </div>
            <div class="col-md-3" onclick="window.location = 'clientarea.php?action=invoices'">
                <div class="hosting-panal invo"> <span class="host-user">{$clientsstats.numunpaidinvoices}</span>
                    <div class="panel-icon"><img src="{$WEB_ROOT}/templates/{$template}/images/due.png" alt="#"></div>
                    <h2>{$LANG.dueinvoices_title}</h2>
                    <p>{$LANG.dueinvoices_description}</p>
                </div>
            </div>
            {if $clientsstats.numdomains || $registerdomainenabled || $transferdomainenabled}
                <div class="col-md-3" onclick="window.location = 'clientarea.php?action=domains'">
                    <div class="hosting-panal regis"> <span class="host-user">{$clientsstats.numactivedomains}</span>
                        <div class="panel-icon"><img src="{$WEB_ROOT}/templates/{$template}/images/reg.png" alt="#"></div>
                        <h2>{$LANG.registerdomain_title}</h2>
                        <p>{$LANG.registerdomain_description}</p>
                    </div>
                </div>
            {elseif $condlinks.affiliates && $clientsstats.isAffiliate}
                <div class="col-md-3" onclick="window.location = 'affiliates.php'">
                    <div class="hosting-panal regis"> <span class="host-user">{$clientsstats.numaffiliatesignups}</span>
                        <div class="panel-icon"><img src="{$WEB_ROOT}/templates/{$template}/images/reg.png" alt="#"></div>
                        <h2>{$LANG.affiliatessignups}</h2>
                        <p>{$LANG.affiliatessignupsdescription}</p>
                    </div>
                </div>
            {else}
                <div class="col-md-3" onclick="window.location = 'clientarea.php?action=quotes'">
                    <div class="hosting-panal regis"> <span class="host-user">{$clientsstats.numquotes}</span>
                        <div class="panel-icon"><img src="{$WEB_ROOT}/templates/{$template}/images/management.png" alt="#"></div>
                        <h2>{$LANG.quotes}</h2>
                        <p>{$LANG.quotesdescription}</p>
                    </div>
                </div>
            {/if}
        </div>
    </section>
    <section class="default-home-active-product-service {if $disableActiveProduct neq 'off'}w-hidden{/if}">
        {function name=outputHomePanels}
            {if $item->getAttribute('id') != 'registerDomainPanel'}
                <div menuItemName="{$item->getName()}" class="card card-accent-{$item->getExtra('color')}{if $item->getClass()} {$item->getClass()}{/if}"{if $item->getAttribute('id')} id="{$item->getAttribute('id')}"{/if}>
                    <div class="card-header">
                        <h3 class="card-title m-0">
                            {if $item->getExtra('btn-link') && $item->getExtra('btn-text')}
                                <div class="float-right">
                                    <a href="{$item->getExtra('btn-link')}" class="btn btn-default bg-color-{$item->getExtra('color')} btn-xs">
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
                    <div class="row">
                        {if $item->hasBodyHtml()}
                            <div class="col-md-12 html-section-home-page">
                                <div class="card-body">
                                    {$item->getBodyHtml()}
                                </div>
                            </div>
                        {/if}
                        {if $item->hasChildren()}
                            <div class="list-group{if $item->getChildrenAttribute('class')} {$item->getChildrenAttribute('class')}{/if} {if $item->getAttribute('id') == 'servicesPanel'}col-md-12{/if}">
                                {foreach $item->getChildren() as $childItem}
                                    {if $childItem->getUri()}
                                        <a menuItemName="{$childItem->getName()}" href="{$childItem->getUri()}" class="list-group-item list-group-item-action{if $childItem->getClass()} {$childItem->getClass()}{/if}{if $childItem->isCurrent()} active{/if} {if $item->getAttribute('id') == 'servicesPanel'}col-md-6{/if}"{if $childItem->getAttribute('dataToggleTab')} data-toggle="tab"{/if}{if $childItem->getAttribute('target')} target="{$childItem->getAttribute('target')}"{/if} id="{$childItem->getId()}">
                                            {if $childItem->hasIcon()}<i class="{$childItem->getIcon()}"></i>&nbsp;{/if}
                                            {$childItem->getLabel()}
                                            {if $childItem->hasBadge()}&nbsp;<span class="badge">{$childItem->getBadge()}</span>{/if}
                                        </a>
                                    {else}
                                        <div menuItemName="{$childItem->getName()}" class="list-group-item list-group-item-action{if $childItem->getClass()} {$childItem->getClass()}{/if} {if $item->getAttribute('id') == 'servicesPanel'}col-md-6{/if}" id="{$childItem->getId()}">
                                            {if $childItem->hasIcon()}<i class="{$childItem->getIcon()}"></i>&nbsp;{/if}
                                            {$childItem->getLabel()}
                                            {if $childItem->hasBadge()}&nbsp;<span class="badge">{$childItem->getBadge()}</span>{/if}
                                        </div>
                                    {/if}
                                {/foreach}
                            </div>
                        {/if}
                    </div>
                </div>
            {/if}
        {/function}
        {foreach $panels as $item}
            {if $item->getExtra('colspan')}
                {outputHomePanels}
                {assign "panels" $panels->removeChild($item->getName())}
            {/if}
        {/foreach}
    </section>
    {if $custom_domains and $disableDomains eq 'off'}
        <section class="default-home-domain-services">
            <div class="home-domain-header">
                <h3>{$LANG.cartproductdomain}</h3>
            </div>
            <div class="row home-domain-main-section {if $custom_domains|@count gt '3'}home-domain-slider-section{/if}">
                {foreach from=$custom_domains key=num item=domain}
                    <div class="col-md-4 slider-content-domain">
                        {if $domain.status eq 'Pending'}
                            <div class="pending-domain-home-page">
                                <div class="domain-icon-home-page">
                                    <img src="{$WEB_ROOT}/templates/{$template}/images/domin-iconeb.png" alt="{$domain.domain}">
                                </div>
                                <h3 class="domain-head-name">{$domain.domain}</h3>
                                <div class="pending-domain-button-home-page">
                                    <a href="{$WEB_ROOT}/clientarea.php?action=domaindetails&id={$domain.id}" class="btn-domain-list-home-page">{$LANG.domainsPending}</a>
                                </div>
                                <a href="{$WEB_ROOT}/domainchecker.php" class="home-page-domain-checker-link">{$LANG.clientareaHomePageAddonTextSearchDomain}</a>
                            </div>
                        {elseif $domain.status eq 'Active'}
                            <div class="active-domain-home-page">
                                <div class="domain-icon-home-page">
                                    <img src="{$WEB_ROOT}/templates/{$template}/images/domin-iconeg.png" alt="{$domain.domain}">
                                </div>
                                <h3 class="domain-head-name">{$domain.domain}</h3>
                                <div class="active-domain-button-home-page">
                                    <a href="{$WEB_ROOT}/clientarea.php?action=domaindetails&id={$domain.id}" class="btn-domain-list-home-page">{$LANG.domainsActive}</a>
                                </div>
                                <div class="domain-manage-section-home-page">
                                    <ul class="home-page-domain-buttons-list">
                                        <li class="manage-btn-home-domain">
                                            <a href="{$WEB_ROOT}/clientarea.php?action=domaindetails&id={$domain.id}" data-toggle="tooltip" data-placement="top" title="" data-original-title="{$LANG.manage}"><i class="fas fa-cog"></i></a>
                                        </li>
                                        <li class="renew-btn-home-domain">
                                            <a href="{$WEB_ROOT}/index.php?rp=/domain/{$domain.domain}/renew" data-toggle="tooltip" data-placement="top" title="" data-original-title="{$LANG.domainsrenew}"><i class="fas fa-sync-alt"></i></a>
                                        </li>
                                        <li class="nameserver-btn-home-domain">
                                            <a href="{$WEB_ROOT}/clientarea.php?action=domaindetails&id={$domain.id}#tabNameservers" data-toggle="tooltip" data-placement="top" title="" data-original-title="{$LANG.domainmanagens}"> <i class="fas fa-globe"></i></a>
                                        </li>                                       
                                    </ul>
                                </div>                                
                            </div>
                        {/if}
                    </div>
                {/foreach}
            </div>
        </section>
    {/if}
    <section class="home-page-last-panels-section">
        <div class="home-page-cart-domain-part row">
            {if $registerdomainenabled OR $transferdomainenabled}
                <div class="col-md-6">
                    <div class="domain-section-home-page-search">
                        <h3 class="heading-title-domain-home-search">{$LANG.clientareaHomePageAddonTextSearchNewDomain}</h3>
                        <form method="post" action="domainchecker.php">
                            <div class="domain-search-form-inner">
                                <input type="text" class="form-control"  placeholder="{$LANG.findyourdomain}" id="domainname" name="domain" required="">
                                <span class="search-button-section-home-domain">
                                    <button type="submit"> <span class="fas fa-search"></span></button>
                                </span> 
                            </div>
                        </form>
                    </div>
                </div>
            {/if}
            <div class="col-md-6">
                <div class="cart-section-count-home-page">
                    <div class="left-section-home-cart">
                        <h3 class="heading-title-cart-home">
                            {if $LANG.youhavecartitemcount|strstr:'% s'} 
                                {$LANG.youhavecartitemcount|replace:'% s':$cartitemcount}
                            {elseif $LANG.youhavecartitemcount|strstr:'%s'}
                                {$LANG.youhavecartitemcount|replace:'%s':$cartitemcount}
                            {/if}
                        </h3>
                        <div class="button-cart-home">
                            {if $cartitemcount > 0 }
                                <a href="{$WEB_ROOT}/cart.php?a=view" class="btn-home-cart">{$LANG.completeorder}</a>
                            {else}
                                <a href="{$WEB_ROOT}/cart.php" class="btn-home-cart">{$LANG.ordernowbutton}</a>
                            {/if}
                        </div>
                    </div>
                    <div class="right-section-home-cart">
                        <img src="{$WEB_ROOT}/templates/{$template}/images/shopping-bkt.png" alt="Shopping Cart">
                    </div>
                </div>
            </div>
        </div>
    </section>
    {foreach $addons_html as $addon_html}
        <div class="home-page-promo-slider">
            {$addon_html}
        </div>
    {/foreach}
    <section class="home-page-default-panels-execute">
        <div class="row panel-home-default-inner-section">
            <div class="col-md-6 col-lg-12 col-xl-6">
                {foreach $panels as $item}
                    {if $item@iteration is odd}
                        {outputHomePanels}
                    {/if}
                {/foreach}
            </div>
            <div class="col-md-6 col-lg-12 col-xl-6">
                {foreach $panels as $item}
                    {if $item@iteration is even}
                        {outputHomePanels}
                    {/if}
                {/foreach}
            </div>
        </div>       
    </section>
</div>