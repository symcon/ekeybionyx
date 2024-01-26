<?php

declare(strict_types=1);
class ekeyConfigurator extends IPSModuleStrict
{
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        //Connect to available splitter or create a new one
        $this->ConnectParent('{12581FD4-FA80-AE58-7EF7-74733949B98B}');
    }

    public function GetConfigurationForm(): string
    {
        $data = json_decode(file_get_contents(__DIR__ . '/form.json'));

        if ($this->HasActiveParent()) {
            $systems = json_decode($this->SendDataToParent(json_encode([
                'DataID'   => '{419A845D-6534-1724-989C-948EB2366D38}',
                'Endpoint' => '/systems',
                'Payload'  => ''
            ])));

            $physicalChildren = $this->getPhysicalChildren();

            foreach ($systems as $system) {
                $this->SendDebug('System', json_encode($systems), 0);

                $instanceID = $this->searchDevice($system->systemId);
                $physicalChildren = array_diff($physicalChildren, [$instanceID]);

                $data->actions[0]->values[] = [
                    'systemId'      => $system->systemId,
                    'systemName'    => $system->systemName,
                    'info'          => sprintf('WebHooks: %d/%d', $system->functionWebhookQuotas->used, $system->functionWebhookQuotas->used + $system->functionWebhookQuotas->free),
                    'instanceID'    => $instanceID,
                    'create'        => [
                        'moduleID'      => '{81DE9D16-04F1-DE04-AC2D-77096E0A405A}',
                        'configuration' => [
                            'SystemId' => $system->systemId
                        ]
                    ],
                ];
            }

            foreach ($physicalChildren as $instanceID) {
                $data->actions[0]->values[] = [
                    'systemId'     => IPS_GetProperty($instanceID, 'SystemId'),
                    'systemName'   => '',
                    'name'         => IPS_GetName($instanceID),
                    'info'         => '',
                    'instanceID'   => $instanceID,
                ];
            }
        }

        return json_encode($data);
    }

    private function getPhysicalChildren(): array
    {
        $connectionID = IPS_GetInstance($this->InstanceID);
        $ids = IPS_GetInstanceListByModuleID('{81DE9D16-04F1-DE04-AC2D-77096E0A405A}');
        $result = [];
        foreach ($ids as $id) {
            $i = IPS_GetInstance($id);
            if ($i['ConnectionID'] == $connectionID['ConnectionID']) {
                $result[] = $id;
            }
        }
        return $result;
    }
    private function searchDevice($systemId): int
    {
        $connectionID = IPS_GetInstance($this->InstanceID);
        $ids = IPS_GetInstanceListByModuleID('{81DE9D16-04F1-DE04-AC2D-77096E0A405A}');
        foreach ($ids as $id) {
            $i = IPS_GetInstance($id);
            if ($i['ConnectionID'] == $connectionID['ConnectionID']) {
                if (IPS_GetProperty($id, 'SystemId') == $systemId) {
                    return $id;
                }
            }
        }
        return 0;
    }
}
