<?php

declare(strict_types=1);
class ekeySystem extends IPSModuleStrict
{
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('SystemId', '');
        $this->RegisterPropertyString('NotificationMapping', '');

        $this->RegisterAttributeString('WebHooks', '{}');
        $this->RegisterAttributeString('NotificationKey', $this->generateRandomString(32));

        //Connect to available splitter or create a new one
        $this->ConnectParent('{12581FD4-FA80-AE58-7EF7-74733949B98B}');

        //Register Hook for commands
        $this->RegisterHook('ekey_bionyx/' . $this->InstanceID);

        //Register profiles for the Notification API
        if (!IPS_VariableProfileExists('InputTypeValues.eKey')) {
            IPS_CreateVariableProfile('InputTypeValues.eKey', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('InputTypeValues.eKey', 10, $this->Translate('Finger'), '', -1);
            IPS_SetVariableProfileAssociation('InputTypeValues.eKey', 20, $this->Translate('Digital Input'), '', -1);
        }

        if (!IPS_VariableProfileExists('ResultValues.eKey')) {
            IPS_CreateVariableProfile('ResultValues.eKey', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('ResultValues.eKey', 0, $this->Translate('Unknown'), '', -1);
            IPS_SetVariableProfileAssociation('ResultValues.eKey', 10, $this->Translate('Match'), '', -1);
            IPS_SetVariableProfileAssociation('ResultValues.eKey', 20, $this->Translate('Filtered Match'), '', -1);
            IPS_SetVariableProfileAssociation('ResultValues.eKey', 30, $this->Translate('No Match'), '', -1);
        }

        if (!IPS_VariableProfileExists('ResultDetailValues.eKey')) {
            IPS_CreateVariableProfile('ResultDetailValues.eKey', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('ResultDetailValues.eKey', 0, $this->Translate('Unknown'), '', -1);
            IPS_SetVariableProfileAssociation('ResultDetailValues.eKey', 10, $this->Translate('Input Disabled'), '', -1);
            IPS_SetVariableProfileAssociation('ResultDetailValues.eKey', 20, $this->Translate('Time Schedule'), '', -1);
            IPS_SetVariableProfileAssociation('ResultDetailValues.eKey', 30, $this->Translate('No Rule Found'), '', -1);
            IPS_SetVariableProfileAssociation('ResultDetailValues.eKey', 40, $this->Translate('No Input Found'), '', -1);
            IPS_SetVariableProfileAssociation('ResultDetailValues.eKey', 50, $this->Translate('Invalid Input'), '', -1);
        }

        if (!IPS_VariableProfileExists('FingerIndex.eKey')) {
            IPS_CreateVariableProfile('FingerIndex.eKey', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('FingerIndex.eKey', -5, $this->Translate('Left Little Finger'), '', -1);
            IPS_SetVariableProfileAssociation('FingerIndex.eKey', -4, $this->Translate('Left Ring Finger'), '', -1);
            IPS_SetVariableProfileAssociation('FingerIndex.eKey', -3, $this->Translate('Left Middle Finger'), '', -1);
            IPS_SetVariableProfileAssociation('FingerIndex.eKey', -2, $this->Translate('Left Index Finger'), '', -1);
            IPS_SetVariableProfileAssociation('FingerIndex.eKey', -1, $this->Translate('Left Thumb'), '', -1);
            IPS_SetVariableProfileAssociation('FingerIndex.eKey', 0, $this->Translate('None'), '', -1);
            IPS_SetVariableProfileAssociation('FingerIndex.eKey', 1, $this->Translate('Right Thumb'), '', -1);
            IPS_SetVariableProfileAssociation('FingerIndex.eKey', 2, $this->Translate('Right Index Finger'), '', -1);
            IPS_SetVariableProfileAssociation('FingerIndex.eKey', 3, $this->Translate('Right Middle Finger'), '', -1);
            IPS_SetVariableProfileAssociation('FingerIndex.eKey', 4, $this->Translate('Right Ring Finger'), '', -1);
            IPS_SetVariableProfileAssociation('FingerIndex.eKey', 5, $this->Translate('Right Little Finger'), '', -1);
        }

    }

    public function GetConfigurationForm(): string
    {
        $data = json_decode(file_get_contents(__DIR__ . '/form.json'));
        $data->elements[0]->items[1]->popup->items[1]->caption = $this->getHookURL($this->ReadAttributeString('NotificationKey'));
        if ($this->HasActiveParent()) {
            $data->actions[0]->values = $this->getFunctionWebHooks();
        }
        return json_encode($data);
    }
    public function AddFunctionWebHook(string $functionName, string $locationName, string $action): void
    {
        $key = $this->generateRandomString(32);

        $result = json_decode($this->SendDataToParent(json_encode([
            'DataID'   => '{419A845D-6534-1724-989C-948EB2366D38}',
            'Endpoint' => sprintf('/systems/%s/function-webhooks', $this->ReadPropertyString('SystemId')),
            'Payload'  => json_encode($this->getCreatePayload($functionName, $locationName, $key))
        ])));

        $webHooks = json_decode($this->ReadAttributeString('WebHooks'), true);
        $webHooks[$result->functionWebhookId] = [
            'action' => json_decode($action, true),
            'key'    => $key,
        ];
        $this->WriteAttributeString('WebHooks', json_encode($webHooks));

        $this->UpdateFormField('FunctionWebHooks', 'values', json_encode($this->getFunctionWebHooks()));
    }

    public function EditFunctionWebHook(string $functionWebhookId, string $functionName, string $locationName, string $action): void
    {
        $payload = [
            'locationName' => $locationName,
            'functionName' => $functionName,
        ];

        $this->SendDataToParent(json_encode([
            'DataID'   => '{419A845D-6534-1724-989C-948EB2366D38}',
            'Endpoint' => sprintf('/systems/%s/function-webhooks/%s/name', $this->ReadPropertyString('SystemId'), $functionWebhookId),
            'Method'   => 'PATCH',
            'Payload'  => json_encode($payload)
        ]));

        $webHooks = json_decode($this->ReadAttributeString('WebHooks'), true);
        $webHooks[$functionWebhookId]['action'] = json_decode($action, true);
        $this->WriteAttributeString('WebHooks', json_encode($webHooks));

        $this->UpdateFormField('FunctionWebHooks', 'values', json_encode($this->getFunctionWebHooks()));
    }

    public function DeleteFunctionWebHook(string $functionWebhookId): void
    {
        $this->SendDataToParent(json_encode([
            'DataID'   => '{419A845D-6534-1724-989C-948EB2366D38}',
            'Endpoint' => sprintf('/systems/%s/function-webhooks/%s', $this->ReadPropertyString('SystemId'), $functionWebhookId),
            'Method'   => 'DELETE'
        ]));

        $webHooks = json_decode($this->ReadAttributeString('WebHooks'), true);
        unset($webHooks[$functionWebhookId]);
        $this->WriteAttributeString('WebHooks', json_encode($webHooks));

        $this->UpdateFormField('FunctionWebHooks', 'values', json_encode($this->getFunctionWebHooks()));
    }

    protected function ProcessHookData(): void
    {
        $this->SendDebug('Trigger', print_r($_GET, true), 0);
        $this->SendDebug('Trigger', print_r($_SERVER, true), 0);

        if (!isset($_GET['key'])) {
            return;
        }

        // eKey is appending some magic path, but not properly though only at the end -> /api/notification/finger
        if ($this->ReadAttributeString('NotificationKey') == str_replace("/api/notification/finger", "", $_GET['key'])) {
            $input = file_get_contents("php://input");
            $this->SendDebug('Notification', print_r($input, true), 0);
            if (!$input) {
                echo "No data!";
            }
            else {
                $index = 0;

                $keyToName = function($key) {
                    switch ($key) {
                        case 'type':
                            return $this->Translate('Input Type');
                        case 'result':
                            return $this->Translate('Result');
                        case 'detail':
                            return $this->Translate('Result Detail');
                        case 'time':
                            return $this->Translate('Date&Time');
                        case 'ctlDevId':
                            return $this->Translate('Executing Device');
                        case 'acqDevId':
                            return $this->Translate('Acquiring Device');
                        case 'userId':
                            return $this->Translate('User ID');
                        case 'fingerIndex':
                            return $this->Translate('Finger Index');
                        default:
                            return $key;
                    }
                };

                $keyToProfile = function($key) {
                    switch ($key) {
                        case 'type':
                            return 'InputTypeValues.eKey';
                        case 'result':
                            return 'ResultValues.eKey';
                        case 'detail':
                            return 'ResultDetailValues.eKey';
                        case 'fingerIndex':
                            return 'FingerIndex.eKey';
                        default:
                            return '';
                    }
                };

                $userIdToName = function ($userId) {
                    $mapping = $this->ReadPropertyString('NotificationMapping');
                    if ($mapping) {
                        $mapping = json_decode(base64_decode($mapping));
                        if ($mapping) {
                            if (isset($mapping->users)) {
                                foreach ($mapping->users as $user) {
                                    if (isset($user->userId) && isset($user->userName)) {
                                        if ($user->userId == $userId) {
                                            return $user->userName;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    return $userId;
                };

                $deviceIdToName = function ($deviceId) {
                    $mapping = $this->ReadPropertyString('NotificationMapping');
                    if ($mapping) {
                        $mapping = json_decode(base64_decode($mapping));
                        if ($mapping) {
                            if (isset($mapping->devices)) {
                                foreach ($mapping->devices as $device) {
                                    if (isset($device->deviceId) && isset($device->deviceName)) {
                                        if ($device->deviceId == $deviceId) {
                                            return $device->deviceName;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    return $deviceId;
                };

                $processItem = function($key, $value, $prefix = '') use(&$index, $keyToName, $keyToProfile, $userIdToName, $deviceIdToName) {
                    if (is_int($value)) {
                        $this->RegisterVariableInteger($prefix . $key, $keyToName($key), $keyToProfile($key), $index);
                        $this->SetValue($prefix . $key, $value);
                    }
                    else if (is_string($value)) {
                        $this->RegisterVariableString($prefix . $key, $keyToName($key), $keyToProfile($key), $index);
                        if ($key == 'userId') {
                            $this->SetValue($prefix . $key, $userIdToName($value));
                        }
                        else if ($key == 'ctlDevId' || $key == 'acqDevId') {
                            $this->SetValue($prefix . $key, $deviceIdToName($value));
                        }
                        else {
                            $this->SetValue($prefix . $key, $value);
                        }
                    }
                    $index++;
                };

                foreach (json_decode($input, true) as $key => $value) {
                    if ($key == 'time' && is_string($value)) {
                        $this->RegisterVariableInteger($key, $keyToName($key), '~UnixTimestamp', $index);
                        $this->SetValue($key, strtotime($value));
                        $index++;
                    }
                    else if ($key == 'params' && is_array($value)) {
                        foreach ($value as $subkey => $subvalue) {
                            $processItem($subkey, $subvalue, $key . '_');
                        }
                    }
                    else {
                        $processItem($key, $value);
                    }
                }

                echo "OK";
            }
        }

        $webHooks = json_decode($this->ReadAttributeString('WebHooks'), true);
        foreach ($webHooks as $webHook) {
            if ($webHook['key'] === $_GET['key']) {
                $this->SendDebug('Execute', $webHook['action']['actionID'] . '=> ' . json_encode($webHook['action']['parameters']), 0);
                IPS_RunAction($webHook['action']['actionID'], $webHook['action']['parameters']);
            }
        }
    }

    private function getFunctionWebHooks(): array
    {
        $webhooks = json_decode($this->SendDataToParent(json_encode([
            'DataID'   => '{419A845D-6534-1724-989C-948EB2366D38}',
            'Endpoint' => sprintf('/systems/%s/function-webhooks', $this->ReadPropertyString('SystemId')),
            'Payload'  => ''
        ])));

        $webHooks = json_decode($this->ReadAttributeString('WebHooks'), true);

        $hasModification = function ($value)
        {
            return $value && $value != 'None';
        };

        $fwh = [];
        foreach ($webhooks as $webhook) {
            $this->SendDebug('FunctionWebHook', json_encode($webhook), 0);

            $fwh[] = [
                'functionWebhookId' => $webhook->functionWebhookId,
                'functionName'      => $webhook->functionName,
                'locationName'      => $hasModification($webhook->modificationState) ? ('*** ' . $webhook->modificationState) : $webhook->locationName,
                'functionAction'    => isset($webHooks[$webhook->functionWebhookId]) ? json_encode($webHooks[$webhook->functionWebhookId]['action']) : '',
                'editable'          => !$hasModification($webhook->modificationState),
                'deletable'         => !$hasModification($webhook->modificationState),
            ];
        }

        return $fwh;
    }

    private function getHookURL($key): string
    {
        $connectionID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $connection = IPS_GetProperty($connectionID, 'Connection');
        $ip = IPS_GetProperty($connectionID, 'IP');
        switch ($connection) {
            case 'local':
                return sprintf('http://%s:3777/hook/ekey_bionyx/%d?key=%s', $ip, $this->InstanceID, $key);
            case 'connect':
                $ccid = IPS_GetInstanceListByModuleID('{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}')[0];
                return sprintf('%s/hook/ekey_bionyx/%d?key=%s', CC_GetConnectURL($ccid), $this->InstanceID, $key);
        }
        return '';
    }

    private function getCreatePayload($functionName, $locationName, $key): array
    {
        return [
            'definition' => [
                'method'                => 'Get',
                'url'                   => $this->getHookURL($key),
                'body'                  => null,
                'securityLevel'         => 'TlsWithCACheck',
                'timeout'               => null,
                'additionalHttpHeaders' => null,
                'authentication'        => [
                    'apiAuthenticationType' => 'None',
                    'expiresIn'             => null
                ]
            ],
            'integrationName' => 'Symcon',
            'locationName'    => $locationName,
            'functionName'    => $functionName,
        ];
    }

    private function generateRandomString(int $length): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
