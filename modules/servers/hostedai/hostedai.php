<?php

use WHMCS\Module\Server\HosteDai\Helper;

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

define('HOSTEDAI_MODULE_VERSION', '2.2.0');

function hostedai_MetaData()
{
    return array(
        'DisplayName' => 'hosted·ai',
        'APIVersion' => '1.0',
        'RequiresServer' => true,
        'Version' => HOSTEDAI_MODULE_VERSION,
    );
}


function hostedai_ConfigOptions(array $params)
{   

    global $whmcs;
    
    // Get the product ID
    $pid = $whmcs->get_req_var("id");
    
    // Try to get server information based on the product's server group
    $serverParams = [];
    
    // Debug logging removed - functionality working correctly
    
    if ($pid) {
        try {
            // Get the product details
            $product = Capsule::table('tblproducts')->where('id', $pid)->first();
            
            if ($product) {
                $productServerGroup = $product->servergroup ?: 0;
                
                // Also check if servergroup is being passed in the request
                $requestServerGroup = 0;
                if (isset($_GET['servergroup']) && $_GET['servergroup']) {
                    $requestServerGroup = $_GET['servergroup'];
                }
                
                $serverGroupToUse = $requestServerGroup ?: $productServerGroup;
                
                if ($serverGroupToUse > 0) {
                    // In WHMCS, server groups are managed through tblservergroupsrel table
                    // We need to join tblservers with tblservergroupsrel to find servers in a group
                    // Get enabled servers from the assigned server group
                    $server = Capsule::table('tblservers')
                        ->join('tblservergroupsrel', 'tblservers.id', '=', 'tblservergroupsrel.serverid')
                        ->where('tblservergroupsrel.groupid', $serverGroupToUse)
                        ->where('tblservers.type', 'hostedai')
                        ->where('tblservers.disabled', 0)
                        ->select('tblservers.*')
                        ->first();
                    
                    if ($server) {
                        $serverParams = [
                            'serverhostname' => $server->hostname,
                            'serverpassword' => decrypt($server->password)
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Log error but continue with default behavior
            logActivity('hostedai_ConfigOptions error', $e->getMessage());
        }
    }
    
    $helper = new Helper($serverParams);

    /** Get the API to fetch the pricing policy items */
    $pricingOptions = ['Select Option'];
    try {
        $getPricingPolicy = $helper->getPolicyItems('pricing-policy');
        if (isset($getPricingPolicy['result']) && is_array($getPricingPolicy['result'])) {
            foreach ($getPricingPolicy['result'] as $value) {
                if (isset($value->policy->id)) {
                    $pricingOptions[$value->policy->id] = $value->policy->name;
                }
            }
        }
    } catch (Exception $e) {
        logActivity('hostedai: Failed to load pricing policies: ' . $e->getMessage());
    }

    /** Get the API to fetch the resource policy items */
    $resourceOptions = ['Select Option'];
    try {
        $getResourcePolicy = $helper->getPolicyItems('resource-policy');
        if (isset($getResourcePolicy['result']) && is_array($getResourcePolicy['result'])) {
            foreach ($getResourcePolicy['result'] as $value) {
                $resourceOptions[$value->id] = $value->name;
            }
        }
    } catch (Exception $e) {
        logActivity('hostedai: Failed to load resource policies: ' . $e->getMessage());
    }

    /** Get the API to fetch the Service policy items */
    $serviceOptions = ['Select Option'];
    try {
        $getServicePolicy = $helper->getPolicyItems('policy/service');
        if (isset($getServicePolicy['result']) && is_array($getServicePolicy['result'])) {
            foreach ($getServicePolicy['result'] as $policy) {
                $serviceOptions[$policy->id] = $policy->name;
            }
        }
    } catch (Exception $e) {
        logActivity('hostedai: Failed to load service policies: ' . $e->getMessage());
    }

    /** Get the API to fetch the Instance policy items */
    $instanceOptions = ['Select Option'];
    try {
        $getInstancePolicy = $helper->getPolicyItems('policy/instance-type');
        if (isset($getInstancePolicy['result']) && is_array($getInstancePolicy['result'])) {
            foreach ($getInstancePolicy['result'] as $policy) {
                $instanceOptions[$policy->id] = $policy->name;
            }
        }
    } catch (Exception $e) {
        logActivity('hostedai: Failed to load instance type policies: ' . $e->getMessage());
    }

    /** Get the API to fetch the Image policy items */
    $imageOptions = ['Select Option'];
    try {
        $getImagePolicy = $helper->getPolicyItems('policy/image');
        if (isset($getImagePolicy['result']) && is_array($getImagePolicy['result'])) {
            foreach ($getImagePolicy['result'] as $policy) {
                $imageOptions[$policy->id] = $policy->name;
            }
        }
    } catch (Exception $e) {
        logActivity('hostedai: Failed to load image policies: ' . $e->getMessage());
    }

    /** Get all available GPU pools for the GPU Pool dropdown (configoption10) */
    $gpuPoolOptions = ['' => '-- No GPU Pool --'];
    try {
        $getPoolsResponse = $helper->getAllPools();
        if ($getPoolsResponse['httpcode'] == 200 && is_array($getPoolsResponse['result'])) {
            foreach ($getPoolsResponse['result'] as $pool) {
                if (isset($pool->pool_id)) {
                    $label = isset($pool->pool_label) ? $pool->pool_label : '';
                    $name  = isset($pool->pool_name)  ? $pool->pool_name  : '';
                    $gpuPoolOptions[$pool->pool_id] = $label . ($label && $name ? ' — ' : '') . $name;
                }
            }
        }
    } catch (Exception $e) {
        logActivity('hostedai: Failed to load GPU pools: ' . $e->getMessage());
    }

    // Role is no longer configurable — first user is always Team Admin

     /** create the custom fields */
    $customfieldarray = [
        'team_id' =>
        [
            'type' => 'product',
            'fieldname' => 'team_id|Team Id',
            'relid' => $pid,
            'fieldtype' => 'text',
            'description' => '',
            'adminonly' => 'on',
            'sortorder' => '1',
        ],
    ];
    $helper->createHostedaiCustomFields($customfieldarray);
    $helper->ensureClientTeamIdField();

    return array(

        'pricing_policy' => array(
            'FriendlyName' => 'Pricing Policy',
            'Type' => 'dropdown',
            'Size' => '25',
            'Options' => $pricingOptions,
            'Description' => '',
        ),
        'resource_policy' => array(
            'FriendlyName' => 'Resources Policy',
            'Type' => 'dropdown',
            'Size' => '25',
            'Options' => $resourceOptions,
            'Description' => '',
        ),
        'service_policy' => array(
            'FriendlyName' => 'Service Policy',
            'Type' => 'dropdown',
            'Size' => '25',
            'Options' => $serviceOptions,
            'Description' => '',
        ),
        'instance_type_policy' => array(
            'FriendlyName' => 'Instance Type Policy',
            'Type' => 'dropdown',
            'Size' => '25',
            'Options' => $instanceOptions,
            'Description' => '',
        ),
        'image_policy' => array(
            'FriendlyName' => 'Image Policy',
            'Type' => 'dropdown',
            'Size' => '25',
            'Options' => $imageOptions,
            'Description' => '',
        ),
        'color' => array(
            'FriendlyName' => 'Color',
            'Type' => 'dropdown',
            'Size' => '25',
            'Options' => array(
                '#414141' => 'black',
                '#305EFB' => 'blue',
                '#104822' => 'green',
                '#FF5738' => 'orange',
                '#FFC352' => 'yellow',
            ),
            'Description' => '',
        ),
        'loginUrl' => array(
            'FriendlyName' => 'Login URL',
            'Type' => 'text',
            'Size' => '255',
        ),
        'suspentionDays' => array(
            'FriendlyName' => 'No. of Suspension Days',
            'Type' => 'text',
            'Size' => '25',
        ),
        'termminationDays' => array(
            'FriendlyName' => 'No. of Termination Days',
            'Type' => 'text',
            'Size' => '25',
        ),

        // configoption10 — GPU pool to subscribe on provisioning
        'gpu_pool_id' => array(
            'FriendlyName' => 'GPU Pool',
            'Type' => 'dropdown',
            'Size' => '25',
            'Options' => $gpuPoolOptions,
            'Description' => 'GPU pool to subscribe the team to on provisioning (leave blank for none)',
        ),

        // configoption11 — number of vGPUs (minimum 1)
        'vgpus' => array(
            'FriendlyName' => 'vGPUs',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '1',
            'Description' => 'Number of virtual GPUs requested from the pool (minimum 1)',
        ),

        // configoption12 — GPU instance type UUID (used in create-instance)
        'gpu_instance_type' => array(
            'FriendlyName' => 'Instance Type',
            'Type' => 'dropdown',
            'Size' => '25',
            'Options' => array(
                '54217b3f-fb3a-4707-869e-fa873b4ba997' => 'Micro',
                'a80d70c3-fa17-4c54-b055-f52539792a59' => 'Small',
                'd3bb7f05-00be-4065-bf45-904574f5c4f4' => 'Medium',
                'd2bc9239-32b2-4e6b-92a2-1e59b82f67c2' => 'Large',
                '2672a86e-7b43-48ec-b97e-f2a24884a8dc' => 'X-Large',
                '92ce9204-3170-42e9-903e-fb0e7b6af88b' => '2X-Large',
                'ac2e543c-4ac7-4bf0-ace9-39e6ff57a8d9' => '4X-Large',
            ),
            'Description' => 'GPU instance type for instance creation',
        ),

        // configoption13 — root storage block UUID (used in create-instance as root_storage_type_id)
        'gpu_storage_block' => array(
            'FriendlyName' => 'Storage Block',
            'Type' => 'dropdown',
            'Size' => '25',
            'Options' => array(
                '1a24b372-62b7-44d9-9d6f-ac64e42ca77b' => 'Block-10',
                '3bf7f9a2-3673-423f-af2f-7f86adf90ce9' => 'Block-20',
                '008705ba-ce6a-44ef-a3a9-5e92fe73e56f' => 'Block-50',
                'b8709e20-2b91-4e9d-8ba9-bd08cab06af4' => 'Block-100',
                '5bb6c97c-e403-4361-87af-37b85ef5bcbd' => 'Block-200',
                'dabb27d8-0283-436d-a866-5a2b3bc664b6' => 'Block-300',
                '8dd7e0cc-00ee-4a53-a0aa-86672be8dc56' => 'Block-500',
                'c5c74b4d-38cf-439d-a65e-dab4aeeec31b' => 'Block-750',
                '5b6cc408-aab3-453a-a93d-d45db4f0430e' => 'Block-1000',
                'e9f98a2e-e514-4167-8b6a-c47bcd241396' => 'Block-1500',
                'b9cad3af-2506-4354-b676-0a3361d26293' => 'Block-2000',
            ),
            'Description' => 'Ephemeral storage block for instance creation',
        ),

        // configoption14 — Service ID (the hostedai service/workspace service UUID)
        'gpu_service_id' => array(
            'FriendlyName' => 'Service ID',
            'Type' => 'text',
            'Size' => '64',
            'Default' => '6d578de2-f4a5-44d5-b9be-49d35f2ed7ff',
            'Description' => 'hostedai service UUID used in instance creation',
        ),

        // configoption15 — Image hash for the instance OS image
        'gpu_image_hash' => array(
            'FriendlyName' => 'Image Hash',
            'Type' => 'text',
            'Size' => '128',
            'Default' => '37191266f9cad5651a92a4b56d8a03bb072bb27d768663e0fc9b6c7ecdfa0a11',
            'Description' => 'OS image hash used in instance creation',
        ),

        // configoption16 — Region ID (integer)
        'gpu_region_id' => array(
            'FriendlyName' => 'Region ID',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '5',
            'Description' => 'Region integer ID for instance creation',
        ),

        // configoption17 — Workspace ID (UUID) — fallback only
        'gpu_workspace_id' => array(
            'FriendlyName' => 'Workspace ID',
            'Type' => 'text',
            'Size' => '64',
            'Default' => '',
            'Description' => 'Fallback workspace ID if auto-detection fails. Leave empty to always auto-detect from team.',
        ),

        // configoption18 — Recipe name -> service_id map, driven by the
        // "GPU Environment" WHMCS Configurable Option chosen at order time.
        'recipe_map' => array(
            'FriendlyName' => 'Recipe to Service ID Map',
            'Type' => 'textarea',
            'Rows' => '5',
            'Cols' => '50',
            'Default' => '{"No Recipe":"6d0483a8-8aa2-4b21-ac27-56032c5f4a16","vLLM TinyLlama":"6d0483a8-8aa2-4b21-ac27-56032c5f4a16","Ollama":"6d0483a8-8aa2-4b21-ac27-56032c5f4a16"}',
            'Description' => 'JSON map of recipe names (as they appear in the "GPU Environment" Configurable Option) to service UUIDs.',
        ),

        // configoption19 — usage-based billing: extra flat $/hour platform fee.
        // Retired by default now that vendor cost (Hosted AI Pricing Policy,
        // already includes our margin) is billed directly — only set this
        // above 0 if you want an additional flat WHMCS-side fee on top.
        'usage_base_rate' => array(
            'FriendlyName' => 'Base Hourly Rate',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '0',
            'Description' => 'Extra flat USD/hour on top of Hosted AI\'s own vendor cost. Leave at 0 unless you want a separate flat platform fee — the Hosted AI Pricing Policy already carries your margin. Used by hostedai_usage_cron.php.',
        ),

        // configoption20 — usage-based billing: recipe name -> extra $/hour
        'usage_recipe_rate_map' => array(
            'FriendlyName' => 'Recipe Hourly Rate Map',
            'Type' => 'textarea',
            'Rows' => '5',
            'Cols' => '50',
            'Default' => '{"No Recipe":0,"vLLM TinyLlama":0.15,"Ollama":0}',
            'Description' => 'JSON map of recipe name -> extra USD/hour added on top of the Base Hourly Rate.',
        ),

        // configoption21 — Shared Storage option name -> storage_block_id map.
        // Driven by a "Shared Storage" WHMCS Configurable Option chosen at
        // order time (create this Configurable Option Group in WHMCS admin,
        // same as "GPU Environment" for recipes). Map key "None" (or the
        // option being empty) means no shared volume is provisioned.
        'shared_storage_block_map' => array(
            'FriendlyName' => 'Shared Storage Block Map',
            'Type' => 'textarea',
            'Rows' => '5',
            'Cols' => '50',
            'Default' => '{"None":"","100GB Shared":"b8709e20-2b91-4e9d-8ba9-bd08cab06af4","300GB Shared":"dabb27d8-0283-436d-a866-5a2b3bc664b6"}',
            'Description' => 'JSON map of "Shared Storage" Configurable Option names to shared-storage-capable storage_block_id UUIDs.',
        ),

        // configoption22 — Billing Mode: which products the usage cron is
        // allowed to deduct from client credit for. Flat-priced monthly
        // subscription products are billed entirely through WHMCS's normal
        // recurring invoice + overdue-suspension automation (already wired
        // via hostedai_SuspendAccount/TerminateAccount) — the usage cron
        // must never ALSO deduct credit for them, or clients get double-billed.
        'billing_mode' => array(
            'FriendlyName' => 'Billing Mode',
            'Type' => 'dropdown',
            'Size' => '25',
            'Options' => array(
                'usage'        => 'Usage-Based (deduct from credit)',
                'subscription' => 'Fixed Subscription (invoice only, no credit deduction)',
            ),
            'Default' => 'usage',
            'Description' => 'Usage-Based: hostedai_usage_cron.php deducts vendor cost + recipe surcharge from client credit. Fixed Subscription: billed only via the product\'s recurring invoice — the cron tracks hours for visibility but never touches credit, and suspension/termination is handled by WHMCS\'s normal overdue-invoice automation instead.',
        ),

        // configoption23 — Fixed Subscription billing: recipe name -> percent
        // surcharge on top of this product's recurring amount. Only used
        // when Billing Mode (configoption22) is "subscription" — the
        // usage-based equivalent is configoption20 (a $/hour map instead).
        // Applied by includes/hooks/hostedai_recipe_surcharge.php at invoice
        // time, computed fresh from the product's *current* recurring
        // amount each time, so it stays correct if the base price changes.
        'usage_recipe_percent_map' => array(
            'FriendlyName' => 'Recipe Percent Map',
            'Type' => 'textarea',
            'Rows' => '5',
            'Cols' => '50',
            'Default' => '{"No Recipe":0,"vLLM TinyLlama":10,"Ollama":0}',
            'Description' => 'JSON map of recipe name (as it appears in the "GPU Environment" Configurable Option) -> percent surcharge added to this product\'s recurring amount. Fixed Subscription products only.',
        ),

        // configoption24 — Client-Choice Resource Maps. Only needed on
        // products where the client picks their own Accelerator / Instance
        // Flavor / Storage Size as priced Configurable Options (create
        // those option groups in WHMCS admin first, named exactly
        // "GPU Accelerator", "Instance Flavor", "Storage Size"). Leave
        // blank on products where the admin fixes these above instead.
        'client_choice_maps' => array(
            'FriendlyName' => 'Client-Choice Resource Maps',
            'Type' => 'textarea',
            'Rows' => '8',
            'Cols' => '50',
            'Default' => '',
            'Description' => 'JSON: {"accelerator_map":{"<GPU Accelerator option name>":{"pool_id":8,"region_id":6}},"flavor_map":{"<Instance Flavor option name>":"<instance_type_id UUID>"},"storage_map":{"<Storage Size option name>":"<storage_block_id UUID>"}}. Only used when the matching Configurable Option is assigned to this product — leave blank for admin-fixed products.',
        ),

    );

}


/**
 * Test connection with the given server parameters.
 *
 * Allows an admin user to verify that an API connection can be
 * successfully made with the given configuration parameters for a
 * server.
 *
 * When defined in a module, a Test Connection button will appear
 * alongside the Server Type dropdown when adding or editing an
 * existing server.
 */
function hostedai_TestConnection(array $params)
{
    
    try {

        $helper = new Helper($params);
        $getPricingPolicy = $helper->getPolicyItems('pricing-policy');
        if($getPricingPolicy['httpcode'] == 200){
         
            $success = true;
        }
        else{
            $errorMsg = $getPricingPolicy['result']->message;
        }
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'openprovider_plesk_license',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        $success = false;
        $errorMsg = $e->getMessage();
    }

    return array(
        'success' => $success,
        'error' => $errorMsg,
    );
}

function hostedai_CreateAccount(array $params)
{
    try {
        global $whmcs;
        $helper = new Helper($params);

        $serviceId = $params['serviceid'];
        $pid = $params['pid'];
        $clientId = $params['userid'];

        $pricingPolicyID = $params['configoption1'];
        $resourcePolicyID =  $params['configoption2'];
        $servicePolicyID =  $params['configoption3'];
        $instancePolicyID =  $params['configoption4'];
        $imagePolicyID =  $params['configoption5'];
        $color =  $params['configoption6'];

        $email =  $params['clientsdetails']['email'];
        $name =  $params['clientsdetails']['fullname'];

        $workspaceId = '';

        // ── TEAM LOOKUP / REUSE (one Hosted AI team per WHMCS client) ───────
        $teamId = $helper->getClientTeamId($clientId);

        if (empty($teamId)) {
            // No team yet for this client — create one.

            // First user is always Team Admin — find admin role ID from API
            $roleID = '';
            $getRoles = $helper->getPolicyItems('roles');
            if (isset($getRoles['result']->roles)) {
                foreach ($getRoles['result']->roles as $role) {
                    if ($role->name === 'team_admin') {
                        $roleID = $role->id;
                        break;
                    }
                }
            }

            $teamName = 'client-' . $clientId . '-'
                . preg_replace('/[^a-z0-9]/i', '', strtolower($name ?? ''));

            // Per POST /team docs, the 5 policy IDs must be bundled inside a
            // "general" (or "baremetal") wrapper object, with has_general_policies
            // / has_baremetal_policies booleans alongside — sending them as flat
            // top-level fields only (the old shape below) is accepted by the API
            // but never actually satisfies the has_general_policies check, which
            // is why team creation kept failing with "invalid policy id".
            $policyBundle = [
                'image_policy_id'         => $imagePolicyID ?? '',
                'instance_type_policy_id' => $instancePolicyID ?? '',
                'pricing_policy_id'       => $pricingPolicyID ?? '',
                'resource_policy_id'      => $resourcePolicyID ?? '',
                'service_policy_id'       => $servicePolicyID ?? '',
            ];

            $postData = [
                'color' => $color ?? '#414141',
                'description' => '',
                'general' => $policyBundle,
                'has_general_policies' => true,
                'has_baremetal_policies' => false,
                'image_policy_id' => $imagePolicyID ?? '',
                'instance_type_policy_id' => $instancePolicyID ?? '',
                'members' => [
                    [
                        'email' => $email ?? '',
                        'name' => $name ?? '',
                        'role' => $roleID,
                        'pre_onboard' => true,
                    ]
                ],
                'name' => $teamName,
                'pricing_policy_id' => $pricingPolicyID ?? '',
                'resource_policy_id' => $resourcePolicyID ?? '',
                'service_policy_id' => $servicePolicyID ?? '',
            ];

            $getResponse = $helper->createHostedaiTeam($postData);

            if ($getResponse['httpcode'] == 200) {

                if (isset($getResponse['result']->id)) {
                    $teamId = $getResponse['result']->id;

                    // Extract workspace from team creation response
                    if (!empty($getResponse['result']->workspaces)
                        && is_array($getResponse['result']->workspaces)) {
                        $workspaceId = $getResponse['result']->workspaces[0]->id ?? '';
                    }
                    logActivity('hostedai: workspace_id from team response: ' . $workspaceId);

                    // Team is stored at CLIENT level now, not per-service.
                    $helper->saveClientTeamId($clientId, $teamId);

                    $billingCycle = $params['model']->billingcycle;
                    if ($billingCycle === 'One Time') {
                        $helper->insert_teamDetail($clientId, $serviceId, $pid, $teamId, 'insert');
                    }

                    logActivity('hostedai: Created new team ' . $teamId . ' for client ' . $clientId);
                }

            } else {
                return $getResponse['result']->message;
            }
        } else {
            logActivity('hostedai: Reusing existing team ' . $teamId . ' for client ' . $clientId);

            // Existing team — look up its workspace via the API.
            $teamDetailResponse = $helper->getTeamDetail($teamId);

            logActivity('hostedai DEBUG getTeamDetail HTTP: ' . $teamDetailResponse['httpcode']);
            logActivity('hostedai DEBUG getTeamDetail result: '
                . substr(json_encode($teamDetailResponse['result']), 0, 500));

            if ($teamDetailResponse && $teamDetailResponse['httpcode'] == 200) {
                // Response is wrapped in "team" key
                $teamObj = $teamDetailResponse['result']->team
                    ?? $teamDetailResponse['result'];
                if (!empty($teamObj->workspaces)) {
                    $workspaceId = $teamObj->workspaces[0]->id ?? '';
                }
            }

            logActivity('hostedai: workspace_id for reused team: ' . $workspaceId);
            if (empty($workspaceId)) {
                logActivity('hostedai: WARNING - no workspace found for team ' . $teamId);
            }
        }
        // ── END TEAM LOOKUP / REUSE ──────────────────────────────────────────

        // ── INSTANCE CREATION ────────────────────────────────────────────────
        // configoption10 = gpu_pool_id  configoption11 = vgpus
        // configoption12 = instance_type_id (UUID)  configoption13 = storage_block_id (UUID)
        // configoption14 = service_id (fallback)     configoption15 = image_hash
        // configoption16 = region_id                 configoption17 = workspace_id (fallback)
        // configoption18 = recipe_map (JSON: recipe name -> service_id)
        $gpuPoolId      = (int)($params['configoption10'] ?? 0);
        $vgpus          = max(1, (int)($params['configoption11'] ?? 1));
        $instanceTypeId = $params['configoption12'] ?? '';
        $storageBlockId = $params['configoption13'] ?? '';
        $imageHash      = $params['configoption15'] ?? '37191266f9cad5651a92a4b56d8a03bb072bb27d768663e0fc9b6c7ecdfa0a11';
        $regionId       = (int)($params['configoption16'] ?? 5);

        // Fallback to configoption17 if workspace was not extracted from team lookup
        if (empty($workspaceId)) {
            $workspaceId = $params['configoption17'] ?? '';
        }

        // ── CLIENT-CHOICE RESOURCE OVERRIDE (configoption24) ──────────────────
        // Some products let the client pick Accelerator/Instance Flavor/Storage
        // themselves at checkout — each a priced WHMCS Configurable Option —
        // instead of the admin fixing them above in Module Settings. When the
        // matching option was selected and resolves in the map, it overrides
        // the fixed value above; otherwise the admin-fixed value stands
        // unchanged. Existing admin-fixed products simply don't have these
        // option groups assigned, so this is a no-op for them.
        $clientChoiceMapJson = $params['configoption24'] ?? '';
        $clientChoiceMap = [];
        if (!empty(trim($clientChoiceMapJson))) {
            // WHMCS's admin textarea HTML-encodes this field on save (e.g.
            // `"` becomes the literal text `&quot;`) and hands that encoded
            // form straight through to the module without decoding it back
            // — confirmed via hex dump, not a smart-quotes issue. Decode
            // entities first, then also normalize typographic quotes/nbsp
            // in case of a copy-paste artifact on top of that, so a data
            // round-trip through the admin UI can't silently break every
            // client-choice lookup below.
            $normalized = html_entity_decode($clientChoiceMapJson, ENT_QUOTES | ENT_HTML5);
            $normalized = strtr($normalized, [
                "\xE2\x80\x9C" => '"', // “
                "\xE2\x80\x9D" => '"', // ”
                "\xE2\x80\x98" => "'", // ‘
                "\xE2\x80\x99" => "'", // ’
                "\xC2\xA0"     => ' ', // non-breaking space
            ]);
            $clientChoiceMap = json_decode($normalized, true) ?? [];
        }

        // trim() guards against stray leading/trailing whitespace in the
        // Configurable Option's saved name (a WHMCS data-entry quirk that
        // silently breaks an exact-match map lookup otherwise).
        $selectedAccelerator = trim($params['configoptions']['GPU Accelerator'] ?? '');
        if ($selectedAccelerator !== '' && isset($clientChoiceMap['accelerator_map'][$selectedAccelerator])) {
            $acceleratorChoice = $clientChoiceMap['accelerator_map'][$selectedAccelerator];
            $gpuPoolId = (int) ($acceleratorChoice['pool_id'] ?? $gpuPoolId);
            $regionId  = (int) ($acceleratorChoice['region_id'] ?? $regionId);
        }

        $selectedFlavor = trim($params['configoptions']['Instance Flavor'] ?? '');
        if ($selectedFlavor !== '' && isset($clientChoiceMap['flavor_map'][$selectedFlavor])) {
            $instanceTypeId = $clientChoiceMap['flavor_map'][$selectedFlavor];
        }

        $selectedStorageSize = trim($params['configoptions']['Storage Size'] ?? '');
        if ($selectedStorageSize !== '' && isset($clientChoiceMap['storage_map'][$selectedStorageSize])) {
            $storageBlockId = $clientChoiceMap['storage_map'][$selectedStorageSize];
        }

        if ($gpuPoolId <= 0) {
            logActivity("hostedai CreateAccount warning: no valid GPU pool resolved for service {$serviceId} (accelerator='{$selectedAccelerator}') — instance creation skipped. Check the Configurable Option name matches configoption24 exactly, or that GPU Pool is set in Module Settings as a fallback."
                . " DEBUG raw configoption24=" . var_export($clientChoiceMapJson, true)
                . " jsonDecodeError=" . json_last_error_msg()
                . " parsedMapKeys=" . json_encode(array_keys($clientChoiceMap))
                . " acceleratorMapKeys=" . json_encode(array_keys($clientChoiceMap['accelerator_map'] ?? []))
                . " rawHex=" . bin2hex($clientChoiceMapJson));
        }
        // ── END CLIENT-CHOICE RESOURCE OVERRIDE ────────────────────────────────

        // ── RECIPE SELECTION (WHMCS Configurable Option → service_id) ────────
        // Hardcoded recipe map (can be overridden by configoption18)
        $defaultRecipeMap = [
            'No Recipe'      => '6d0483a8-8aa2-4b21-ac27-56032c5f4a16',
            'vLLM TinyLlama' => '6d0483a8-8aa2-4b21-ac27-56032c5f4a16',
            'Ollama'         => '6d0483a8-8aa2-4b21-ac27-56032c5f4a16',
        ];

        // Try configoption18 override first
        $recipeMapJson = $params['configoption18'] ?? '';
        $recipeMap = [];
        if (!empty(trim($recipeMapJson))) {
            $recipeMap = json_decode($recipeMapJson, true) ?? [];
        }
        // Fall back to hardcoded map if configoption empty or invalid
        if (empty($recipeMap)) {
            $recipeMap = $defaultRecipeMap;
        }

        $selectedRecipe = $params['configoptions']['GPU Environment']
            ?? 'No Recipe';

        $apiServiceId = $recipeMap[$selectedRecipe]
            ?? $recipeMap['No Recipe']
            ?? '6d0483a8-8aa2-4b21-ac27-56032c5f4a16';

        logActivity('hostedai: Recipe selected: ' . $selectedRecipe . ' -> service_id: ' . $apiServiceId);
        // ── END RECIPE SELECTION ─────────────────────────────────────────────

        $instanceName   = 'whmcs-' . $serviceId;

        if (!empty($teamId) && $gpuPoolId > 0) {
            logActivity('hostedai: Using resource_policy: '
                . ($params['configoption2'] ?? 'not set')
                . ' region_id: ' . $regionId);

            $instanceResponse = $helper->createInstance(
                $teamId,
                $apiServiceId,
                $regionId,
                $gpuPoolId,
                $vgpus,
                $imageHash,
                $instanceTypeId,
                $storageBlockId,
                $workspaceId,
                $instanceName
            );

            if (!empty($instanceResponse['success']) && $instanceResponse['success'] === true) {
                $instanceId = $instanceResponse['result'];
                $helper->savePoolSubscriptionId($serviceId, $instanceId);
                $helper->saveRecipeNote($serviceId, $selectedRecipe);

                // Surface the instance ID in the service's domain field so it's
                // visible/searchable in the client and admin service lists.
                Capsule::table('tblhosting')
                    ->where('id', $serviceId)
                    ->update(['domain' => $instanceId]);

                logActivity('hostedai: Instance created successfully: ' . $instanceId);

                // ── SHARED VOLUME (optional, client-selected at order time) ──
                $sharedStorageMapJson = $params['configoption21'] ?? '';
                $sharedStorageMap = [];
                if (!empty(trim($sharedStorageMapJson))) {
                    $sharedStorageMap = json_decode($sharedStorageMapJson, true) ?? [];
                }

                $selectedSharedStorage = $params['configoptions']['Shared Storage'] ?? 'None';
                $sharedStorageBlockId  = $sharedStorageMap[$selectedSharedStorage] ?? '';

                if (!empty($sharedStorageBlockId) && !empty($teamId)) {
                    $volumeResponse = $helper->createSharedVolume(
                        'whmcs-' . $serviceId . '-shared',
                        $regionId,
                        $sharedStorageBlockId,
                        $teamId
                    );

                    if (!empty($volumeResponse['success'])) {
                        $helper->saveSharedVolumeId($serviceId, $volumeResponse['result']->id);
                        logActivity('hostedai: Shared volume created successfully: ' . $volumeResponse['result']->id);
                    } else {
                        $volErrorMsg = isset($volumeResponse['result']->message)
                            ? $volumeResponse['result']->message
                            : 'Shared volume creation failed (HTTP ' . ($volumeResponse['httpcode'] ?? '?') . ')';
                        logActivity('hostedai CreateAccount warning: ' . $volErrorMsg);
                        // Instance was created; shared volume failure is non-fatal — admin can retry manually.
                    }
                }
                // ── END SHARED VOLUME ────────────────────────────────────────
            } else {
                $errorMsg = isset($instanceResponse['result']->message)
                    ? $instanceResponse['result']->message
                    : 'Instance creation failed (HTTP ' . ($instanceResponse['httpcode'] ?? '?') . ')';
                logActivity('hostedai CreateAccount warning: ' . $errorMsg);
                // Team was created; instance failure is non-fatal — admin can retry manually.
            }
        }
        // ── END INSTANCE CREATION ────────────────────────────────────────────

    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'hostedai',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}


/**
 * Suspend an instance of a product/service.
 *
 * Called when a suspension is requested. This is invoked automatically by WHMCS
 * when a product becomes overdue on payment or can be called manually by admin
 * user.
 */
function hostedai_SuspendAccount(array $params)
{
    try {
        $helper = new Helper($params);
        $serviceId = $params['serviceid'];

        // ── INSTANCE SUSPENSION ──────────────────────────────────────────────
        // Suspending a service only stops that service's instance. The team is
        // shared across the client's other services (Change 1) and lives as
        // long as the client exists in WHMCS — it is never suspended here.
        $instanceId = $helper->getPoolSubscriptionIdFromNotes($serviceId);
        if (!empty($instanceId)) {
            $helper->stopInstance($instanceId);
            logActivity('hostedai: Suspended instance ' . $instanceId . ' for service ' . $serviceId);
        }
        // ── END INSTANCE SUSPENSION ───────────────────────────────────────────

        return 'success';

    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'hostedai',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}


/**
 * Un-suspend instance of a product/service.
 *
 * Called when an un-suspension is requested. This is invoked
 * automatically upon payment of an overdue invoice for a product, or
 * can be called manually by admin user.
 */
function hostedai_UnsuspendAccount(array $params)
{
    try {

        $helper = new Helper($params);
        $serviceId = $params['serviceid'];
        $teamId = $helper->resolveTeamId($params);

        // Unsuspend the team first, in case it was suspended when all of the
        // client's services were previously suspended together.
        if (!empty($teamId)) {
            $helper->unsuspendHostedaiTeam($teamId);
        }

        // ── INSTANCE UNSUSPENSION ─────────────────────────────────────────────
        $instanceId = $helper->getPoolSubscriptionIdFromNotes($serviceId);
        if (!empty($instanceId)) {
            $helper->startInstance($instanceId);
            logActivity('hostedai: Unsuspended instance ' . $instanceId . ' for service ' . $serviceId);
        }
        // ── END INSTANCE UNSUSPENSION ─────────────────────────────────────────

        return 'success';

    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'hostedai',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

}


/**
 * Terminate instance of a product/service.
 *
 * Called when a termination is requested. This can be invoked automatically for
 * overdue products if enabled, or requested manually by an admin user.
 */
function hostedai_TerminateAccount(array $params)
{
    try {

        $helper = new Helper($params);
        $serviceId = $params['serviceid'];
        $pid = $params['pid'];

        // ── INSTANCE TERMINATION ────────────────────────────────────────────
        // Only this service's instance is removed here. The team is shared
        // across the client's other services/instances (Change 1) and lives
        // as long as the client exists in WHMCS — it is never deleted here.
        // Team deletion happens on client deletion (see hostedai_client_delete
        // hook), not on service termination.
        $instanceId = $helper->getPoolSubscriptionIdFromNotes($serviceId);
        if (!empty($instanceId)) {
            $deleteResponse = $helper->deleteInstance($instanceId);
            if ($deleteResponse['httpcode'] == 200 || $deleteResponse['httpcode'] == 204) {
                $helper->savePoolSubscriptionId($serviceId, '');
                logActivity('hostedai: Instance ' . $instanceId . ' deleted successfully');
            } else {
                logActivity('hostedai: Warning — could not delete instance ' . $instanceId);
            }
        }
        // ── END INSTANCE TERMINATION ─────────────────────────────────────────

        // ── SHARED VOLUME TERMINATION ─────────────────────────────────────────
        $sharedVolumeId = $helper->getSharedVolumeIdFromNotes($serviceId);
        if (!empty($sharedVolumeId)) {
            $deleteVolResponse = $helper->deleteSharedVolume($sharedVolumeId);
            if ($deleteVolResponse['httpcode'] == 200 || $deleteVolResponse['httpcode'] == 204) {
                $helper->saveSharedVolumeId($serviceId, '');
                logActivity('hostedai: Shared volume ' . $sharedVolumeId . ' deleted successfully');
            } else {
                logActivity('hostedai: Warning — could not delete shared volume ' . $sharedVolumeId);
            }
        }
        // ── END SHARED VOLUME TERMINATION ─────────────────────────────────────

        $fields = ["team_id" => ''];
        $helper->insert_hostedai_custom_fields_value($serviceId, $pid, $fields);
        $helper->delete_teamDetail($serviceId, $pid);

        return 'success';

    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'hostedai',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}


/**
 * Upgrade or downgrade an instance of a product/service.
 *
 * Called to apply any change in product assignment or parameters. It
 * is called to provision upgrade or downgrade orders, as well as being
 * able to be invoked manually by an admin user.

 */
function hostedai_ChangePackage(array $params)
{
    try {
        $helper = new Helper($params);
        $pricing_policy_id = $params['configoption1'];
        $resource_policy_id = $params['configoption2'];
        $teamId = $helper->resolveTeamId($params);

        $changePackage = $helper->changeHostedaiTeamPackage($pricing_policy_id, $resource_policy_id, $teamId); 
        if($changePackage['status'] == 'success') {
            return 'success';
        } else {
            return $changePackage['message'];
        }

    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'hostedai',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

}




/**
 * Admin services tab additional fields.
 *
 * Define additional rows and fields to be displayed in the admin area service
 * information and management page within the clients profile.

 */
function hostedai_AdminServicesTabFields(array $params)
{
    try {
        global $CONFIG;
        global $whmcs;

        $loginURL = !empty($params['configoption7']) ? $params['configoption7'] : '#';
        $helper = new Helper($params);

        $assets = $CONFIG['SystemURL'] . "/modules/servers/hostedai/assets";

        $language = $CONFIG['Language'];
        $langfilename = __DIR__ . '/lang/' . $language . '.php';
        if (file_exists($langfilename)) {
            require($langfilename);
        } else {
            require(__DIR__ . '/lang/english.php');
        }

        $teamId = $helper->resolveTeamId($params);

        // ── GPU INSTANCE ID / STATUS ─────────────────────────────────────────
        // Computed up front (independent of the team lookup below) so the
        // admin can always tell which instance a service maps to, even if
        // the team/resource-overview lookup below fails.
        $instanceId = $helper->getPoolSubscriptionIdFromNotes($params['serviceid']);
        $instanceStatus = 'No instance';
        if (!empty($instanceId)) {
            $instanceDetail = $helper->getUnifiedInstance($instanceId);
            $instanceStatus = ($instanceDetail['httpcode'] == 200)
                ? ($instanceDetail['result']->status ?? 'Unknown')
                : 'Not found on Hosted AI';
        }
        // ── END GPU INSTANCE ID / STATUS ─────────────────────────────────────

        if(!empty($teamId)) {
            $getTeamdata = $helper->getTeamDetail($teamId);

            // Get team data
            if($getTeamdata['httpcode'] == 200)
            {

                if($getTeamdata['result']->team->is_suspended == true) {
                    $is_suspend = "<span class='btn btn-danger'>Suspended</span>";
                } else {
                    $is_suspend = "<span class='btn btn-success'>Active</span>";
                }


                $getTeamMembers = $helper->getTeamMembers($teamId);
    
                if($getTeamMembers['httpcode'] == 200) {
                    
                    $members = $getTeamMembers['result']->members;
                    foreach ($members as $member) {
                        $teamEmail = $member->user->email ?? '';
                        $teamStatus = $member->status ?? '';
                        $teamRole = $member->role->label ?? '';
        
                        $teamMemberHTML .= '<tbody>
                                <tr>
                                    <td>' . $teamEmail . '</td>
                                    <td>' . ucfirst($teamStatus) . '</td>
                                    <td>' . $teamRole . '</td>
                                </tr>
                            </tbody>';
                    }
        
                    // Get resource overview
                    $getResourceOverview = $helper->getResourceOverview($teamId);
                    
                    if($getResourceOverview['httpcode'] == 200) {
                        $resourceOverviewData = $getResourceOverview['result'];
        
                        $resourceHTML = '';
            
                        foreach ($resourceOverviewData as $resourceType => $resource) {

                            $used = $resource->used;
                            $available = $resource->available;

                            if($available > 0) {
                                $percentage = ($used/$available)*100;
                            } else {
                                $percentage = 0;
                            }
            
                            if ($resourceType == 'cores') {
                                $unit = 'Cores';
                            } elseif ($resourceType == 'gpus') {
                                $unit = 'No. of cards';
                            } else {
                                $unit = 'GB';
                            }

                            if($available == -1) {
                                $aval = 'Unlimited';
                                $used = $resource->used . " " . $unit.  ' (∞)';
                            } else {
                                $aval = $available . " " . $unit;
                                $used = $resource->used . " " . $unit . " (".$percentage."%)";
                            }
                            
                            $imagePath = $CONFIG['SystemURL'] . "/modules/servers/hostedai/assets/images/".$resourceType.".svg";
            
                            $resourceHTML .= '<div class="col-lg-6 mt-2">
                                    <div class="overview-card">
                                        <div class="overview-card-header">
                                            <img src="'. $imagePath .'" alt="'. $resourceType .'">
                                            <h3>'.strtoupper($resourceType).'</h3>
                                        </div>
                                        <div class="overview-card-detail">
                                            <p>'.$used.'</p>
                                            <p>'.$aval.'</p>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" aria-valuenow="'.$percentage.'" aria-valuemin="'.$percentage.'" aria-valuemax="'.$percentage.'" style="width:'.$percentage.'%">'.$percentage.'%</div>
                                        </div>
                                    </div>
                                </div>';
                        }
                        $assets = $CONFIG['SystemURL'] . "/modules/servers/hostedai/assets";
                        $random = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 3);
                
                        $informationHtml = '
                        <link href="' . $assets . '/css/style.css?v=' . $random . '" rel="stylesheet">
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/validator/13.7.0/validator.min.js"></script>
                        <script src="' . $assets . '/js/custom.js?v=' . $random . '"></script>
                
                        <table class="ad_on_table_dash table table-striped" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tbody>
                                <tr>
                                    <td style="width:50%" class="hading-td">
                                        <div class="hosting-information">
                                            <div class="panel panel-primary">
                                                <div class="panel-heading"><p>Resource Overview</p> <p>'.$is_suspend.'</p> </div>
                                                <div class="panel-body overview-main">
                                                    <div class="row">
                                                        '.$resourceHTML.'
                                                    </div>
                                                </div>
                                            </div>
                
                                            <div class="panel panel-success">
                                                <div class="panel-heading">Team Members List</div>
                                                <div class="panel-body">
                                                    <table class="table table-bordered">
                                                        <thead class="members-list-head">
                                                            <tr>
                                                                <th scope="col">Email</th>
                                                                <th scope="col">Role</th>
                                                                <th scope="col">Status</th>
                                                            </tr>
                                                        </thead>
                                                            '.$teamMemberHTML.'
                                                    </table>
                
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>';
                
                        return [
                            " Hosting Information" => $informationHtml,
                            'GPU Instance ID' => $instanceId ?: 'No instance',
                            'Instance Status' => $instanceStatus,
                        ];
        
                    }
    
                }
    
    
    
            }

        }

    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'hostedai',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }

    // Fallback path (team/resource-overview lookup failed or threw) — still
    // surface the instance ID/status if it was resolved before the failure.
    if (isset($instanceId)) {
        return [
            'GPU Instance ID' => $instanceId ?: 'No instance',
            'Instance Status' => $instanceStatus,
        ];
    }
    return array();
}

/**
 * Client area output logic handling.
 *
 * This function is used to define module specific client area output. It should
 * return an array consisting of a template file and optional additional
 * template variables to make available to that template.
 *
 
 */
function hostedai_ClientArea(array $params)
{
    
    try {

        global $CONFIG;
        global $whmcs;
        $helper = new Helper($params);

        // ── HOSTED AI WALLET: balance + itemized top-up/usage history ────
        // Independent of the Hosted AI API calls below (pure local WHMCS DB
        // reads), so it still renders even if Hosted AI is unreachable.
        // This is the Hosted AI Wallet only — fully separate from WHMCS's
        // native account credit — debited by crons/hostedai_usage_cron.php
        // and credited by includes/hooks/hostedai_wallet_topup.php.
        require_once __DIR__ . '/../../../includes/hostedai_wallet.php';

        $walletBalance = 0.0;
        $walletHistory = [];
        try {
            $walletBalance = hostedai_wallet_get_balance($params['userid']);
            $walletHistory = hostedai_wallet_get_history($params['userid']);
        } catch (\Exception $e) {
            logActivity('hostedai: wallet history lookup failed: ' . $e->getMessage());
        }
        $walletTopupUrl = hostedai_wallet_get_topup_url();
        // ── END HOSTED AI WALLET ─────────────────────────────

        // ── CLIENT AREA ACTION HANDLER ──────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_shared_volume'])) {
            $sharedVolumeId = $helper->getSharedVolumeIdFromNotes($params['serviceid']);
            if (!empty($sharedVolumeId)) {
                $helper->deleteSharedVolume($sharedVolumeId);
                $helper->saveSharedVolumeId($params['serviceid'], '');
                logActivity('hostedai: client deleted shared volume ' . $sharedVolumeId);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_shared_volume'])) {
            $sharedStorageMapJson = $params['configoption21'] ?? '';
            $sharedStorageMap = [];
            if (!empty(trim($sharedStorageMapJson))) {
                $sharedStorageMap = json_decode($sharedStorageMapJson, true) ?? [];
            }

            $selectedSharedStorage = $_POST['shared_storage_choice'] ?? '';
            $newStorageBlockId = $sharedStorageMap[$selectedSharedStorage] ?? '';
            $newRegionId       = (int)($params['configoption16'] ?? 5);
            $newVolumeTeamId   = $helper->resolveTeamId($params);

            if (!empty($newStorageBlockId) && !empty($newVolumeTeamId)) {
                $volumeResponse = $helper->createSharedVolume(
                    'whmcs-' . $params['serviceid'] . '-shared',
                    $newRegionId,
                    $newStorageBlockId,
                    $newVolumeTeamId
                );

                if (!empty($volumeResponse['success'])) {
                    $helper->saveSharedVolumeId($params['serviceid'], $volumeResponse['result']->id);
                    logActivity('hostedai: client created shared volume ' . $volumeResponse['result']->id);
                } else {
                    $volErrorMsg = isset($volumeResponse['result']->message)
                        ? $volumeResponse['result']->message
                        : 'Shared volume creation failed (HTTP ' . ($volumeResponse['httpcode'] ?? '?') . ')';
                    logActivity('hostedai: client shared volume creation failed: ' . $volErrorMsg);
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
            (isset($_POST['instance_action']) || isset($_POST['delete_instance']))) {

            $instanceId = $helper->getPoolSubscriptionIdFromNotes(
                $params['serviceid']
            );

            if (!empty($instanceId)) {

                if (isset($_POST['instance_action'])) {
                    $action = $_POST['instance_action'];

                    if ($action === 'start') {
                        $helper->startInstance($instanceId);
                        logActivity('hostedai: client started instance '
                            . $instanceId);
                    } elseif ($action === 'stop') {
                        $helper->stopInstance($instanceId);
                        logActivity('hostedai: client stopped instance '
                            . $instanceId);
                    } elseif ($action === 'restart') {
                        $helper->restartInstance($instanceId);
                        logActivity('hostedai: client restarted instance '
                            . $instanceId);
                    }
                }

                if (isset($_POST['delete_instance'])) {
                    $helper->deleteInstance($instanceId);
                    $helper->savePoolSubscriptionId(
                        $params['serviceid'], ''
                    );
                    logActivity('hostedai: client deleted instance '
                        . $instanceId);
                }
            }
        }
        // ── END ACTION HANDLER ──────────────────────────────

        $loginURL = !empty($params['configoption7']) ? $params['configoption7'] : '#';

        $assets = $CONFIG['SystemURL'] . "/modules/servers/hostedai/assets";

        $language = $CONFIG['Language'];
        $langfilename = __DIR__ . '/lang/' . $language . '.php';
        if (file_exists($langfilename)) {
            require($langfilename);
        } else {
            require(__DIR__ . '/lang/english.php');
        }

        $teamId = $helper->resolveTeamId($params);
        if(!empty($teamId)) {
            $getTeamdata = $helper->getTeamDetail($teamId);

            if($getTeamdata && $getTeamdata['httpcode'] == 200)
            {
                $getTeamMembers = $helper->getTeamMembers($teamId);

                $responseData  = [
                    'name' => $getTeamdata['result']->team->name,
                    'teamId' => $teamId,
                ];

                $getResourceOverview = $helper->getResourceOverview($teamId);

                $templateFile = 'templates/manage.tpl';

                if($getResourceOverview['httpcode'] == 200) {

                    $resourceOverviewData = $getResourceOverview['result'];

                    foreach ($resourceOverviewData as $resourceKey => $resource) {
                        if (isset($resource->available) && $resource->available > 0) {
                            $resource->percent = ($resource->used / $resource->available) * 100;
                        } else {
                            $resource->percent = 0;
                        }
                    }

                    // ── GPU Pool data ─────────────────────────────────────────
                    $poolId           = (int)($params['configoption10'] ?? 0);
                    $poolData         = null;
                    $subscriptionData = null;
                    $instancesData    = [];

                    if ($poolId > 0) {
                        try {
                            $poolResp = $helper->getPoolDetails($poolId);
                            if ($poolResp['httpcode'] == 200) {
                                $poolData = $poolResp['result'];
                            }
                        } catch (\Exception $e) {
                            logActivity('hostedai pool details error: ' . $e->getMessage());
                        }

                        try {
                            $subResp = $helper->getPoolSubscriptionStatus($poolId, $teamId);
                            if ($subResp['httpcode'] == 200) {
                                $subResult = $subResp['result'];
                                // The endpoint may return an array of subscriptions or a
                                // single object; normalise to a single object for the template.
                                if (is_array($subResult) && !empty($subResult)) {
                                    $subscriptionData = $subResult[0];
                                } else {
                                    $subscriptionData = $subResult;
                                }
                            }
                        } catch (\Exception $e) {
                            logActivity('hostedai pool subscription error: ' . $e->getMessage());
                        }

                        try {
                            $instResp = $helper->getTeamInstances($teamId);
                            if ($instResp['httpcode'] == 200) {
                                // GET /instances returns { "data": [...] }
                                $instancesData = isset($instResp['result']->data)
                                    ? $instResp['result']->data
                                    : (is_array($instResp['result']) ? $instResp['result'] : []);
                            }
                        } catch (\Exception $e) {
                            logActivity('hostedai instances error: ' . $e->getMessage());
                        }
                    }
                    // ─────────────────────────────────────────────────────────

                    // ── INSTANCE MONITORING DATA ────────────────────────
                    $instanceId = $helper->getPoolSubscriptionIdFromNotes(
                        $params['serviceid']
                    );
                    $sharedVolumeId = $helper->getSharedVolumeIdFromNotes(
                        $params['serviceid']
                    );

                    $sharedStorageOptionsJson = $params['configoption21'] ?? '';
                    $sharedStorageOptions = [];
                    if (!empty(trim($sharedStorageOptionsJson))) {
                        $decodedStorageMap = json_decode($sharedStorageOptionsJson, true) ?? [];
                        $sharedStorageOptions = array_keys($decodedStorageMap);
                    }

                    $instanceDetails     = null;
                    $instanceStats       = null;
                    $instanceLogs        = null;
                    $instanceBilling     = null;
                    $instanceCredentials = null;

                    if (!empty($instanceId)) {
                        $r = $helper->getUnifiedInstance($instanceId);
                        if ($r['httpcode'] == 200) $instanceDetails = $r['result'];

                        // getInstanceStats removed — /object/stats does not support GPUaaS pod IDs
                        $instanceStats = null;

                        $r = $helper->getInstanceLogs($instanceId);
                        if ($r['httpcode'] == 200) $instanceLogs = $r['result'];

                        $r = $helper->getInstanceBilling($instanceId);
                        if ($r['httpcode'] == 200) $instanceBilling = $r['result'];

                        $r = $helper->getInstanceCredentials($instanceId);
                        if ($r['httpcode'] == 200) $instanceCredentials = $r['result'];
                    }
                    // ── END INSTANCE MONITORING DATA ────────────────────

                    return array(
                        'templatefile' => $templateFile,
                        'templateVariables' => array(
                            'walletBalance'       => $walletBalance,
                            'walletHistory'       => $walletHistory,
                            'walletTopupUrl'      => $walletTopupUrl,
                            'responseData'        => $responseData,
                            'teammembers'         => $getTeamMembers ? $getTeamMembers['result']->members : '',
                            'resourcesData'       => $resourceOverviewData,
                            'loginURL'            => '',
                            'serviceId'           => $params['serviceid'],
                            'userEmail'           => $params['clientsdetails']['email'],
                            'assets'              => $assets,
                            'LANG'                => $_ADDONLANG,
                            'poolData'            => $poolData,
                            'poolSubscription'    => $subscriptionData,
                            'instances'           => $instancesData,
                            'poolId'              => $poolId,
                            'instanceId'          => $instanceId ?? '',
                            'sharedVolumeId'      => $sharedVolumeId ?? '',
                            'sharedStorageOptions' => $sharedStorageOptions,
                            'instanceDetails'     => $instanceDetails,
                            'instanceStats'       => $instanceStats,
                            'instanceLogs'        => $instanceLogs,
                            'instanceBilling'     => $instanceBilling,
                            'instanceCredentials' => $instanceCredentials,
                        ),
                    );

                }
                
            }

        }

    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'hostedai',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        // In an error condition, display an error page.
        return array(
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => array(
                'usefulErrorHelper' => $e->getMessage(),
            ),
        );
    }
}
