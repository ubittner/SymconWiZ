<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

trait Control
{
    public function GetSystemConfig(): string
    {
        /*
         * mac          MAC address of the device
         * moduleName	Internal module type
         * fwVersion	Firmware version of the bulb
         * bulbType     Type of light (e.g. SHDW = Tunable White + RGB)
         * wifiFwVer	Wi-Fi module firmware
         * homeId	    Your account's home group (local)
         * roomId	    Assigned room in WiZ app
         * groupId	    Group if part of one (e.g. scenes)
         */
        $command = [
            'method' => 'getSystemConfig'
        ];
        return $this->ExecuteCommand(json_encode($command));
    }

    public function ShowSystemConfig(): void
    {
        $configs = $this->GetSystemConfig();
        if (!$this->IsStringJsonEncoded($configs)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Abort, invalid response!'), 0);
        }
        foreach (json_decode($configs, true) as $IPAddress => $config) {
            $config = json_decode($config, true);
            echo $IPAddress . ":\n";
            print_r($config);
            echo "\n";
        }
    }

    public function AdoptSystemConfig(): void
    {
        $configs = $this->GetSystemConfig();
        if (!$this->IsStringJsonEncoded($configs)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Abort, invalid response!'), 0);
        }
        $lighting = json_decode($this->ReadPropertyString('Lighting'), true);
        foreach (json_decode($configs, true) as $IPAddress => $config) {
            $config = json_decode($config, true);
            foreach ($lighting as $key => $light) {
                $use = $light['Use'];
                $ip = $light['IPAddress'];
                $mac = $light['MACAddress'];
                $port = $light['Port'];
                $name = $light['InternalDesignation'];
                $homeID = $light['HomeID'];
                $roomID = $light['RoomID'];
                $groupID = $light['GroupID'];
                if ($light['IPAddress'] == $IPAddress) {
                    if (isset($config['result']['mac'])) {
                        $mac = $config['result']['mac'];
                    }
                    //Home ID
                    if (isset($config['result']['homeId'])) {
                        $homeID = $config['result']['homeId'];
                    }
                    //Room ID

                    if (isset($config['result']['roomId'])) {
                        $roomID = $config['result']['roomId'];
                    }
                    //Group ID
                    if (isset($config['result']['groupId'])) {
                        $groupID = $config['result']['groupId'];
                    }
                }
                $lighting[$key] = [
                    'Use'                 => $use,
                    'IPAddress'           => $ip,
                    'MACAddress'          => $mac,
                    'Port'                => $port,
                    'InternalDesignation' => $name,
                    'HomeID'              => $homeID,
                    'RoomID'              => $roomID,
                    'GroupID'             => $groupID
                ];
            }
        }
        if (isset($lighting)) {
            @IPS_SetProperty($this->InstanceID, 'Lighting', json_encode($lighting));
        }
        if (IPS_HasChanges($this->InstanceID)) {
            IPS_ApplyChanges($this->InstanceID);
        }
    }

    public function TogglePower(bool $State): bool
    {
        /*
         * false = off
         * true = on
         */
        $command = [
            'method' => 'setState',
            'params' => [
                'state' => $State
            ]
        ];
        $this->SetValue('Power', $State);
        $response = $this->ExecuteCommand(json_encode($command));
        $result = $this->IsResponseSuccessful($response);
        if (!$result) {
            $this->SetValue('Power', !$State);
        }
        return $result;
    }

    public function SetBrightness(int $Brightness): bool
    {
        /*
         * Range: 0 to 100 (percent brightness)
         * You can combine brightness with other parameters like sceneId, r/g/b, or temp
         */
        $command = [
            'method' => 'setPilot',
            'params' => [
                'dimming' => $Brightness
            ]
        ];
        $actualBrightness = $this->GetValue('Brightness');
        $this->SetValue('Brightness', $Brightness);
        $response = $this->ExecuteCommand(json_encode($command));
        $result = $this->IsResponseSuccessful($response);
        if (!$result) {
            $this->SetValue('Brightness', $actualBrightness);
        }
        return $result;
    }

    public function SetColorTemperature(int $Temperature): bool
    {
        /*
         * Range: 2200 to 6500 (Kelvin)
         * 2200 = warm white (orange/yellow)
         * 4000 = neutral white
         * 6500 = cool white (bluish)
         */
        $command = [
            'method' => 'setPilot',
            'params' => [
                'temp' => $Temperature
            ]
        ];
        $actualTemperature = $this->GetValue('Temperature');
        $this->SetValue('Temperature', $Temperature);
        $response = $this->ExecuteCommand(json_encode($command));
        $result = $this->IsResponseSuccessful($response);
        if (!$result) {
            $this->SetValue('Temperature', $actualTemperature);
        } else {
            $this->SetValue('Power', true);
            $this->SetValue('Color', 0);
            $this->SetValue('Scene', 0);
        }
        //Optional add dimming (brightness)
        $this->SetBrightness($this->GetValue('Brightness'));
        return $result;
    }

    public function SetColor(int $Color): bool
    {
        //Do not mix r/g/b with temp or sceneId — each command targets a different mode.
        $command = [
            'method' => 'setPilot',
            'params' => [
                'r' => (($Color >> 16) & 0xFF),
                'g' => (($Color >> 8) & 0xFF),
                'b' => ($Color & 0xFF)
            ]
        ];
        $actualColor = $this->GetValue('Color');
        $this->SetValue('Color', $Color);
        $response = $this->ExecuteCommand(json_encode($command));
        $result = $this->IsResponseSuccessful($response);
        if (!$result) {
            $this->SetValue('Color', $actualColor);
        } else {
            $this->SetValue('Power', true);
            $this->SetValue('Temperature', 2200);
            $this->SetValue('Scene', 0);
        }
        //Optional add dimming (brightness)
        $this->SetBrightness($this->GetValue('Brightness'));
        return $result;
    }

    public function SetScene(int $SceneID): bool
    {
        /*
         * "0" : "none",
         * "1" : "ocean",
         * "2" : "romance",
         * "3" : "sunset",
         * "4" : "party",
         * "5" : "fireplace",
         * "6" : "cozy",
         * "7" : "forest",
         * "8" : "pastel",
         * "9" : "wake",
         * "10" : "bedtime",
         * "11" : "warm",
         * "12" : "daylight",
         * "13" : "cool",
         * "14" : "night",
         * "15" : "focus",
         * "16" : "relax",
         * "17" : "true",
         * "18" : "tv",
         * "19" : "plant",
         * "20" : "spring",
         * "21" : "summer",
         * "22" : "fall",
         * "23" : "deepdive",
         * "24" : "jungle",
         * "25" : "mojito",
         * "26" : "club",
         * "27" : "christmas",
         * "28" : "halloween",
         * "29" : "candlelight",
         * "30" : "golden",
         * "31" : "pulse",
         * "32" : "steampunk",
         * "35" : "lightalarm"
         * "36" : "snowy sky",
         * "205": "unknown"
         */
        $command = [
            'method' => 'setPilot',
            'params' => [
                'sceneId' => $SceneID
            ]
        ];
        $actualScene = $this->GetValue('Scene');
        $this->SetValue('Scene', $SceneID);
        $response = $this->ExecuteCommand(json_encode($command));
        $result = $this->IsResponseSuccessful($response);
        if (!$result) {
            $this->SetValue('Scene', $actualScene);
        } else {
            $this->SetValue('Power', true);
            $this->SetValue('Color', 0);
            $this->SetValue('Temperature', 2200);
        }
        return $result;
    }

    public function GetStatus(): string
    {
        $command = [
            'method' => 'getPilot'
        ];
        return $this->ExecuteCommand(json_encode($command));
    }

    public function UpdateStatus(): void
    {
        /*
         * Examples:
         * {"method":"getPilot","env":"pro","result":{"mac":"a1b2c3d4e5f6","rssi":-52,"state":true,"sceneId":0,"r":255,"g":0,"b":65,"c":0,"w":111,"dimming":53}}
         *
         * c = Cold white channel
         * This is the proportion of cold white LEDs (e.g., 6500 K).
         * Value range: 0–255
         *
         * w = Warm white channel
         * This is the proportion of warm white LEDs (e.g., 2200 K).
         * Value range: 0–255
         *
         * {"method":"getPilot","env":"pro","result":{"mac":"a1b2c3d4e5f6","rssi":-52,"state":true,"sceneId":5,"speed":100,"dimming":100}}
         * {"method":"getPilot","env":"pro","result":{"mac":"a1b2c3d4e5f6","rssi":-54,"state":true,"sceneId":0,"temp":2200,"dimming":53}}
         */
        $this->SetTimerInterval('StatusUpdate', $this->ReadPropertyInteger('StatusUpdateInterval') * 1000);
        $responses = $this->GetStatus();
        if (!$this->IsStringJsonEncoded($responses)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Abort, invalid response!'), 0);
            return;
        }
        foreach (json_decode($responses, true) as $response) {
            if (!$this->IsStringJsonEncoded($response)) {
                $this->SendDebug(__FUNCTION__, $this->Translate('Abort, invalid response!'), 0);
                continue;
            }
            $status = json_decode($response, true);
            if (isset($status['result'])) {
                //State
                if (isset($status['result']['state'])) {
                    $this->SetValue('Power', $status['result']['state']);
                }
                //Brightness
                if (isset($status['result']['dimming'])) {
                    $this->SetValue('Brightness', $status['result']['dimming']);
                }
                //Scene
                if (isset($status['result']['sceneId'])) {
                    //{"method":"getPilot","env":"pro","result":{"mac":"d8a0117fe018","rssi":-55,"state":true,"sceneId":205,"dimming":69}}
                    //sceneId = 205 ?
                    $this->SetValue('Scene', $status['result']['sceneId']);
                }
                //Color
                if (isset($status['result']['r']) && isset($status['result']['g']) && isset($status['result']['b'])) {
                    $color = ($status['result']['r'] << 16) | ($status['result']['g'] << 8) | $status['result']['b'];
                    $this->SetValue('Color', $color);
                } else {
                    $this->SetValue('Color', 0);
                }
                //Temperature
                if (isset($status['result']['temp'])) {
                    $this->SetValue('Temperature', $status['result']['temp']);
                } else {
                    $this->SetValue('Temperature', 2200);
                }
                //Cold white
                if (isset($status['result']['c'])) {
                    //Not used at the moment
                    $this->SendDebug(__FUNCTION__, $this->Translate('Cold white') . ': ' . $status['result']['c'], 0);
                }
                //Warm white
                if (isset($status['result']['w'])) {
                    //Not used at the moment
                    $this->SendDebug(__FUNCTION__, $this->Translate('Warm white') . ': ' . $status['result']['w'], 0);
                }
            }
        }
    }

    public function ExecuteCommand(string $Command): string
    {
        if (!$this->ReadPropertyBoolean('Active')) {
            return '[]';
        }
        $result = [];
        $lighting = json_decode($this->ReadPropertyString('Lighting'), true);
        foreach ($lighting as $light) {
            if (!$light['Use']) {
                continue;
            }
            if (!$this->IsIpValid($light['IPAddress'])) {
                $this->SendDebug(__FUNCTION__, $this->Translate('Abort, invalid IP-Address!'), 0);
                continue;
            }
            $this->SendDebug(__FUNCTION__, $this->Translate('Command') . ': ' . $Command, 0);
            if (!$this->IsStringJsonEncoded($Command)) {
                $this->SendDebug(__FUNCTION__, $this->Translate('Abort, invalid command!'), 0);
                continue;
            }
            //Enter semaphore first
            if (!$this->LockSemaphore('ExecuteCommand')) {
                $this->SendDebug(__FUNCTION__, $this->Translate('Abort, Semaphore reached!'), 0);
                $this->UnlockSemaphore('ExecuteCommand');
                continue;
            }
            if (!Sys_Ping($light['IPAddress'], 1000)) {
                $this->SendDebug(__FUNCTION__, $this->Translate('Abort, IP-Address') . ' ' . $light['IPAddress'] . ' ' . $this->Translate('not reachable!'), 0);
                $this->UnlockSemaphore('ExecuteCommand');
                continue;
            }
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if (!$socket) {
                $this->SendDebug(__FUNCTION__, $this->Translate("Abort, couldn't create socket:" . ' ' . socket_strerror(socket_last_error())), 0);
                $this->UnlockSemaphore('ExecuteCommand');
                continue;
            }
            //Send the UDP packet
            socket_sendto($socket, $Command, strlen($Command), 0, $light['IPAddress'], $light['Port']);
            //Optional: Receive response (timeout may be needed)
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 1, 'usec' => 0]);
            if (@socket_recvfrom($socket, $data, 512, 0, $from, $port)) {
                $this->SendDebug(__FUNCTION__, $from . ': ' . $port . ', ' . $this->Translate('Result') . ': ' . $data, 0);
            }
            $result[$light['IPAddress']] = $data;
            socket_close($socket);
            //Leave semaphore
            $this->UnlockSemaphore('ExecuteCommand');
        }
        return json_encode($result);
    }

    ##### Protected

    protected function IsIpValid(string $ip): bool
    {
        $this->SendDebug(__FUNCTION__, $this->Translate('IP-Address') . ': ' . $ip, 0);
        $result = filter_var($ip, FILTER_VALIDATE_IP);
        if (!$result) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Abort, invalid IP-Address!'), 0);
            return false;
        }
        return true;
    }

    protected function IsResponseSuccessful(string $Response): bool
    {
        //{"method":"setState","env":"pro","result":{"success":true}}
        $this->SendDebug(__FUNCTION__, $this->Translate('Response') . ': ' . $Response, 0);
        if (!$this->IsStringJsonEncoded($Response)) {
            $this->SendDebug(__FUNCTION__, $this->Translate('Abort, invalid response!'), 0);
            return false;
        }
        $result = false;
        foreach (json_decode($Response, true) as $IPAddress => $response) {
            $response = json_decode($response, true);
            if (isset($response['result']['success'])) {
                $text = sprintf($this->Translate('Response for %s was successful!'), $IPAddress);
                $this->SendDebug(__FUNCTION__, $text, 0);
                $result = true;
            }
        }
        return $result;
    }

    protected function IsStringJsonEncoded(string $String): bool
    {
        json_decode($String);
        return json_last_error() === JSON_ERROR_NONE;
    }

    protected function LockSemaphore(string $Name): bool
    {
        for ($i = 0; $i < 100; $i++) {
            if (IPS_SemaphoreEnter(self::MODULE_PREFIX . '_' . $this->InstanceID . '_Semaphore_' . $Name, 1)) {
                $this->SendDebug(__FUNCTION__, 'Semaphore locked', 0);
                return true;
            } else {
                IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    protected function UnlockSemaphore(string $Name): void
    {
        IPS_SemaphoreLeave(self::MODULE_PREFIX . '_' . $this->InstanceID . '_Semaphore_' . $Name);
        $this->SendDebug(__FUNCTION__, 'Semaphore unlocked', 0);
    }
}