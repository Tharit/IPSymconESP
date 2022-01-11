<?php

class ESPDevice extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        // properties
        $this->RegisterPropertyString('Topic', '');

        // variables
        $this->RegisterVariableBoolean("Connected", "Connected");
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $topic = $this->ReadPropertyString('Topic');
        $this->SetReceiveDataFilter('.*' . $topic . '.*');
    }

    
    public function ReceiveData($JSONString)
    {
        $this->SendDebug('JSON', $JSONString, 0);
        if (empty($this->ReadPropertyString('Topic'))) return;

        $data = json_decode($JSONString);

        $Buffer = $data;

        $topic = $this->ReadPropertyString('Topic');
        
        if($Buffer->Topic === $topic . "/LWT") {
            $this->SetValue("Connected", $Buffer->Payload === 'Online' ? true : false);
        } else if($Buffer->Topic === $topic . "/SENSOR") {
            $values = json_decode($Buffer->Payload);
            foreach($values as $key => $value) {
                $type = gettype($value);
                if($type === 'integer' || $type === 'float') {
                    $this->RegisterVariableFloat($key, $key);
                } else if($type === 'boolean') {
                    $this->RegisterVariableBoolean($key, $key, '~Switch');
                    $this->EnableAction($key);
                } else {
                    $this->RegisterVariableString($key, $key);
                }
                $this->SetValue($key, $value);
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        //MQTT Server
        $Server['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Server['PacketType'] = 3;
        $Server['QualityOfService'] = 0;
        $Server['Retain'] = false;
        $Server['Topic'] = $this->ReadPropertyString('Topic') . '/CMD/' . $Ident;
        $Server['Payload'] = json_encode($Value);
        $ServerJSON = json_encode($Server, JSON_UNESCAPED_SLASHES);
        $resultServer = $this->SendDataToParent($ServerJSON);
    }
}