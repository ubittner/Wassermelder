<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Wassermelder/tree/main/Wassermelder
 */

/** @noinspection PhpUnused */

declare(strict_types=1);

trait WM_waterDetector
{
    public function DetermineWaterDetectors(): void
    {
        $instanceIDs = @IPS_GetInstanceListByModuleID(self::HOMEMATIC_DEVICE_GUID);
        $variables = [];
        foreach ($instanceIDs as $instanceID) {
            $childrenIDs = @IPS_GetChildrenIDs($instanceID);
            foreach ($childrenIDs as $childrenID) {
                $object = @IPS_GetObject($childrenID);
                if ($object['ObjectIdent'] == 'ALARMSTATE') {
                    // Check for variable
                    if ($object['ObjectType'] == 2) {
                        $name = strstr(@IPS_GetName($instanceID), ':', true);
                        if ($name == false) {
                            $name = @IPS_GetName($instanceID);
                        }
                        $type = IPS_GetVariable($childrenID)['VariableType'];
                        $triggerValue = 'true';
                        if ($type == 1) {
                            $triggerValue = '1';
                        }
                        array_push($variables, [
                            'Use'          => true,
                            'Name'         => $name,
                            'ID'           => $childrenID,
                            'TriggerValue' => $triggerValue]);
                    }
                }
            }
        }
        // Get already listed variables
        $listedVariables = json_decode($this->ReadPropertyString('WaterDetectors'), true);
        // Add new variables
        if (!empty($listedVariables)) {
            $addVariables = array_diff(array_column($variables, 'ID'), array_column($listedVariables, 'ID'));
            foreach ($addVariables as $addVariable) {
                $name = strstr(@IPS_GetName(@IPS_GetParent($addVariable)), ':', true);
                $type = IPS_GetVariable($addVariable)['VariableType'];
                $triggerValue = 'true';
                if ($type == 1) {
                    $triggerValue = '1';
                }
                array_push($listedVariables, [
                    'Use'          => true,
                    'Name'         => $name,
                    'ID'           => $addVariable,
                    'TriggerValue' => $triggerValue]);
            }
        } else {
            $listedVariables = $variables;
        }
        // Sort variables by name
        array_multisort(array_column($listedVariables, 'Name'), SORT_ASC, $listedVariables);
        $listedVariables = array_values($listedVariables);
        // Update variable list
        $value = json_encode($listedVariables);
        @IPS_SetProperty($this->InstanceID, 'WaterDetectors', $value);
        if (@IPS_HasChanges($this->InstanceID)) {
            @IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Wassermelder wurden automatisch ermittelt!';
    }

    public function UpdateState(): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if (!$this->GetValue('WaterDetection')) {
            return;
        }
        $waterSensors = json_decode($this->ReadPropertyString('WaterDetectors'), true);
        if (empty($waterSensors)) {
            return;
        }
        // Sort variables by name
        array_multisort(array_column($waterSensors, 'Name'), SORT_ASC, $waterSensors);
        $state = false;
        $sensorStateList = [];
        $timestamp = (string) date('d.m.Y, H:i:s');
        $string = "<table style='width: 100%; border-collapse: collapse;'>";
        $string .= '<tr><td><b>Status</b></td><td><b>Name</b></td><td><b>Letzte Statusprüfung</b></td></tr>';
        foreach ($waterSensors as $waterSensor) {
            if (!$waterSensor['Use']) {
                continue;
            }
            $id = $waterSensor['ID'];
            if ($id == 0 || @!IPS_ObjectExists($id)) {
                continue;
            }
            $unicode = json_decode('"\u2705"'); # white_check_mark
            $actualValue = boolval(GetValue($id));
            $triggerValue = $waterSensor['TriggerValue'];
            switch ($triggerValue) {
                case '0':
                case 'false':
                    $triggerValue = false;
                    break;

                case '1':
                case 'true':
                    $triggerValue = true;
                    break;

                default:
                    $triggerValue = boolval($triggerValue);

            }
            if ($actualValue == $triggerValue) {
                $unicode = json_decode('"\ud83d\udca7"'); # droplet
                $state = true;
            }
            $string .= '<tr><td>' . $unicode . '</td><td>' . $waterSensor['Name'] . '</td><td>' . $timestamp . '</td></tr>';
            array_push($sensorStateList, [
                'unicode'   => $unicode,
                'name'      => $waterSensor['Name'],
                'timestamp' => $timestamp]);
        }
        $string .= '</table>';
        $this->SetValue('SensorList', $string);
        $this->SetBuffer('SensorStateList', json_encode($sensorStateList));
        $this->SetValue('State', $state);
        if (!$state) {
            $this->SetValue('AlertingSensor', '');
        }
    }

    public function CheckTriggerVariable(int $SenderID, bool $ValueChanged): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if (!$this->GetValue('WaterDetection')) {
            return;
        }
        $waterSensors = json_decode($this->ReadPropertyString('WaterDetectors'), true);
        if (empty($waterSensors)) {
            return;
        }
        $lastState = $this->GetValue('State');
        $this->UpdateState();
        $actualState = $this->GetValue('State');
        $key = array_search($SenderID, array_column($waterSensors, 'ID'));
        if (!is_int($key)) {
            return;
        }
        if (!$waterSensors[$key]['Use']) {
            return;
        }
        $triggerValue = $waterSensors[$key]['TriggerValue'];
        switch ($triggerValue) {
            case '0':
            case 'false':
                $triggerValue = false;
                break;

            case '1':
            case 'true':
                $triggerValue = true;
                break;

            default:
                $triggerValue = boolval($triggerValue);

        }
        $sensorStateList = "Wassermelder: \n\n";
        $sensors = json_decode($this->GetBuffer('SensorStateList'));
        if (!empty($sensors)) {
            foreach ($sensors as $sensor) {
                $sensorStateList .= $sensor->unicode . ' ' . $sensor->name . "\n";
            }
        }
        $title = 'Wassermelder';
        $location = $this->ReadPropertyString('LocationDesignation');
        $timestamp = (string) date('d.m.Y, H:i:s');
        $actualValue = boolval(GetValue($SenderID));
        $name = $waterSensors[$key]['Name'];
        // Water detected
        if ($ValueChanged && ($actualValue == $triggerValue)) {
            $this->SetValue('AlertingSensor', $name);
            if (!$this->ReadPropertyBoolean('UseNotification')) {
                return;
            }
            if (!$this->ReadPropertyBoolean('UseStateWaterDetected')) {
                return;
            }
            // WebFront Notification
            $unicode = json_decode('"\ud83d\udca7"'); # droplet
            $text = $location . "\n" . $unicode . " Wasser erkannt\n" . $name . "\n" . $timestamp;
            $this->SendWebFrontNotification($title, $text, '');
            // WebFront Push Notification
            $text = "\n" . $location . "\n" . $unicode . " Wasser erkannt\n" . $name . "\n" . $timestamp;
            $this->SendWebFrontPushNotification($title, $text, 'alarm');
            // Mailer
            $subject = 'Wassermelder ' . $location . ' - ' . $unicode . ' Wasser erkannt, ' . $name;
            $text = "Status:\n\n" . $timestamp . ', Wassermelder ' . $location . ' - ' . $unicode . ' Wasser erkannt, ' . $name . "\n\n";
            $text .= $sensorStateList;
            $this->SendMailNotification($subject, $text);
            // NeXXt Mobile SMS
            $text = $title . "\n" . $location . "\n" . "Wasser erkannt\n" . $name . "\n" . $timestamp;
            $this->SendNeXXtMobileSMS($text);
            // Sipgate SMS
            $text = $title . "\n" . $location . "\n" . $unicode . " Wasser erkannt\n" . $name . "\n" . $timestamp;
            $this->SendSipgateSMS($text);
            // Telegram Message
            $text = $title . "\n" . $location . "\n" . $unicode . " Wasser erkannt\n" . $name . "\n" . $timestamp;
            $this->SendTelegramMessage($text);
        }
        // No water detected
        if ($ValueChanged && ($actualValue != $triggerValue)) {
            // All water sensors are ok
            if (($actualState != $lastState) && $actualState == false) {
                if (!$this->ReadPropertyBoolean('UseNotification')) {
                    return;
                }
                if (!$this->ReadPropertyBoolean('UseStateOK')) {
                    return;
                }
                // WebFront Notification
                $unicode = json_decode('"\u2705"'); # white_check_mark
                $text = $location . "\n" . $unicode . " OK\n" . $timestamp;
                $this->SendWebFrontNotification($title, $text, '');
                // WebFront Push Notification
                $text = "\n" . $location . "\n" . $unicode . " OK\n" . $timestamp;
                $this->SendWebFrontPushNotification($title, $text, 'alarm');
                // Mailer
                $subject = 'Wassermelder ' . $location . ' - ' . $unicode . ' OK';
                $text = "Status:\n\n" . $timestamp . ', Wassermelder ' . $location . ' - ' . $unicode . " OK \n\n";
                $text .= $sensorStateList;
                $this->SendMailNotification($subject, $text);
                // NeXXt Mobile SMS
                $text = $title . "\n" . $location . "\nOK\n" . $timestamp;
                $this->SendNeXXtMobileSMS($text);
                // Sipgate SMS
                $text = $title . "\n" . $location . "\n" . $unicode . " OK\n" . $timestamp;
                $this->SendSipgateSMS($text);
                // Telegram Message
                $text = $title . "\n" . $location . "\n" . $unicode . " OK\n" . $timestamp;
                $this->SendTelegramMessage($text);
            }
        }
    }
}