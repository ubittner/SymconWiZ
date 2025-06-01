<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpRedundantMethodOverrideInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

class WiZDiscovery extends IPSModule
{
    //Constants

    private const WIZ_LIGHTING_GUID = '{C764FBFD-2A5A-32BF-1987-CF28B5C82047}';
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
    }

    public function ApplyChanges(): void
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        if ($Message == IPS_KERNELSTARTED) {
            $this->KernelReady();
        }
    }

    public function GetConfigurationForm(): string
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $devices = $this->DiscoverLightingDevices();
        if (empty($devices)) {
            $formData['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'caption' => 'No devices found! Please try again.'
                    ]]
                ]
            ];
        } else {
            $values = [];
            foreach ($devices as $device) {
                $instanceID = $this->GetLightingInstance($device['IPAddress']);
                $addValue = [
                    'IPAddress'  => $device['IPAddress'],
                    'MACAddress' => $device['MACAddress'],
                    'Port'       => $device['Port'],
                    'HomeID'     => $device['HomeID'],
                    'RoomID'     => $device['RoomID'],
                    'GroupID'    => $device['GroupID'],
                    'Model'      => $device['Model'],
                    'Firmware'   => $device['Firmware'],
                    'instanceID' => $instanceID
                ];

                $lighting = json_encode([
                    'Use'                   => true,
                    'IPAddress'             => (string) $device['IPAddress'],
                    'MACAddress'            => (string) $device['MACAddress'],
                    'Port'                  => (int) $device['Port'],
                    'InternalDesignation'   => $device['Model'] . ' ' . $device['Firmware'],
                    'HomeID'                => (string) $device['HomeID'],
                    'RoomID'                => (string) $device['RoomID'],
                    'GroupID'               => (string) $device['GroupID']
                ]);

                $addValue['create'] = [
                    [
                        'moduleID'      => self::WIZ_LIGHTING_GUID,
                        'name'          => $this->Translate('WiZ Lighting') . ' (' . $device['IPAddress'] . ')',
                        'configuration' => [
                            'Lighting' => "[$lighting]"
                        ]
                    ]
                ];
                $values[] = $addValue;
            }
            $formData['actions'][0]['values'] = $values;
        }
        return json_encode($formData);
    }

    public function DiscoverLightingDevices(): array
    {
        //Open UDP-Broadcast socket
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec'=>2, 'usec'=>0]);

        //UDP-Broadcast-Notification: WiZ API "getSystemConfig"
        $message = json_encode([
            'method' => 'getSystemConfig',
            'params' => new stdClass()
        ]);

        //Send Broadcast
        socket_sendto($socket, $message, strlen($message), 0, '255.255.255.255', 38899);

        $devices = [];
        $startTime = time();

        //Response (max. 3 seconds)
        while (time() - $startTime < 3) {
            $buf = '';
            $from = '';
            $port = 0;
            $bytes = @socket_recvfrom($socket, $buf, 512, 0, $from, $port);
            if ($bytes !== false && !empty($buf)) {
                $data = json_decode($buf, true);
                if (isset($data['result'])) {
                    $device = [
                        'IPAddress'     => $from,
                        'MACAddress'    => $data['result']['mac'] ?? 'N/A',
                        'Port'          => $port,
                        'HomeID'        => $data['result']['homeId'] ?? 'N/A',
                        'RoomID'        => $data['result']['roomId'] ?? 'N/A',
                        'GroupID'       => $data['result']['groupId'] ?? 'N/A',
                        'Model'         => $data['result']['moduleName'] ?? 'N/A',
                        'Firmware'      => $data['result']['fwVersion'] ?? 'N/A',
                    ];
                    $devices[] = $device;
                }
            }
        }
        return $devices;
    }

    ##### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function GetLightingInstance(string $IPAddress): int
    {
        $instances = IPS_GetInstanceListByModuleID(self::WIZ_LIGHTING_GUID);
        foreach ($instances as $instance) {
            $devices = json_decode(IPS_GetProperty($instance, 'Lighting'), true);
            foreach ($devices as $device) {
                if ($device['IPAddress'] == $IPAddress) {
                    return $instance;
                }
            }
        }
        return 0;
    }
}