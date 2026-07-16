<?php

namespace WHMCS\Module\Server\HosteDai;

use Exception;
use WHMCS\Database\Capsule;

use WHMCS\Module\Server;

class Helper
{
    public $baseUrl = '';
    public $token = '';

    /**
     * Convert decimal hours to hours:minutes format
     * @param float $decimalHours
     * @return string
     */
    public function formatHoursMinutes($decimalHours)
    {
        $hours = floor($decimalHours);
        $minutes = round(($decimalHours - $hours) * 60);

        // Handle edge case where rounding gives 60 minutes
        if ($minutes >= 60) {
            $hours += 1;
            $minutes = 0;
        }

        return sprintf('%d:%02d', $hours, $minutes);
    }
    public $key = '';
    public $method = 'GET';
    public $data = [];
    public $header = [];
    public $endPoint = '';
    public function __construct($params = NULL)
    {
        global $whmcs;

        $servername = $params['serverhostname'];
        if ($servername == '') {
            $servername = Capsule::table('tblservers')->where('type', 'hostedai')->where('disabled', 0)->value('hostname');
        }

        $this->baseUrl = "https://" . $servername . "/api/";

        $this->token = $params['serverpassword'];
        if ($this->token == '') {
            $password = Capsule::table('tblservers')->where('type', 'hostedai')->where('disabled', 0)->value('password');
            $this->token = decrypt($password);
        }
    }

    /** Get the policy data based on type */
    public function getPolicyItems($type = null)
    {

        try {
            /** api to get the license items */
            $endPoint = $type;

            $baseUrl = $this->baseUrl;
            $getUrl =  $baseUrl . $endPoint;
            $curlResponse = $this->curlCall("GET", "getPolicyItems", $endPoint, $getUrl);

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Error in function (getPolicyItems), Error: ', $e->getMessage());
        }
    }

    /** Api to create the Team */
    public function createHostedaiTeam($apiData)
    {
        try {
            $endPoint = 'team';
            $curlResponse = $this->curlCall("POST", "createHostedaiTeam", $endPoint, $apiData);

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Unable to create Hostedai Team, Error: ', $e->getMessage());
        }
    }

    /** Invite members to a team with pre-onboarding */
    public function inviteTeamMembers($teamId, $members)
    {
        try {
            $endPoint = 'team/' . $teamId . '/invite';
            $curlResponse = $this->curlCall("POST", "inviteTeamMembers", $endPoint, $members);
            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Unable to invite team members, Error: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /** Onboard a user (complete registration without email flow) */
    public function onboardUser($email, $name, $password)
    {
        try {
            $endPoint = 'onboard';
            $data = [
                'email' => $email,
                'name' => $name,
                'old_password' => $password,
                'new_password' => $password,
            ];
            $curlResponse = $this->curlCall("POST", "onboardUser", $endPoint, $data);
            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Unable to onboard user, Error: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /** Get the team based on teamID */
    public function getTeamDetail($teamid)
    {
        try {
            $endPoint = 'team/' . $teamid;
            $curlResponse = $this->curlCall("GET", "getTeamDetail", $endPoint, '');

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Unable to get team details, Error: ', $e->getMessage());
        }
    }

    /** Get the team members based on teamID */
    public function getTeamMembers($teamid)
    {
        try {
            $endPoint = 'team/' . $teamid . '/members?page=1&itemsPerPage=50';
            $curlResponse = $this->curlCall("GET", "getTeamMembers", $endPoint, '');

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Unable to get team details, Error: ', $e->getMessage());
        }
    }

    /** Get the resource overview based on teamID */
    public function getResourceOverview($teamid)
    {
        try {
            $endPoint = 'team/' . $teamid . '/resource-overview';
            $curlResponse = $this->curlCall("GET", "getResourceOverview", $endPoint, '');

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Unable to get Resource Overview, Error: ', $e->getMessage());
        }
    }

    /** Suspend team based on teamID */
    public function suspendHostedaiTeam($teamid)
    {
        try {
            $endPoint = 'team/' . $teamid . '/suspend';
            $curlResponse = $this->curlCall("POST", "suspendHostedaiTeam", $endPoint, '');

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Failed to Suspend hostedai team, Error: ', $e->getMessage());
        }
    }

    /** Unsuspend team based on teamID */
    public function unsuspendHostedaiTeam($teamid)
    {
        try {
            $endPoint = 'team/' . $teamid . '/unsuspend';
            $curlResponse = $this->curlCall("POST", "unsuspendHostedaiTeam", $endPoint, '');

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Failed to Unsuspend hostedai team, Error: ', $e->getMessage());
        }
    }

    /** Terminate team based on teamID */
    public function terminateHostedaiTeam($teamid)
    {
        try {
            $endPoint = 'team/' . $teamid;
            $curlResponse = $this->curlCall("DELETE", "terminateHostedaiTeam", $endPoint, '');

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Failed to Terminate hostedai team, Error: ', $e->getMessage());
        }
    }

    /* Generate Bill */
    public function generateBill($teamid)
    {
        try {

            // Production: Bill for last month
            $start_date = date('Y-m-01\T00:00', strtotime('first day of last month'));
            $end_date = date('Y-m-t\T23:59', strtotime('last month'));

            $endPoint = "team-billing/group-by-workspace/" . $teamid . "/" . $start_date . "/" . $end_date . "/monthly?timezone=UTC";

            // Debug logging (disabled in production for security)
            // logActivity("DEBUG generateBill: TeamID={$teamid}, StartDate={$start_date}, EndDate={$end_date}");
            // logActivity("DEBUG generateBill: Full URL=" . $this->baseUrl . $endPoint);

            $curlResponse = $this->curlCall("GET", "generateBill", $endPoint, '');

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Unable to Generate the bill, Error: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /* Generate Detailed Team Bill with enhanced data */
    public function generateDetailedTeamBill($teamid, $start_date = null, $end_date = null, $interval = 'monthly')
    {
        try {
            if (!$start_date) {
                $start_date = date('Y-m-01\T00:00', strtotime('first day of last month'));
            }
            if (!$end_date) {
                $end_date = date('Y-m-t\T23:59', strtotime('last month'));
            }

            $endPoint = "team-billing/" . $teamid . "/" . $start_date . "/" . $end_date . "/" . $interval . "?timezone=UTC";

            $curlResponse = $this->curlCall("GET", "generateDetailedTeamBill", $endPoint, '');

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Unable to Generate detailed team bill, Error: ', $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /* Get Workspace Billing */
    public function getWorkspaceBilling($workspaceId, $start_date = null, $end_date = null, $interval = 'monthly')
    {
        try {
            if (!$start_date) {
                $start_date = date('Y-m-01\T00:00', strtotime('first day of last month'));
            }
            if (!$end_date) {
                $end_date = date('Y-m-t\T23:59', strtotime('last month'));
            }

            $endPoint = "workspace-billing/" . $workspaceId . "/" . $start_date . "/" . $end_date . "/" . $interval . "?timezone=UTC";

            $curlResponse = $this->curlCall("GET", "getWorkspaceBilling", $endPoint, '');

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Unable to Get workspace billing, Error: ', $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /* Get Shared Storage Billing for Team by Region */
    public function getTeamSharedStorageBilling($teamId, $regionId = 'all', $start_date = null, $end_date = null, $interval = 'monthly')
    {
        try {
            if (!$start_date) {
                $start_date = date('Y-m-01\T00:00', strtotime('first day of last month'));
            }
            if (!$end_date) {
                $end_date = date('Y-m-t\T23:59', strtotime('last month'));
            }

            $endPoint = "team-billing/shared-storage/" . $teamId . "/" . $start_date . "/" . $end_date . "/" . $interval . "?region_id=" . urlencode($regionId) . "&timezone=UTC";

            $curlResponse = $this->curlCall("GET", "getTeamSharedStorageBilling", $endPoint, '');

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Unable to Get shared storage billing, Error: ', $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /* Get GPUaaS Pool Billing for Team by Region */
    public function getTeamGpuaasPoolBilling($teamId, $regionId = 'all', $start_date = null, $end_date = null, $interval = 'monthly')
    {
        try {
            if (!$start_date) {
                $start_date = date('Y-m-01\T00:00', strtotime('first day of last month'));
            }
            if (!$end_date) {
                $end_date = date('Y-m-t\T23:59', strtotime('last month'));
            }

            $endPoint = "team-billing/gpuaas-pool/" . $teamId . "/" . $start_date . "/" . $end_date . "/" . $interval . "?region_id=" . urlencode($regionId) . "&timezone=UTC";

            $curlResponse = $this->curlCall("GET", "getTeamGpuaasPoolBilling", $endPoint, '');

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Unable to Get GPUaaS pool billing, Error: ', $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /* Generate Invoice */
    public function createInvoice($id, $invoice, $currencyCode = null)
    {
        try {
            $command = 'CreateInvoice';
            $postData = [
                'userid' => $id,
                'date' => date('Y-m-d'),
                'duedate' => date('Y-m-d', strtotime('+7 days')),
            ];

            // Add currency if provided by API
            if ($currencyCode && $currencyCode !== 'USD') {
                // Get WHMCS currency ID for the currency code
                $currencyId = \WHMCS\Database\Capsule::table('tblcurrencies')
                    ->where('code', $currencyCode)
                    ->value('id');

                if ($currencyId) {
                    $postData['currency'] = $currencyId;
                    logActivity("Invoice created with currency: {$currencyCode} (ID: {$currencyId})");
                } else {
                    logActivity("Warning: Currency {$currencyCode} not found in WHMCS, using default currency");
                }
            }

            $postData = array_merge($postData, $invoice);

            $results = localAPI($command, $postData);
            return $results;
        } catch (Exception $e) {
            logActivity('Unable to generate invoice for user ' . $id . ', WHMCS LOCAL API ERROR: ', $e->getMessage());
        }
    }

    /** Change package based on teamID */
    public function changeHostedaiTeamPackage($pricing_id, $resource_id, $teamId)
    {
        try {

            $updatePricingPolicy = $this->updatePricing($pricing_id, $teamId);
            $updateResourcePolicy = $this->updateResource($resource_id, $teamId);

            if($updatePricingPolicy['httpcode'] == 200 && $updateResourcePolicy['httpcode'] == 200) {
                return [
                    'status' => 'success',
                    'message' => 'Team package updated successfully.',
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Error to change the team package.',
                ];
            }

        } catch (Exception $e) {
            logActivity('Failed to Change hostedai team package ID:' .$teamId.  ', Error: ', $e->getMessage());
        }
    }


    /* Update pricing */
    public function updatePricing($pricing_id, $team_id)
    {
        try {
            $endPoint = 'pricing-policy/'. $pricing_id .'/assign-team';

            $data = ["team_id" => $team_id];

            $curlResponse = $this->curlCall("POST", "updatePricing", $endPoint, $data);

            return $curlResponse;

        } catch (Exception $e) {
            logActivity('Failed to Change hostedai team package, Error: ', $e->getMessage());
        }
    }


    /* Update resource */
    public function updateResource($resource_id, $team_id)
    {
        try {
            $endPoint = 'resource-policy/assign-team';

            $data = ["policy_id" => $resource_id, "team_id" => $team_id];

            $curlResponse = $this->curlCall("POST", "updateResource", $endPoint, $data);

            return $curlResponse;

        } catch (Exception $e) {
            logActivity('Failed to Change hostedai team package, Error: ', $e->getMessage());
        }
    }


    /* Suspend or Terminate Hostedai Service */
    public function suspendTerminate_service($serviceId, $pid, $command)
    {
        try {
            $postData = array(
                'serviceid' => $serviceId,
            );

            $results = localAPI($command, $postData);

            if ($command = 'ModuleTerminate') {
                if ($results['httpcode'] == 200 && $results['result'] == 'success') {
                    $this->delete_teamDetail($serviceId, $pid);
                }
            }

            return $results;
        } catch (Exception $e) {
            logActivity($command . ' failed, Error:' . $e->getMessage());
        }
    }

    /** Create the custom fields */
    public function createHostedaiCustomFields($customfieldarray)
    {
        foreach ($customfieldarray as $fieldname => $customfieldarrays) {

            if (Capsule::table('tblcustomfields')->where('type', $customfieldarrays['type'])->where('relid', $customfieldarrays['relid'])->where('fieldname', 'like', '%' . $fieldname . '%')->count() == 0) {
                Capsule::table('tblcustomfields')->insert($customfieldarrays);
            }
        }
    }

    /** Update custom fields data  */
    public function insert_hostedai_custom_fields_value($serviceid, $package_id, $fields = [])
    {
        try {
            foreach ($fields as $key => $value) {
                $custom_field_data = Capsule::table('tblcustomfields')->where("type", "product")->where("fieldname", "like", "%$key%")->where("relid", $package_id)->first();

                if ($custom_field_data) {
                    $field_value = Capsule::table('tblcustomfieldsvalues')->where("fieldid", "=", $custom_field_data->id)->where("relid", "=", $serviceid)->first();

                    if ($field_value->id) {
                        $field_value = Capsule::table('tblcustomfieldsvalues')->where("fieldid", "=", $custom_field_data->id)->where("relid", "=", $serviceid)->update(["value" => $value]);
                    } else {
                        $field_value = Capsule::table('tblcustomfieldsvalues')->insert(["fieldid" => $custom_field_data->id, "relid" => $serviceid, "value" => $value]);
                    }
                }
            }

            return "success";
        } catch (\Exception $e) {
            logActivity('funtion(insert_hostedai_custom_fields_value) Hostedai Error:', $e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * Ensure the client-level Hosted AI team_id custom field exists.
     * (One team per WHMCS client — Change 1.)
     */
    public function ensureClientTeamIdField()
    {
        try {
            $field = Capsule::table('tblcustomfields')
                ->where('type', 'client')
                ->where('fieldname', 'like', '%hostedai_team_id%')
                ->first();

            if (!$field) {
                Capsule::table('tblcustomfields')->insert([
                    'type'      => 'client',
                    'relid'     => 0,
                    'fieldname' => 'Hosted AI Team ID|hostedai_team_id',
                    'fieldtype' => 'text',
                    'adminonly' => 'on',
                ]);

                $field = Capsule::table('tblcustomfields')
                    ->where('type', 'client')
                    ->where('fieldname', 'like', '%hostedai_team_id%')
                    ->first();
            }

            return $field;
        } catch (Exception $e) {
            logActivity('hostedai: ensureClientTeamIdField error: ' . $e->getMessage());
            return null;
        }
    }

    /** Save (or update) the Hosted AI team_id for a WHMCS client. */
    public function saveClientTeamId($clientId, $teamId)
    {
        try {
            $field = $this->ensureClientTeamIdField();
            if (!$field) {
                return;
            }

            $existing = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $field->id)
                ->where('relid', $clientId)
                ->first();

            if ($existing) {
                Capsule::table('tblcustomfieldsvalues')
                    ->where('fieldid', $field->id)
                    ->where('relid', $clientId)
                    ->update(['value' => $teamId]);
            } else {
                Capsule::table('tblcustomfieldsvalues')->insert([
                    'fieldid' => $field->id,
                    'relid'   => $clientId,
                    'value'   => $teamId,
                ]);
            }
        } catch (Exception $e) {
            logActivity('hostedai: saveClientTeamId error: ' . $e->getMessage());
        }
    }

    /** Get the Hosted AI team_id for a WHMCS client, or null if none stored. */
    public function getClientTeamId($clientId)
    {
        try {
            $field = Capsule::table('tblcustomfields')
                ->where('type', 'client')
                ->where('fieldname', 'like', '%hostedai_team_id%')
                ->first();

            if (!$field) {
                return null;
            }

            $value = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $field->id)
                ->where('relid', $clientId)
                ->first();

            return $value ? $value->value : null;
        } catch (Exception $e) {
            logActivity('hostedai: getClientTeamId error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve the Hosted AI team_id for a service/client.
     *
     * Client-level field is the source of truth for services created after
     * the client=team change. Falls back to the legacy per-service product
     * custom field for services created before it, so existing services
     * keep working without any migration step.
     */
    public function resolveTeamId($params)
    {
        try {
            $clientId  = $params['userid'];
            $serviceId = $params['serviceid'];
            $pid       = $params['pid'];

            // Try client-level first (new way)
            $teamId = $this->getClientTeamId($clientId);

            // Fall back to per-service custom field (old way)
            if (empty($teamId)) {
                if (isset($params['customfields']['team_id']) && $params['customfields']['team_id'] !== '') {
                    $teamId = $params['customfields']['team_id'];
                } else {
                    $field = Capsule::table('tblcustomfields')
                        ->where('type', 'product')
                        ->where('relid', $pid)
                        ->where('fieldname', 'like', '%team_id%')
                        ->first();

                    if ($field) {
                        $value = Capsule::table('tblcustomfieldsvalues')
                            ->where('fieldid', $field->id)
                            ->where('relid', $serviceId)
                            ->first();

                        if ($value) {
                            $teamId = $value->value;
                        }
                    }
                }
            }

            if (empty($teamId)) {
                logActivity('hostedai: WARNING - could not resolve team_id for client '
                    . $clientId . ' service ' . $serviceId);
            }

            return $teamId;
        } catch (Exception $e) {
            logActivity('hostedai: resolveTeamId error: ' . $e->getMessage());
            return null;
        }
    }

    /* Insert Team details in custom table */
    public function insert_teamDetail($userId, $serviceId, $pid, $actionId, $action)
    {
        try {
            if (!Capsule::schema()->hasTable('mod_hostdaiteam_details')) {
                Capsule::schema()->create('mod_hostdaiteam_details', function ($table) {
                    $table->increments('id');
                    $table->string('uid');
                    $table->string('sid');
                    $table->string('pid');
                    $table->string('teamid');
                    $table->string('invoiceid');
                    $table->string('status');
                    $table->timestamps();
                });
            }

            if ($action == 'insert') {

                Capsule::table('mod_hostdaiteam_details')->insert([
                    'uid' => $userId,
                    'sid' => $serviceId,
                    'pid' => $pid,
                    'teamid' => $actionId,
                    'invoiceid' => '',
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } elseif ($action == 'update') {
                Capsule::table('mod_hostdaiteam_details')->where('uid', $userId)->where('pid', $pid)->where('sid', $serviceId)->update([
                    'invoiceid' => $actionId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Exception $e) {
            logActivity('Function (insert_teamDetail) Hostedai Error: ' . $e->getMessage());
        }
    }

    /* Delete Custom table values */
    public function delete_teamDetail($serviceId, $pid)
    {
        try {
            // Table is only created lazily by insert_teamDetail() for "One Time"
            // billing cycle services — recurring services never create it, so
            // guard the delete the same way insert_teamDetail() guards the create.
            if (!Capsule::schema()->hasTable('mod_hostdaiteam_details')) {
                return;
            }
            Capsule::table('mod_hostdaiteam_details')->where('sid', $serviceId)->where('pid', $pid)->delete();
        } catch (\Exception $e) {
            logActivity('Function (delete_teamDetail) Hostedai Error: ' . $e->getMessage());
        }
    }

    /** Create One Time Login Token */
    public function createOneTimeLoginToken($userEmail, $fullData = null)
    {
        try {
            $endPoint = 'create-otl';
            if ($fullData) {
                $data = $fullData;
            } else {
                $data = [
                    'email' => $userEmail,
                    'send_email_invite' => false
                ];
            }
            $curlResponse = $this->curlCall("POST", "createOneTimeLoginToken", $endPoint, $data);
            return $curlResponse;
        } catch (Exception $e) {
            logActivity('Unable to create OTL token, Error: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GPU Pool (GPUaaS) helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all available GPU pools.
     * GET /gpuaas/all-pools
     * Returns ['httpcode' => int, 'result' => array of pool objects]
     */
    public function getAllPools()
    {
        try {
            $curlResponse = $this->curlCall('GET', 'getAllPools', 'gpuaas/all-pools', '');
            return $curlResponse;
        } catch (Exception $e) {
            logActivity('hostedai: Failed to get GPU pools: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * List all available GPU instance types.
     * GET /gpuaas/instance-types
     */
    public function getInstanceTypes()
    {
        try {
            $curlResponse = $this->curlCall('GET', 'getInstanceTypes', 'gpuaas/instance-types', '');
            return $curlResponse;
        } catch (Exception $e) {
            logActivity('hostedai: Failed to get instance types: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Create a GPU instance for a team.
     * POST /service/i/create-instance
     * Returns instance ID string on success.
     */
    public function createInstance(
        $teamId,
        $serviceId,
        $regionId,
        $poolId,
        $vgpus,
        $imageHash,
        $instanceTypeId,
        $storageBlockId,
        $workspaceId,
        $instanceName
    ) {
        $data = [
            'service_id'           => (string)$serviceId,
            'team_id'              => (string)$teamId,
            'region_id'            => (int)$regionId,
            'image_hash'           => (string)$imageHash,
            'instance_type_id'     => (string)$instanceTypeId,
            'root_storage_type_id' => (string)$storageBlockId,
            'name'                 => (string)$instanceName,
            'description'          => 'WHMCS provisioned instance',
            'pod_opts'             => [
                'pool_id'        => (int)$poolId,
                'vgpus'          => (int)$vgpus,
                'shared_volumes' => [],
            ],
            'public_keys'  => [''],
            'workspace_id' => (string)$workspaceId,
        ];

        logActivity('hostedai: Starting createInstance for team ' . $teamId . ' pool ' . $poolId);

        $maxAttempts  = 3;
        $lastResponse = ['httpcode' => 500, 'result' => null, 'success' => false];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = $this->curlCall(
                'POST', 'createInstance',
                'service/i/create-instance',
                $data, true
            );

            logActivity('hostedai createInstance attempt ' . $attempt
                . ' HTTP: ' . $response['httpcode']
                . ' Response: ' . substr((string)$response['result'], 0, 100));

            if ($response['httpcode'] == 200 || $response['httpcode'] == 201) {
                $instanceId = trim($response['result'], " \t\n\r\0\x0B\"");
                return [
                    'httpcode' => $response['httpcode'],
                    'result'   => $instanceId,
                    'success'  => true,
                ];
            }

            $lastResponse = $response;

            if ($attempt < $maxAttempts) {
                sleep(2);
            }
        }

        logActivity('hostedai: createInstance failed after ' . $maxAttempts . ' attempts. Last HTTP: ' . $lastResponse['httpcode']);
        return $lastResponse;
    }

    /**
     * Delete a GPU instance.
     * DELETE /instance/{instanceId}
     */
    public function deleteInstance($instanceId)
    {
        try {
            $response = $this->curlCall(
                'DELETE', 'deleteInstance',
                'instance/' . $instanceId,
                ''
            );
            return $response;
        } catch (Exception $e) {
            logActivity('hostedai: Failed to delete instance: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Subscribe a team to a GPU pool.
     * POST /gpuaas/pool/subscribe
     *
     * The API returns a plain string (subscription ID), not a JSON object.
     * We pass $returnRaw = true to curlCall so json_decode is skipped.
     *
     * @param int    $poolId         pool_id integer from getAllPools
     * @param string $teamId         team UUID
     * @param int    $vgpus          number of vGPUs (minimum 1)
     * @param string $instanceTypeId instance type UUID from getInstanceTypes (e.g. "d3bb7f05-..." for Medium)
     * @param string $storageBlockId ephemeral storage block UUID — must belong to the chosen instance type;
     *                               use the instance type's default_storage_block.id if unsure
     * @return array ['httpcode' => int, 'result' => string|object]
     *               On success result is the raw subscription-ID string.
     *               On failure result is the decoded error object.
     */
    public function subscribeToPool($poolId, $teamId, $vgpus = 1, $instanceTypeId = '', $storageBlockId = '')
    {
        try {
            $poolId = (int)$poolId;
            $vgpus  = max(1, (int)$vgpus);

            $data = [
                'pool_id'                      => (int)$poolId,
                'team_id'                      => (string)$teamId,
                'vgpus'                        => (int)$vgpus,
                'guaranteed_gpu_share_percent' => 0,
                'is_vip_priority'              => false,
                'shared_volumes'               => [],
                'instance_type_id'             => (string)$instanceTypeId,
                'ephemeral_storage_block_id'   => (string)$storageBlockId,
            ];
            // returnRaw = true: the success response body is a plain subscription-ID string
            $curlResponse = $this->curlCall('POST', 'subscribeToPool', 'gpuaas/pool/subscribe', $data, true);

            // If the request failed (4xx/5xx), decode the error JSON for readable messages
            if ($curlResponse['httpcode'] >= 400) {
                $curlResponse['result'] = json_decode($curlResponse['result']);
            } else {
                // Strip surrounding quotes if the server returns a JSON-encoded string
                $raw = trim($curlResponse['result'], " \t\n\r\0\x0B\"");
                $curlResponse['result'] = $raw;
            }

            return $curlResponse;
        } catch (Exception $e) {
            logActivity('hostedai: Failed to subscribe to GPU pool: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Unsubscribe a team from a GPU pool.
     * POST /gpuaas/pool/unsubscribe
     *
     * @param int    $poolId  pool_id integer
     * @param string $teamId  team UUID
     */
    public function unsubscribeFromPool($poolId, $teamId)
    {
        try {
            $data = [
                'pool_id' => (int)$poolId,
                'team_id' => (string)$teamId,
            ];
            $curlResponse = $this->curlCall('POST', 'unsubscribeFromPool', 'gpuaas/pool/unsubscribe', $data);
            return $curlResponse;
        } catch (Exception $e) {
            logActivity('hostedai: Failed to unsubscribe from GPU pool: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Get details for a single pool.
     * GET /gpuaas/pools/{poolId}
     */
    public function getPoolDetails($poolId)
    {
        try {
            $curlResponse = $this->curlCall('GET', 'getPoolDetails', 'gpuaas/pools/' . (int)$poolId, '');
            return $curlResponse;
        } catch (Exception $e) {
            logActivity('hostedai: Failed to get pool details: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Get a team's subscription status for a pool.
     * GET /gpuaas/pools/{poolId}/subscriptions?team_id={teamId}
     */
    public function getPoolSubscriptionStatus($poolId, $teamId)
    {
        try {
            $endpoint = 'gpuaas/pools/' . (int)$poolId . '/subscriptions?team_id=' . urlencode($teamId);
            $curlResponse = $this->curlCall('GET', 'getPoolSubscriptionStatus', $endpoint, '');
            return $curlResponse;
        } catch (Exception $e) {
            logActivity('hostedai: Failed to get pool subscription status: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Get cumulative metrics for a pool.
     * GET /gpuaas/pools/{poolId}/metrics/cumulative
     */
    public function getPoolMetrics($poolId)
    {
        try {
            $endpoint = 'gpuaas/pools/' . (int)$poolId . '/metrics/cumulative';
            $curlResponse = $this->curlCall('GET', 'getPoolMetrics', $endpoint, '');
            return $curlResponse;
        } catch (Exception $e) {
            logActivity('hostedai: Failed to get pool metrics: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Get instances for a team.
     * GET /instances?team_id={teamId}
     */
    public function getTeamInstances($teamId)
    {
        try {
            $endpoint = 'instances?team_id=' . urlencode($teamId);
            $curlResponse = $this->curlCall('GET', 'getTeamInstances', $endpoint, '');
            return $curlResponse;
        } catch (Exception $e) {
            logActivity('hostedai: Failed to get team instances: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Get unified instance details
     * GET /instances/unified/{instanceId}
     */
    public function getUnifiedInstance($instanceId)
    {
        try {
            $endpoint = 'instances/unified/' . urlencode($instanceId);
            return $this->curlCall('GET', 'getUnifiedInstance', $endpoint, '');
        } catch (Exception $e) {
            logActivity('hostedai: getUnifiedInstance error: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Get instance metrics/stats
     * GET /object/stats?object_id={instanceId}
     */
    public function getInstanceStats($instanceId)
    {
        try {
            $endpoint = 'object/stats?object_id=' . urlencode($instanceId);
            return $this->curlCall('GET', 'getInstanceStats', $endpoint, '');
        } catch (Exception $e) {
            logActivity('hostedai: getInstanceStats error: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Get instance logs
     * GET /logs/instances/{resource_id}
     */
    public function getInstanceLogs($instanceId)
    {
        try {
            $endpoint = 'logs/instances/' . urlencode($instanceId)
                      . '?page=0&pageSize=50';
            return $this->curlCall('GET', 'getInstanceLogs', $endpoint, '');
        } catch (Exception $e) {
            logActivity('hostedai: getInstanceLogs error: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Get instance billing for current month
     * GET /instance-billing/{id}/{start}/{end}/{interval}
     */
    public function getInstanceBilling($instanceId)
    {
        try {
            $start    = urlencode(date('Y-m-01') . 'T00:00');
            $end      = urlencode(date('Y-m-d')  . 'T23:59');
            $endpoint = 'instance-billing/'
                      . $instanceId . '/'
                      . $start . '/'
                      . $end . '/monthly?timezone=utc';
            return $this->curlCall('GET', 'getInstanceBilling', $endpoint, '');
        } catch (Exception $e) {
            logActivity('hostedai: getInstanceBilling error: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Get running hours for an instance in an arbitrary time window.
     * Sums "Subscription Rate".hour_consumption across all hourly intervals
     * (the "GPU" hour_consumption field is always 0 on this API and must not
     * be used for billing).
     *
     * @param string $instanceId
     * @param string $startDateTime 'Y-m-d\TH:i' — already formatted by caller, UTC, no seconds
     * @param string $endDateTime   'Y-m-d\TH:i' — already formatted by caller, UTC, no seconds
     * @return float|null Total running hours, or null on failure.
     */
    public function getRunningHours($instanceId, $startDateTime, $endDateTime)
    {
        try {
            $start = urlencode($startDateTime);
            $end   = urlencode($endDateTime);
            $endpoint = 'instance-billing/' . $instanceId . '/'
                      . $start . '/' . $end . '/hourly?timezone=utc';

            $resp = $this->curlCall('GET', 'getRunningHours', $endpoint, '');
            if ($resp['httpcode'] != 200 || empty($resp['result'])) {
                return null;
            }

            $result = $resp['result'];
            // Navigate: billing_info.instances.{id}.intervals
            $instances = $result->billing_info->instances ?? null;
            if (!$instances) {
                return 0.0;
            }

            $inst = $instances->{$instanceId} ?? null;
            if (!$inst || empty($inst->intervals)) {
                return 0.0;
            }

            $totalHours = 0.0;
            foreach ($inst->intervals as $timestamp => $interval) {
                $subRate = $interval->Resources->{'Subscription Rate'} ?? null;
                if ($subRate && isset($subRate->hour_consumption)) {
                    $totalHours += (float) $subRate->hour_consumption;
                }
            }
            return $totalHours;
        } catch (\Exception $e) {
            logActivity('hostedai: getRunningHours error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get instance SSH credentials
     * GET /instance/{instanceId}/credentials
     */
    public function getInstanceCredentials($instanceId)
    {
        try {
            $endpoint = 'instance/' . urlencode($instanceId) . '/credentials';
            return $this->curlCall('GET', 'getInstanceCredentials', $endpoint, '');
        } catch (Exception $e) {
            logActivity('hostedai: getInstanceCredentials error: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Start an instance
     * PUT /instance/{instanceId}/start
     */
    public function startInstance($instanceId)
    {
        try {
            $endpoint = 'instance/' . urlencode($instanceId) . '/start';
            return $this->curlCall('PUT', 'startInstance', $endpoint, '');
        } catch (Exception $e) {
            logActivity('hostedai: startInstance error: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Stop an instance
     * PUT /instance/{instanceId}/stop
     */
    public function stopInstance($instanceId)
    {
        try {
            $endpoint = 'instance/' . urlencode($instanceId) . '/stop';
            return $this->curlCall('PUT', 'stopInstance', $endpoint, '');
        } catch (Exception $e) {
            logActivity('hostedai: stopInstance error: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    /**
     * Restart an instance
     * PUT /instance/{instanceId}/restart
     */
    public function restartInstance($instanceId)
    {
        try {
            $endpoint = 'instance/' . urlencode($instanceId) . '/restart';
            return $this->curlCall('PUT', 'restartInstance', $endpoint, '');
        } catch (Exception $e) {
            logActivity('hostedai: restartInstance error: ' . $e->getMessage());
            return ['httpcode' => 500, 'result' => null];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Pool subscription-ID persistence (stored in tblhosting notes)
    // Format:  pool_subscription_id:{value}
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Write (or clear) the pool_subscription_id note on a hosting record.
     *
     * @param int    $serviceId      tblhosting.id
     * @param string $subscriptionId Pass empty string to remove the note.
     */
    public function savePoolSubscriptionId($serviceId, $subscriptionId)
    {
        try {
            $notes = Capsule::table('tblhosting')->where('id', $serviceId)->value('notes');
            $notes = (string)$notes;

            // Remove any existing pool_subscription_id line.
            $notes = preg_replace('/pool_subscription_id:[^\r\n]*(\r\n|\r|\n)?/', '', $notes);
            $notes = rtrim($notes);

            if ($subscriptionId !== '') {
                $notes = ($notes !== '' ? $notes . "\n" : '') . 'pool_subscription_id:' . $subscriptionId;
            }

            Capsule::table('tblhosting')->where('id', $serviceId)->update(['notes' => $notes]);
        } catch (\Exception $e) {
            logActivity('hostedai: Failed to save pool subscription ID: ' . $e->getMessage());
        }
    }

    /**
     * Read the pool_subscription_id stored in tblhosting notes.
     *
     * @param int $serviceId  tblhosting.id
     * @return string|null  The subscription ID, or null if not set.
     */
    public function getPoolSubscriptionIdFromNotes($serviceId)
    {
        try {
            $notes = Capsule::table('tblhosting')->where('id', $serviceId)->value('notes');
            if ($notes && preg_match('/pool_subscription_id:([^\r\n]+)/', $notes, $matches)) {
                return trim($matches[1]);
            }
        } catch (\Exception $e) {
            logActivity('hostedai: Failed to read pool subscription ID: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Write the selected recipe name to tblhosting notes (recipe:{name}).
     * Idempotent — replaces any previous recipe: line rather than appending.
     *
     * @param int    $serviceId  tblhosting.id
     * @param string $recipeName Pass empty string to remove the note.
     */
    public function saveRecipeNote($serviceId, $recipeName)
    {
        try {
            $notes = Capsule::table('tblhosting')->where('id', $serviceId)->value('notes');
            $notes = (string)$notes;

            $notes = preg_replace('/recipe:[^\r\n]*(\r\n|\r|\n)?/', '', $notes);
            $notes = rtrim($notes);

            if ($recipeName !== '') {
                $notes = ($notes !== '' ? $notes . "\n" : '') . 'recipe:' . $recipeName;
            }

            Capsule::table('tblhosting')->where('id', $serviceId)->update(['notes' => $notes]);
        } catch (\Exception $e) {
            logActivity('hostedai: Failed to save recipe note: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────

    /* Retrieve the Curl API response.
     *
     * @param bool $returnRaw  When true, 'result' is the raw response string
     *                         instead of json_decode()'d output. Use this for
     *                         endpoints that return a plain (non-JSON) string.
     */
    public function curlCall($method, $action, $endpoint = null, $data = null, $returnRaw = false)
    {
        $baseUrl = $this->baseUrl;

        // Pre-encode to preserve boolean types (false stays false, not "")
        $postBody = '';
        if (is_string($data)) {
            $postBody = $data;
        } elseif (!empty((array)$data)) {
            $postBody = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $curl = curl_init();
        switch ($method) {
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $postBody);
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $postBody);
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $postBody);
                break;
            default:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        }

        curl_setopt($curl, CURLOPT_URL, $baseUrl . $endpoint);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);

        if (isset($this->token) && $this->token != '') {
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'Content-Type: application/json',
                'x-api-key: ' . $this->token
            ]);
        } else {
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $response  = curl_exec($curl);
        $httpCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);

        if ($curlError) {
            logActivity("CURL Error: Connection failed");
        }
        if ($httpCode >= 400) {
            logActivity("API Error: HTTP {$httpCode}");
        }
        if (empty($this->token)) {
            logActivity("Configuration Error: API token not configured");
        }

        if (curl_errno($curl)) {
            throw new \Exception(curl_error($curl));
        }
        curl_close($curl);

        if ($data == '') {
            $data = ['url' => $baseUrl . $endpoint];
        }

        if ($action == 'suspendHostedaiTeam') {
            $response = json_encode(['response' => 'Hostedai team suspended successfully.']);
        } elseif ($action == 'unsuspendHostedaiTeam') {
            $response = json_encode(['response' => 'Hostedai team unsuspended successfully.']);
        } elseif ($action == 'terminateHostedaiTeam') {
            $response = json_encode(['response' => 'Hostedai team terminated successfully.']);
        }

        if ($returnRaw) {
            logModuleCall("Hostedai", $action, $data, $response);
            return ['httpcode' => $httpCode, 'result' => $response];
        }

        logModuleCall("Hostedai", $action, $data, json_decode($response));
        return ['httpcode' => $httpCode, 'result' => json_decode($response)];
    }
}