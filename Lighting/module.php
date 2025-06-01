<?php

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpMissingReturnTypeInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class WiZLighting extends IPSModule
{
    //Helper
    use Control;

    //Constants
    private const MODULE_PREFIX = 'WIZ';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ### Properties

        $this->RegisterPropertyBoolean('Active', true);
        $this->RegisterPropertyString('Lighting', '[]');
        $this->RegisterPropertyInteger('StatusUpdate', 300);
        $this->RegisterPropertyBoolean('UseBrightness', true);
        $this->RegisterPropertyBoolean('UseTemperature', true);
        $this->RegisterPropertyBoolean('UseColor', true);
        $this->RegisterPropertyBoolean('UseScene', true);
        $this->RegisterPropertyBoolean('UseStatusUpdate', true);

        ### Variables

        //Power
        $id = @$this->GetIDForIdent('Power');
        $this->RegisterVariableBoolean('Power', $this->Translate('Light'), '~Switch', 10);
        $this->EnableAction('Power');
        if (!$id) {
            IPS_SetIcon(@$this->GetIDForIdent('Power'), 'Bulb');
        }

        //Brightness
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.Brightness';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileValues($profile, 0, 100, 1);
        IPS_SetVariableProfileText($profile, '', '%');
        IPS_SetVariableProfileIcon($profile, 'Sun');
        $this->RegisterVariableInteger('Brightness', $this->Translate('Brightness'), $profile, 20);
        $this->EnableAction('Brightness');

        //Temperature
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.Temperature';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileValues($profile, 2200, 6500, 1);
        IPS_SetVariableProfileText($profile, '', '°K');
        IPS_SetVariableProfileIcon($profile, 'Temperature');
        $this->RegisterVariableInteger('Temperature', $this->Translate('Temperature'), $profile, 30);
        $this->EnableAction('Temperature');

        //Color
        $id = @$this->GetIDForIdent('Color');
        $this->RegisterVariableInteger('Color', $this->Translate('Color'), '~HexColor', 40);
        $this->EnableAction('Color');
        if (!$id) {
            IPS_SetIcon(@$this->GetIDForIdent('Color'), 'Paintbrush');
        }

        //Scenes
        $scenes = [
            0   => 'none',
            1   => 'ocean',
            2   => 'romance',
            3   => 'sunset',
            4   => 'party',
            5   => 'fireplace',
            6   => 'cozy',
            7   => 'forest',
            8   => 'pastel',
            9   => 'wake',
            10  => 'bedtime',
            11  => 'warm',
            12  => 'daylight',
            13  => 'cool',
            14  => 'night',
            15  => 'focus',
            16  => 'relax',
            17  => 'true',
            18  => 'tv',
            19  => 'plant',
            20  => 'spring',
            21  => 'summer',
            22  => 'fall',
            23  => 'deepdive',
            24  => 'jungle',
            25  => 'mojito',
            26  => 'club',
            27  => 'christmas',
            28  => 'halloween',
            29  => 'candlelight',
            30  => 'golden',
            31  => 'pulse',
            32  => 'steampunk',
            35  => 'lightalarm',
            36  => 'snowysky',
            205 => 'unknown'
        ];
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.Scenes';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        foreach ($scenes as $key => $name) {
            IPS_SetVariableProfileAssociation($profile, $key, $this->Translate($name), '', -1);
        }
        $this->RegisterVariableInteger('Scene', $this->Translate('Scene'), $profile, 50);
        $this->EnableAction('Scene');
        if (!$id) {
            IPS_SetIcon(@$this->GetIDForIdent('Scene'), 'Menu');
        }

        //Update status
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.StatusUpdate';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileAssociation($profile, 0, $this->Translate('Update'), 'Repeat', -1);
        $this->RegisterVariableInteger('StatusUpdate', 'Status', $profile, 60);
        $this->EnableAction('StatusUpdate');

        ### Timer
        $this->RegisterTimer('StatusUpdate', 0, self::MODULE_PREFIX . '_UpdateStatus(' . $this->InstanceID . ');');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

        //Delete profiles
        $profiles = ['Brightness', 'Temperature', 'Scenes', 'StatusUpdate'];
        foreach ($profiles as $profile) {
            $this->DeleteProfile($profile);
        }
    }

    public function ApplyChanges()
    {
        //Wait until Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();

        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        IPS_SetHidden($this->GetIDForIdent('Brightness'), !$this->ReadPropertyBoolean('UseBrightness'));
        IPS_SetHidden($this->GetIDForIdent('Temperature'), !$this->ReadPropertyBoolean('UseTemperature'));
        IPS_SetHidden($this->GetIDForIdent('Color'), !$this->ReadPropertyBoolean('UseColor'));
        IPS_SetHidden($this->GetIDForIdent('Scene'), !$this->ReadPropertyBoolean('UseScene'));
        IPS_SetHidden($this->GetIDForIdent('StatusUpdate'), !$this->ReadPropertyBoolean('UseStatusUpdate'));

        $this->CheckConfiguration();
        $this->SetTimerInterval('StatusUpdate', $this->ReadPropertyInteger('StatusUpdate') * 1000);
        $this->UpdateStatus();
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

    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'Power':
                $this->TogglePower($Value);
                break;

            case 'Brightness':
                $this->SetBrightness($Value);
                break;

            case 'Temperature':
                $this->SetColorTemperature($Value);
                break;

            case 'Color':
                $this->SetColor($Value);
                break;

            case 'Scene':
                $this->SetScene($Value);
                break;

            case 'StatusUpdate':
                $this->UpdateStatus();
                break;

        }
    }

    ##### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function CheckConfiguration(): void
    {
        $status = 102;
        $lighting = json_decode($this->ReadPropertyString('Lighting'), true);
        foreach ($lighting as $light) {
            if (!$light['Use']) {
                continue;
            }
            if ($light['IPAddress'] == '') {
                $status = 200;
            } else {
                if (!$this->IsIpValid($light['IPAddress'])) {
                    $status = 201;
                }
            }
        }
        if (!$this->ReadPropertyBoolean('Active')) {
            $status = 104;
        }
        $this->SetStatus($status);
    }

    private function DeleteProfile(string $ProfileName): void
    {
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $ProfileName;
        if (@IPS_VariableProfileExists($profile)) {
            IPS_DeleteVariableProfile($profile);
        }
    }
}