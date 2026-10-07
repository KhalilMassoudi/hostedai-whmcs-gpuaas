<style>
.offerings-panel tr td p {
    display: none;
}
.offerings-panel tr td p:first-child {
    text-overflow: ellipsis;
    white-space: nowrap;
    width: 240px;
    overflow: hidden;
    display: block;
}
.offerings-panel hr {
    display: none;
}
</style>

<div class="theme-latest-dashboard-page">
	{if $hostedaiWalletBalance !== null}
	<div class="account-credit-banner">
		<div class="account-credit-banner-info">
			<span class="account-credit-banner-label">Hosted AI Wallet</span>
			<span class="account-credit-banner-amount {if $hostedaiWalletBalance > 0}account-credit-positive{else}account-credit-zero{/if}">
				${$hostedaiWalletBalance|string_format:"%.2f"}
			</span>
		</div>
		<a href="{$WEB_ROOT}/{$hostedaiWalletTopupUrl}" class="account-credit-banner-btn">Recharge Wallet</a>
	</div>
	{/if}
	<div class="new-dashboard-head-bar">
		<div class="pull-left">
			<div class="order"><span><img src="{$WEB_ROOT}/templates/{$template}/images/dash-icon.svg"
						alt="Dash Icon"></span>{$LANG.latestDashboard}</div>
		</div>
	</div>
	<div class="dasboard-inner-section">
		<div class="row">
			<div class="col-12 col-sm-12 col-md-12 col-lg-6 coman-box">
				<div class="panel panel-default panel-b-height">
					<div class="panel-heading">
						<h3>{$LANG.clientHomePanels.activeProductsServices}</h3>
						{if !empty($active_services)}
						<a href="{$WEB_ROOT}/clientarea.php?action=services">{$LANG.viewAll}</a>
						{/if}
					</div>
					<div class="panel-body">
						<div
							class="panel-content active-tickets-panel offerings-panel mh-200 panel-services-client {if empty($active_services)}product-empty{/if}">
							<div class="table-responsive">
								<table class="tickets-table table">
									<tbody>
										{if !empty($active_services)}
										{foreach from=$active_services key=num item=active_service}
										{if $num <= 4} <tr>
											<td class="ticket-name">{$active_service.p_name} - {$active_service.domain}
											</td>
											<td>{$currentCurrencySymbol}{$active_service.amount}</td>
											{assign var="timestamp" value=strtotime($active_service.nextduedate)}
											<td>{date('F d, Y', $timestamp)}</td>
											<td><a
													href="{$WEB_ROOT}/clientarea.php?action=productdetails&id={$active_service.id}"><i
														class="fa fa-cog"></i></a></td>
											<td><span class="ticket-status active">{$active_service.domainstatus}</span>
											</td>
											</tr>
											{/if}
											{/foreach}
											{else}
											<tr>
												<td class="ticket-name no-product-serv">
													<img
														src="{$WEB_ROOT}/templates/{$template}/images/home-product-icon.svg">
													<p>{$LANG.clientHomePanels.activeProductsServicesNone}</p>
												</td>
											</tr>
											{/if}
									</tbody>
								</table>
							</div>
						</div>

						{if !empty($active_services)}
						<div class="panel-footer">
							<a href="{$WEB_ROOT}/cart.php"
								class="panel-footer-btn"><span>{$LANG.navservicesorder}</span> <i
									class="fa fa-plus-circle" aria-hidden="true"></i>
							</a>
						</div>
						{/if}
					</div>
				</div>
			</div>
			<div class="col-12 col-sm-6 col-md-6 col-lg-3 coman-box">
				<div class="panel panel-default">
					<div class="panel-heading">
						<h3>{$LANG.navservices}</h3>
					</div>
					<div class="panel-body">
						<div class="panel-content services-panel">
							<h3>{$num_active_services}</h3>
							<span class="sevice-status active">{$LANG.latestActiveService}</span>
							<p>{$LANG.latestAddtionalService}</p>
						</div>
						{if $num_active_services gt 0}
						<div class="panel-footer">
							<a href="{$WEB_ROOT}/clientarea.php?action=services"
								class="panel-footer-btn"><span>{$LANG.viewAll}</span> <i class="fa fa-cog"
									aria-hidden="true"></i>
							</a>
						</div>
						{/if}
					</div>
				</div>
			</div>
			<div class="col-12 col-sm-6 col-md-6 col-lg-3 coman-box">
				<div class="box-100">
					<div class="panel panel-default panel-b-height">
						<div class="panel-heading">
							<h3>{$LANG.latestBilling}</h3>
						</div>
						<div class="panel-body">
							{if $unpaidInvoiceBalance}
							<div class="panel-content billing-panel">
								<h3>{$unpaidInvoiceBalance}</h3>
								<span class="sevice-status">{$LANG.latestTotalBalance}</span>
								<p>{$LANG.latestReviewCurrent}</p>
							</div>
							<div class="panel-footer">
								<a href="{$WEB_ROOT}/clientarea.php?action=masspay&all=true"
									class="panel-footer-btn"><span>{$LANG.masspayall}</span> <i
										class="fa fa-plus-circle" aria-hidden="true"></i>
								</a>
							</div>
							{else}
							<div class="panel-content billing-panel">
								<h3>{$currentCurrencySymbol}0.00</h3>
								<span class="sevice-status">{$LANG.latestTotalBalance}</span>
								<p>{$LANG.noinvoicesduemsg}</p>
							</div>
							<div class="panel-footer">
								<a href="{$WEB_ROOT}/clientarea.php?action=addfunds"
									class="panel-footer-btn"><span>{$LANG.addfunds}</span> <i class="fa fa-plus-circle"
										aria-hidden="true"></i>
								</a>
							</div>
							{/if}
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			{if $registerdomainenabled || $transferdomainenabled}
			<div class="col-12 col-sm-12 col-md-6 col-lg-3 coman-box registerDomainSec">
				<div class="box-100">
					<div class="panel panel-default panel-b-height">
						<div class="panel-heading">
							<h3>{$LANG.registerdomainname}</h3>
						</div>
						<div class="panel-body">
							<div class="panel-content message-panel">
								<p>{$LANG.homebegin}</p>
								<form method="post" action="domainchecker.php" id="frmDomainHomepage">
									<div class="row">
										<div class="col-md-12 col-sm-10 clx-box-12">
											<div class="input-group input-group-lg">
												<input type="text" class="form-control" name="domain"
													placeholder="{$LANG.exampledomain}" autocapitalize="none"
													data-toggle="tooltip" data-placement="left" data-trigger="manual"
													title="{lang key='orderForm.required'}" />
												<span class="input-group-btn">
													{if $registerdomainenabled}
													<input type="submit" class="btn search" value="{$LANG.search}"
														id="btnDomainSearch" />
													{/if}
													{if $transferdomainenabled}
													<input type="submit" id="btnTransfer" name="transfer"
														class="btn transfer" value="{$LANG.domainstransfer}" />
													{/if}
												</span>
											</div>
										</div>
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
			{/if}
			<div class="col-12 col-sm-6 col-md-6 col-lg-3 coman-box">
				<div class="box-100">
					<div class="panel panel-default panel-b-height">
						<div class="panel-heading">
							<h3>{$LANG.latestOrder}</h3>
							<a href="{$WEB_ROOT}/cart.php">{$LANG.latestOrderView}</a>
						</div>
						<div class="panel-body">
							<div class="panel-content order-panel">
								<img src="{$WEB_ROOT}/templates/{$template}/images/cart-icon.svg" />
								<p>{$LANG.latestOrderApps}</p>
							</div>
							<div class="panel-footer">
								<a href="{$WEB_ROOT}/cart.php"
									class="panel-footer-btn"><span>{$LANG.ordernowbutton}</span> <i
										class="fa fa-plus-circle" aria-hidden="true"></i>
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>
			{if $marketConnectPromosData}
			<div class="col-12 col-sm-12 col-md-12 col-lg-6 coman-box">
				<div class="box-100">
					<div class="panel pd-panel rcmd-panel panel-default panel-b-height">
						<div class="panel-heading">
							<h3 id="home-promo-widget-title"></h3>
						</div>
						<div class="panel-body">
							<div class="panel-content offerings-panel">
								<div class="row">
									<div class="col-md-12">
										{if $marketConnectPromosData.symantec eq '1' || $marketConnectPromosData.weebly
										eq '1' || $marketConnectPromosData.ox eq '1' ||
										$marketConnectPromosData.codeguard eq '1' || $marketConnectPromosData.sitelock
										eq '1' || $marketConnectPromosData.sitelockvpn eq '1' ||
										$marketConnectPromosData.spamexperts eq '1' ||
										$marketConnectPromosData.marketgoo eq '1' ||
										$marketConnectPromosData.sitebuilder eq '1' ||
										$marketConnectPromosData.cpanelseo eq '1' || $marketConnectPromosData.nordvpn eq '1' || $marketConnectPromosData.xovinow eq '1' || $marketConnectPromosData.threesixtymonitoring eq '1'}
										<ul class="home-page-promo-slider">
											{if $marketConnectPromosData.symantec eq '1'}
											<li class="w-hidden">
												<img
													src="{$WEB_ROOT}/templates/{$template}/marketconnect/symantec/symantec.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="symantec_title"></h4>
													</a>
													<p id="symantec_desc"></p>
												</div>
											</li>
											{/if}
											{if $marketConnectPromosData.weebly eq '1'}
											<li class="w-hidden">
												<img
													src="{$WEB_ROOT}/templates/{$template}/marketconnect/weebly/weebly.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="weebly_title"></h4>
													</a>
													<p id="weebly_desc"></p>
												</div>
											</li>
											{/if}
											{if $marketConnectPromosData.ox eq '1'}
											<li class="w-hidden">
												<img src="{$WEB_ROOT}/templates/{$template}/marketconnect/ox/ox.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="ox_title"></h4>
													</a>
													<p id="ox_desc"></p>
												</div>
											</li>
											{/if}
											{if $marketConnectPromosData.codeguard eq '1'}
											<li class="w-hidden">
												<img
													src="{$WEB_ROOT}/templates/{$template}/marketconnect/codeguard/codeguard.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="codeguard_title"></h4>
													</a>
													<p id="codeguard_desc"></p>
												</div>
											</li>
											{/if}
											{if $marketConnectPromosData.sitelock eq '1'}
											<li class="w-hidden">
												<img
													src="{$WEB_ROOT}/templates/{$template}/marketconnect/sitelock/sitelock.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="sitelock_title"></h4>
													</a>
													<p id="sitelock_desc"></p>
												</div>
											</li>
											{/if}
											{if $marketConnectPromosData.sitelockvpn eq '1'}
											<li class="w-hidden">
												<img
													src="{$WEB_ROOT}/templates/{$template}/marketconnect/sitelockvpn/sitelockvpn.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="sitelockvpn_title"></h4>
													</a>
													<p id="sitelockvpn_desc"></p>
												</div>
											</li>
											{/if}
											{if $marketConnectPromosData.spamexperts eq '1'}
											<li class="w-hidden">
												<img
													src="{$WEB_ROOT}/templates/{$template}/marketconnect/spamexperts/spamexperts.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="spamexperts_title"></h4>
													</a>
													<p id="spamexperts_desc"></p>
												</div>
											</li>
											{/if}
											{if $marketConnectPromosData.marketgoo eq '1'}
											<li class="w-hidden">
												<img
													src="{$WEB_ROOT}/templates/{$template}/marketconnect/marketgoo/marketgoo.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="marketgoo_title"></h4>
													</a>
													<p id="marketgoo_desc"></p>
												</div>
											</li>
											{/if}
											{if $marketConnectPromosData.sitebuilder eq '1'}
											<li class="w-hidden">
												<img
													src="{$WEB_ROOT}/templates/{$template}/marketconnect/sitebuilder/sitebuilder.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="sitebuilder_title"></h4>
													</a>
													<p id="sitebuilder_desc"></p>
												</div>
											</li>
											{/if}
											{if $marketConnectPromosData.cpanelseo eq '1'}
											<li class="w-hidden">
												<img
													src="{$WEB_ROOT}/templates/{$template}/marketconnect/cpanelseo/cpanelseo.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="cpanelseo_title"></h4>
													</a>
													<p id="cpanelseo_desc"></p>
												</div>
											</li>
											{/if}
											{if $marketConnectPromosData.nordvpn eq '1'}
											<li class="w-hidden">
												<img
													src="{$WEB_ROOT}/templates/{$template}/marketconnect/nordvpn/nordvpn.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="nordvpn_title"></h4>
													</a>
													<p id="nordvpn_desc"></p>
												</div>
											</li>
											{/if}
											{if $marketConnectPromosData.xovinow eq '1'}
											<li class="w-hidden">
												<img
													src="{$WEB_ROOT}/templates/{$template}/marketconnect/xovinow/xovinow.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="xovinow_title"></h4>
													</a>
													<p id="xovinow_desc"></p>
												</div>
											</li>
											{/if}
											{if $marketConnectPromosData.threesixtymonitoring eq '1'}
											<li class="w-hidden">
												<img
													src="{$WEB_ROOT}/templates/{$template}/marketconnect/threesixtymonitoring/threesixtymonitoring.svg" />
												<div class="offering-text">
													<a href="">
														<h4 id="threesixtymonitoring_title"></h4>
													</a>
													<p id="threesixtymonitoring_desc"></p>
												</div>
											</li>
											{/if}
										</ul>
										{/if}
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="home-promo-product" style="display:none;">
				{foreach from=$addons_html item=addon_html}
				<div>
					{$addon_html}
				</div>
				{/foreach}
			</div>
			{else}
			<div class="col-12 col-sm-12 col-md-12 col-lg-6 coman-box">
				<div class="box-100">
					<div class="panel pd-panel panel-default panel-b-height">
						<div class="panel-heading">
							<h3 id="home-promo-widget-title-new">{$LANG.productsnavbar}</h3>
						</div>
						<div class="panel-body">
							<div class="panel-content offerings-panel  top-offering">
								<div class="row">
									<div class="col-md-6 coman-box">
										<ul class="product-group-home-page-left">
											{$homePageGroupProductData.left}
										</ul>
									</div>
									<div class="col-md-6 coman-box">
										<ul class="product-group-home-page-right">
											{$homePageGroupProductData.right}
										</ul>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			{/if}
		</div>
		<div class="row">
			<div class="col-12 col-sm-12 col-md-6 col-lg-6 wd-65 coman-box">
				<div class="panel panel-default panel-b-height">
					<div class="panel-heading">
						<h3>{$LANG.clientHomePanels.recentSupportTickets}</h3>
						{if !empty($tickets)}
						<a href="{$WEB_ROOT}/supporttickets.php">{$LANG.viewAll} </a>
						{/if}
					</div>
					<div class="panel-body">
						<div
							class="panel-content recent-tickets-panel offerings-panel mh-200 panel-tickets-client {if empty($tickets)}ticket-empty{/if}">
							<div class="table-responsive">
								<table class="tickets-table table">
									<tbody>
										{if !empty($tickets)}
										{foreach from=$tickets key=num item=ticket}
										{if $num <= 3} <tr
											onclick="window.location = '{$WEB_ROOT}/viewticket.php?tid={$ticket.tid}&c={$ticket.c}'">
											<td class="ticket-no">#{$ticket.tid}</td>
											<td>{$ticket.subject}</td>
											{assign var="someVar" value="("|explode:$ticket.lastreply}
											{assign var="someVarOne" value=$someVar[0]|replace:'/':'-'}
											{assign var="timestampone" value=strtotime($someVarOne)}
											<td>{date('F d, Y', $timestampone)}</td>
											</tr>
											{/if}
											{/foreach}
											{else}
											<tr>
												<td class="ticket-no"></td>
												<td>
													<img
														src="{$WEB_ROOT}/templates/{$template}/images/home-tickets-icon.svg">
													<p>{$LANG.clientHomePanels.recentSupportTicketsNone}</p>
												</td>
												<td></td>
											</tr>
											{/if}
									</tbody>
								</table>
							</div>
						</div>
						{if !empty($tickets)}
						<div class="panel-footer">
							<a href="{$WEB_ROOT}/submitticket.php"
								class="panel-footer-btn"><span>{$LANG.opennewticket}</span> <i class="fa fa-plus-circle"
									aria-hidden="true"></i>
							</a>
						</div>
						{/if}
					</div>
				</div>
			</div>
			<div class="col-12 col-sm-6 col-md-6 col-lg-3 wd-35 coman-box">
				<div class="box-100">
					<div
						class="panel panel-default panel-b-height panel-support-client {if empty($support_departmentlist)}no-department{/if}">
						<div class="panel-heading">
							<h3>{$LANG.supportticketschoosedepartment}</h3>
						</div>
						<div class="panel-body">
							<div class="panel-content support-panel offerings-panel mh-200">
								<ul>
									{if !empty($support_departmentlist)}
									{foreach from=$support_departmentlist key=num item=department}
									{if $num <= 3} <li><a
											href="{$WEB_ROOT}/submitticket.php?step=2&deptid={$department.id}"><i
												class="fa fa-envelope" aria-hidden="true"></i>
											<span>{$department.name}</span></a></li>
										{/if}
										{/foreach}
										{else}
										<li class="no-department-home">
											<img
												src="{$WEB_ROOT}/templates/{$template}/images/home-department-icon.svg">
											<p>{$LANG.nosupportdepartments}</p>
										</li>
										{/if}
								</ul>
							</div>
							{if !empty($support_departmentlist)}
							<div class="panel-footer">
								<a href="{$WEB_ROOT}/submitticket.php"
									class="panel-footer-btn"><span>{$LANG.navopenticket}</span> <i
										class="fa fa-plus-circle" aria-hidden="true"></i>
								</a>
							</div>
							{/if}
						</div>
					</div>
				</div>
			</div>
			<div class="col-12 col-sm-12 col-md-6 col-lg-3 coman-box">
				<div class="box-100">
					<div class="panel panel-default panel-b-height panel-message-client">
						<div class="panel-heading">
							<h3>{$LANG.messageCenter}</h3>
						</div>
						<div class="panel-body">
							<div
								class="panel-content message-panel offerings-panel {if count($clientAlerts) == 0}no-notify-center{/if}">
								<ul>
									{foreach $clientAlerts as $alert}
									<li>
										<span><i class="fa fa-exclamation-triangle"></i></span>
										<a href="{$alert->getLink()}">
											<p>{$alert->getMessage()}</p>
										</a>
									</li>
									{foreachelse}
									<li class="no-msg-home">
										<img src="{$WEB_ROOT}/templates/{$template}/images/home-msg-icon.svg">
										<p>{$LANG.notificationsnone}</p>
									</li>
									{/foreach}
								</ul>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-12 col-sm-12 col-md-6 col-lg-6 wd-65 coman-box">
				<div
					class="panel panel-default panel-b-height panel-news-client {if empty($announcements)}no-announcement{/if}">
					<div class="panel-heading">
						<h3>{$LANG.news}</h3>
					</div>
					<div class="panel-body">
						<div class="panel-content recent-tickets-panel offerings-panel">
							<div class="table-responsive">
								<table class="tickets-table table">
									<tbody>
										{foreach $announcements as $announcement}
										<tr
											onclick="window.location = '{routePath('announcement-view', $announcement.id, $announcement.urlfriendlytitle)}'">
											<td class="ticket-no" width="70%"><a
													href="{routePath('announcement-view', $announcement.id, $announcement.urlfriendlytitle)}">
													<p>{$announcement.title}</p>
												</a></td>
											<td>
												{assign var="someVar" value="("|explode:$announcement.date}
												{assign var="someVarOne" value=$someVar[0]|replace:'/':'-'}
												{assign var="timestampone" value=strtotime($someVarOne)}
												{date('F d, Y', $timestampone)}
											</td>
										</tr>
										{foreachelse}
										<tr class="no-news-home">
											<td class="ticket-no"></td>
											<td>
												<img src="{$WEB_ROOT}/templates/{$template}/images/home-news-icon.svg">
												{$LANG.announcementsnone}
											</td>
											<td></td>
										</tr>
										{/foreach}
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-12 col-sm-12 col-md-6 col-lg-6 wd-65 coman-box">
				<div class="panel panel-default panel-b-height panel-knowledgebase-client {if empty($knowledgebaseArticles)}no-knowledgebase{/if}">
					<div class="panel-heading">
						<h3>{$LANG.knowledgebasetitle}</h3>
						{if !empty($knowledgebaseArticles)}
						{if !empty($friendlyUrlAppend)}
						<a href="{$WEB_ROOT}/{$friendlyUrlAppend}/knowledgebase">{$LANG.viewAll} </a>
						{else}
						<a href="{$WEB_ROOT}/knowledgebase">{$LANG.viewAll} </a>
						{/if}
						{/if}
					</div>
					<div class="panel-body">
						<div class="panel-content recent-tickets-panel offerings-panel">
							<div class="table-responsive">
								<table class="tickets-table table">
									<tbody>
										{if !empty($knowledgebaseArticles)}
										{foreach from=$knowledgebaseArticles key=num item=articles}
										{if !empty($friendlyUrlAppend)}
										<tr
											onclick="window.location = '{$WEB_ROOT}/{$friendlyUrlAppend}/knowledgebase/{$articles.titleUrl}'">
											{else}
										<tr
											onclick="window.location = '{$WEB_ROOT}/knowledgebase/{$articles.titleUrl}'">
											{/if}
											<td class="ticket-no" width="30%">{$articles.title}</td>
											<td {if $articles.readMoreStatus neq "true"}width="70%"{else}width="50%"{/if}>
												{$articles.full_desc}
											</td>
										{if $articles.readMoreStatus == "true"}
											<td width="20%">
												{if !empty($friendlyUrlAppend)}
														<a class="read-more"
															href="{$WEB_ROOT}/{$friendlyUrlAppend}/knowledgebase/{$articles.titleUrl}">{$LANG.readmore}
														</a>
												{else}
												<a class="read-more"
													href="{$WEB_ROOT}/knowledgebase/{$articles.titleUrl}">{$LANG.readmore}
												</a>
												{/if}
											</td>
										{/if}

										</tr>
										{/foreach}
										{else}
										<tr class="no-article-home">
											<td class="ticket-no"></td>
											<td>
												<img
													src="{$WEB_ROOT}/templates/{$template}/images/home-knowledgebase-icon.svg">
												{$LANG.knowledgebasenoarticles}
											</td>
											<td></td>
										</tr>
										{/if}
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>latestDashBoardPromoFeature();</script>