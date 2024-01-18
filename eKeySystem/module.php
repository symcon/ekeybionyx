<?php

declare(strict_types=1);
class eKeySystem extends IPSModuleStrict
{
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("SystemId", "");

        $this->RegisterAttributeString("WebHooks", "{}");

        //Connect to available splitter or create a new one
        $this->ConnectParent('{12581FD4-FA80-AE58-7EF7-74733949B98B}');

        //Register Hook for commands
        $this->RegisterHook("ekey_bionyx/" . $this->InstanceID);
    }

    private function getFunctionWebHooks(): array
    {
        $webhooks = json_decode($this->SendDataToParent(json_encode([
            'DataID'   => '{419A845D-6534-1724-989C-948EB2366D38}',
            'Endpoint' => sprintf('/systems/%s/function-webhooks', $this->ReadPropertyString('SystemId')),
            'Payload'  => ''
        ])));

        $webHooks = json_decode($this->ReadAttributeString("WebHooks"), true);

        $hasModification = function($value) {
            return $value && $value != "None";
        };

        $fwh = [];
        foreach ($webhooks as $webhook) {
            $this->SendDebug('FunctionWebHook', json_encode($webhook), 0);

            $fwh[] = [
                'functionWebhookId' => $webhook->functionWebhookId,
                'functionName'      => $webhook->functionName,
                'locationName'      => $hasModification($webhook->modificationState) ? ('*** ' . $webhook->modificationState) : $webhook->locationName,
                'functionAction'    => isset($webHooks[$webhook->functionWebhookId]) ? json_encode($webHooks[$webhook->functionWebhookId]["action"]) : "",
                'editable'          => !$hasModification($webhook->modificationState),
                'deletable'         => !$hasModification($webhook->modificationState),
            ];
        }

        return $fwh;
    }

    public function GetConfigurationForm(): string
    {
        $data = json_decode(file_get_contents(__DIR__ . '/form.json'));
        if ($this->HasActiveParent()) {
            $data->actions[0]->values = $this->getFunctionWebHooks();
        }
        return json_encode($data);
    }

    protected function ProcessHookData(): void
    {
        $this->SendDebug("Trigger", print_r($_GET, true), 0);
        $this->SendDebug("Trigger", print_r($_SERVER, true), 0);

        if (!isset($_GET['key'])) {
            return;
        }

        $webHooks = json_decode($this->ReadAttributeString("WebHooks"), true);
        foreach ($webHooks as $webHook) {
            if ($webHook['key'] === $_GET['key']) {
                $this->SendDebug("Execute", $webHook['action']['actionID'] . "=> " .json_encode($webHook['action']['parameters']), 0);
                IPS_RunAction($webHook['action']['actionID'], $webHook['action']['parameters']);
            }
        }
    }

    private function getCreatePayload($functionName, $locationName, $key): array
    {
        $connectionID = IPS_GetInstance($this->InstanceID)["ConnectionID"];
        $connection = IPS_GetProperty($connectionID, "Connection");
        $ip = IPS_GetProperty($connectionID, "IP");

        $url = "";
        switch($connection) {
            case "local":
                $url = sprintf("http://%s:3777/hook/ekey_bionyx/%d?key=%s", $ip, $this->InstanceID, $key);
                break;
            case "connect":
                $ccid = IPS_GetInstanceListByModuleID("{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}")[0];
                $url = sprintf("%s/hook/ekey_bionyx/%d?key=%s", CC_GetConnectURL($ccid), $this->InstanceID, $key);
                break;
        }

        return [
            "definition" => [
                "method" => "Get",
                "url" => $url,
                "body" => null,
                "securityLevel" => "TlsWithCACheck",
                "timeout" => null,
                "additionalHttpHeaders" => null,
                "authentication" => [
                    "apiAuthenticationType" => "None",
                    "expiresIn" => null
                ]
            ],
            "integrationName" => "Symcon",
            "locationName" => $locationName,
            "functionName" => $functionName,
        ];
    }

    private function generateRandomString(int $length): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    public function AddFunctionWebHook(string $functionName, string $locationName, string $action): void
    {
        $key = $this->generateRandomString(32);

        $result = json_decode($this->SendDataToParent(json_encode([
            'DataID'   => '{419A845D-6534-1724-989C-948EB2366D38}',
            'Endpoint' => sprintf('/systems/%s/function-webhooks', $this->ReadPropertyString('SystemId')),
            'Payload'  => json_encode($this->getCreatePayload($functionName, $locationName, $key))
        ])));

        $webHooks = json_decode($this->ReadAttributeString("WebHooks"), true);
        $webHooks[$result->functionWebhookId] = [
            "action" => json_decode($action, true),
            "key"    => $key,
        ];
        $this->WriteAttributeString("WebHooks", json_encode($webHooks));

        $this->UpdateFormField("FunctionWebHooks", "values", json_encode($this->getFunctionWebHooks()));
    }

    public function EditFunctionWebHook(string $functionWebhookId, string $functionName, string $locationName, string $action): void
    {
        $payload = [
            "locationName" => $locationName,
            "functionName" => $functionName,
        ];

        $this->SendDataToParent(json_encode([
            'DataID'   => '{419A845D-6534-1724-989C-948EB2366D38}',
            'Endpoint' => sprintf('/systems/%s/function-webhooks/%s/name', $this->ReadPropertyString('SystemId'), $functionWebhookId),
            'Method'   => 'PATCH',
            'Payload'  => json_encode($payload)
        ]));

        $webHooks = json_decode($this->ReadAttributeString("WebHooks"), true);
        $webHooks[$functionWebhookId]["action"] = json_decode($action, true);
        $this->WriteAttributeString("WebHooks", json_encode($webHooks));

        $this->UpdateFormField("FunctionWebHooks", "values", json_encode($this->getFunctionWebHooks()));
    }

    public function DeleteFunctionWebHook(string $functionWebhookId): void
    {
        $this->SendDataToParent(json_encode([
            'DataID'   => '{419A845D-6534-1724-989C-948EB2366D38}',
            'Endpoint' => sprintf('/systems/%s/function-webhooks/%s', $this->ReadPropertyString('SystemId'), $functionWebhookId),
            'Method'   => 'DELETE'
        ]));

        $webHooks = json_decode($this->ReadAttributeString("WebHooks"), true);
        unset($webHooks[$functionWebhookId]);
        $this->WriteAttributeString("WebHooks", json_encode($webHooks));

        $this->UpdateFormField("FunctionWebHooks", "values", json_encode($this->getFunctionWebHooks()));
    }
}
