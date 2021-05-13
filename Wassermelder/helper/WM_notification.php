<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Wassermelder/tree/main/Wassermelder
 */

/** @noinspection PhpUnused */

declare(strict_types=1);

trait WM_notification
{
    public function SendDailyNotification(): void
    {
        $this->SetDailyNotificationTimer();
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->UpdateState();
        if (!$this->ReadPropertyBoolean('UseDailyNotification')) {
            return;
        }
        $title = 'Wassermelder';
        $location = $this->ReadPropertyString('LocationDesignation');
        $timestamp = (string) date('d.m.Y, H:i:s');
        $unicode = json_decode('"\u2705"'); # white_check_mark
        $actualState = $this->GetValue('State');
        $statusDescription = ' OK';
        if ($actualState) {
            $unicode = json_decode('"\ud83d\udca7"'); # droplet
            $statusDescription = ' Wasser erkannt';
        }
        // WebFront Notification
        $text = $timestamp . "\n" . $unicode . $statusDescription;
        $this->SendWebFrontNotification($title, $text, '');
        // WebFront Push Notification
        $text = $timestamp . "\n" . $unicode . $statusDescription;
        $this->SendWebFrontPushNotification($title, $text, 'alarm');
        // Mail
        $subject = 'Wassermelder ' . $location . ' - ' . $unicode . $statusDescription;
        $text = "Status:\n\n" . $timestamp . ', Wassermelder ' . $location . ' - ' . $unicode . $statusDescription . "\n\n";
        $sensorStateList = "Wassermelder: \n\n";
        $sensors = json_decode($this->GetBuffer('SensorStateList'));
        if (!empty($sensors)) {
            foreach ($sensors as $sensor) {
                $sensorStateList .= $sensor->unicode . ' ' . $sensor->name . "\n";
            }
        }
        $text .= $sensorStateList;
        $this->SendMailNotification($subject, $text);
        // NeXXt Mobile SMS
        $text = $title . "\n" . $location . "\n" . $statusDescription . "\n" . $timestamp;
        $this->SendNeXXtMobileSMS($text);
        // Sipgate SMS
        $this->SendSipgateSMS($text);
        // Telegram Message
        $this->SendTelegramMessage($text);
    }

    #################### Protected

    protected function SendWebFrontNotification(string $Title, string $Text, string $Icon): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('WebFrontNotification');
        if ($id == 0 || @!IPS_ObjectExists($id)) {
            return;
        }
        //@WFC_SendNotification($id, $Title, $Text, $Icon, $this->ReadPropertyInteger('DisplayDuration'));
        $scriptText = 'WFC_SendNotification(' . $id . ', "' . $Title . '", "' . $Text . '", "' . $Icon . '", ' . $this->ReadPropertyInteger('DisplayDuration') . ');';
        IPS_RunScriptText($scriptText);
    }

    protected function SendWebFrontPushNotification(string $Title, string $Text, string $Sound, int $TargetID = 0): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('WebFrontPushNotification');
        if ($id == 0 || @!IPS_ObjectExists($id)) {
            return;
        }
        //@WFC_PushNotification($id, $Title, $Text, $Sound, $TargetID);
        $scriptText = 'WFC_PushNotification(' . $id . ', "' . $Title . '", "' . $Text . '", "' . $Sound . '", ' . $TargetID . ');';
        IPS_RunScriptText($scriptText);
    }

    protected function SendMailNotification(string $Subject, string $Text): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('Mailer');
        if ($id == 0 || @!IPS_ObjectExists($id)) {
            return;
        }
        //@MA_SendMessage($id, $Subject, $Text);
        $scriptText = 'MA_SendMessage(' . $id . ', "' . $Subject . '", "' . $Text . '");';
        IPS_RunScriptText($scriptText);
    }

    protected function SendNeXXtMobileSMS(string $Text): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('NeXXtMobile');
        if ($id == 0 || @!IPS_ObjectExists($id)) {
            return;
        }
        //@NMSMS_SendMessage($id, $Text);
        $scriptText = 'NMSMS_SendMessage(' . $id . ', "' . $Text . '");';
        IPS_RunScriptText($scriptText);
    }

    protected function SendSipgateSMS(string $Text): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('Sipgate');
        if ($id == 0 || @!IPS_ObjectExists($id)) {
            return;
        }
        //@SGSMS_SendMessage($id, $Text);
        $scriptText = 'SGSMS_SendMessage(' . $id . ', "' . $Text . '");';
        IPS_RunScriptText($scriptText);
    }

    protected function SendTelegramMessage(string $Text): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('Telegram');
        if ($id == 0 || @!IPS_ObjectExists($id)) {
            return;
        }
        //@TB_SendMessage($id, $Text);
        $scriptText = 'TB_SendMessage(' . $id . ', "' . $Text . '");';
        IPS_RunScriptText($scriptText);
    }

    #################### Private

    private function SetDailyNotificationTimer(): void
    {
        if (!$this->ReadPropertyBoolean('UseDailyNotification')) {
            $milliseconds = 0;
        } else {
            $now = time();
            $time = json_decode($this->ReadPropertyString('DailyNotificationTime'));
            $hour = $time->hour;
            $minute = $time->minute;
            $second = $time->second;
            $definedTime = $hour . ':' . $minute . ':' . $second;
            if (time() >= strtotime($definedTime)) {
                $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
            } else {
                $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j'), (int) date('Y'));
            }
            $milliseconds = ($timestamp - $now) * 1000;
        }
        $this->SetTimerInterval('DailyNotification', $milliseconds);
    }
}