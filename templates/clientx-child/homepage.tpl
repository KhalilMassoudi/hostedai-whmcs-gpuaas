{if $hostedaiWalletBalance !== null}
<div class="account-credit-banner">
    <div class="account-credit-banner-info">
        <span class="account-credit-banner-label">{lang key='homepage.yourAccount'} &mdash; GPU Credits</span>
        <span class="account-credit-banner-amount {if $hostedaiWalletBalance > 0}account-credit-positive{else}account-credit-zero{/if}">
            ${$hostedaiWalletBalance|string_format:"%.2f"}
        </span>
        <span class="account-credit-banner-note" style="display:block;font-size:11px;opacity:.7;margin-top:2px">
            Shared balance across all your Pay-As-You-Go GPU services — separate from any other account balance. Top up, then choose your GPU Cloud.
        </span>
    </div>
    <a href="{$WEB_ROOT}/{$hostedaiWalletTopupUrl}" class="account-credit-banner-btn">Recharge GPU Credits</a>
</div>
{/if}
{if !empty($productGroups) || $registerdomainenabled || $transferdomainenabled}
    {$domainCount = 0}
    {if $registerdomainenabled}
        {$domainCount = $domainCount+1}
    {/if}
    {if $transferdomainenabled}
        {$domainCount = $domainCount+1}
    {/if}
    {$domainCount = $domainCount+$productGroups|@count}
    <div class="home-page-product-heading">
        <h2 class="text-center m-4">{lang key='clientHomePanels.productsAndServices'}</h2>
        {if $domainCount > 4}
            <div class="switcher-for-product">
                <span class="switch-slider active" slider-status="on" onclick="sliderIntiateOnClick(this,'on');">{$LANG.productSlider}</span>
                <span class="switch-slider" slider-status="off" onclick="sliderIntiateOnClick(this,'off');">{$LANG.allProducts}</span>
            </div>
        {/if}
    </div>  
    <div class="card-columns home home-page-product-listing{if $domainCount > 4} home-page-slider{/if}" id="slider-product-group">
        {assign var=headColorClass value=['1'=>'p-blue','2'=>'p-lightblue','3'=>'p-green', '4' => 'p-yellow']}
        {$headindexed = 1}
        {foreach $productGroups as $key => $productGroup}
            <div class="card mb-3 slide">
                <div class="card-body p-lg-4 p-xl-5 text-center">
                    <h3 class="card-title pricing-card-title {$headColorClass.$headindexed}">
                        {$productGroup->name}
                    </h3>
                    <p>{$productGroup->tagline}</p>
                    <a href="{$productGroup->getRoutePath()}" class="wgs_custom-btn">
                        {lang key='browseProducts'}
                    </a>
                </div>
            </div>
            {$headindexed = $headindexed + 1}
            {if $headindexed gt 4}
                {$headindexed = 1}
            {/if}
        {/foreach}
        {if $registerdomainenabled}
            <div class="card mb-3 slide">
                <div class="card-body p-lg-4 p-xl-5 text-center">
                    <h3 class="card-title pricing-card-title p-blue">
                        {lang key='orderregisterdomain'}
                    </h3>
                    <p>{lang key='secureYourDomain'}</p>
                    <a href="{$WEB_ROOT}/cart.php?a=add&domain=register" class="wgs_custom-btn">
                        {lang key='navdomainsearch'}
                    </a>
                </div>
            </div>
        {/if}
        {if $transferdomainenabled}
            <div class="card mb-3 slide">
                <div class="card-body p-lg-4 p-xl-5 text-center">
                    <h3 class="card-title pricing-card-title p-blue">
                        {lang key='transferYourDomain'}
                    </h3>
                    <p>{lang key='transferExtend'}</p>
                    <a href="{$WEB_ROOT}/cart.php?a=add&domain=transfer" class="wgs_custom-btn">
                        {lang key='transferYourDomain'}
                    </a>
                </div>
            </div>
        {/if}
    </div>
{/if}
<div class="can-help-home-page">
    <h2 class="text-center m-4">{lang key='howCanWeHelp'}</h2>
    <div class="row my-5 action-icon-btns">
        <div class="col-6 col-md-4 col-lg">
            <a href="{routePath('announcement-index')}">
                <figure class="ico-container">
                    <i class="fal fa-bullhorn"></i>
                </figure>
                {lang key='announcementstitle'}
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="serverstatus.php">
                <figure class="ico-container">
                    <i class="fal fa-server"></i>
                </figure>
                {lang key='networkstatustitle'}
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{routePath('knowledgebase-index')}">
                <figure class="ico-container">
                    <i class="fal fa-book"></i>
                </figure>
                {lang key='knowledgebasetitle'}
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="{routePath('download-index')}">
                <figure class="ico-container">
                    <i class="fal fa-download"></i>
                </figure>
                {lang key='downloadstitle'}
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="submitticket.php">
                <figure class="ico-container">
                    <i class="fal fa-life-ring"></i>
                </figure>
                {lang key='homepage.submitTicket'}
            </a>
        </div>
    </div>
</div>
<div class="your-account-home-page">
    <h2 class="text-center m-4">{lang key='homepage.yourAccount'}</h2>
    <div class="row my-5 action-icon-btns">
        <div class="col-6 col-md-4 col-lg">
            <a href="clientarea.php">
                <figure class="ico-container">
                    <i class="fal fa-home"></i>
                </figure>
                {lang key='homepage.yourAccount'}
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="clientarea.php?action=services">
                <figure class="ico-container">
                    <i class="far fa-cubes"></i>
                </figure>
                {lang key='homepage.manageServices'}
            </a>
        </div>
        {if $registerdomainenabled || $transferdomainenabled || $numberOfDomains}
            <div class="col-6 col-md-4 col-lg">
                <a href="clientarea.php?action=domains">
                    <figure class="ico-container">
                        <i class="fal fa-globe"></i>
                    </figure>
                    {lang key='homepage.manageDomains'}
                </a>
            </div>
        {/if}
        <div class="col-6 col-md-4 col-lg">
            <a href="supporttickets.php">
                <figure class="ico-container">
                    <i class="fal fa-comments"></i>
                </figure>
                {lang key='homepage.supportRequests'}
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <a href="clientarea.php?action=masspay&all=true">
                <figure class="ico-container">
                    <i class="fal fa-credit-card"></i>
                </figure>
                {lang key='homepage.makeAPayment'}
            </a>
        </div>
    </div>
</div>
{if $homePageLatestLayout}
    {if $announcements}
    <div class="news-sec">
        <div class="container">
            <div class="row">
                <div class="col-sm-12">
                    <div class="news-title-sec"><h2>{$LANG.news}</h2>
                    <p>{$LANG.announcementsdescription}</p></div>
                </div>
            </div>
            <div class="row news-box-row">
                {foreach $announcements as $announcement}	  
                    <div class="col-sm-6">
                        <div class="media-news">
                            <div class="left-img-icon">
                                <object class="icon-5" type="image/svg+xml" data="{$WEB_ROOT}/templates/{$template}/images/icon-news.svg"></object>
                            </div>
                            <div class="image-right-body">
                                <a href="{routePath('announcement-view', $announcement.id, $announcement.urlfriendlytitle)}"><h4>{$announcement.title}</h4>
                                    {if $announcement.text|strip_tags|strlen < 100}
                                        <p>{$announcement.text}</p>
                                    {else}
                                        <p>{$announcement.summary|truncate:100}</p>
                                    {/if}
                                </a>
                            </div>
                        </div>
                    </div>
                {/foreach}	 		 
            </div>
        </div>
    </div>
    {/if}
{/if}