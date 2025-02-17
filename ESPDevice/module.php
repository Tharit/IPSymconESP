<?php

class ESPDevice extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        // properties
        $this->RegisterPropertyBoolean('RetainActuatorValues', false);
        $this->RegisterPropertyString('Topic', '');
        $this->RegisterPropertyString('StatusTopic', 'STATUS');
        $this->RegisterPropertyString('LastWillTopic', 'LWT');

        // variables
        $this->RegisterVariableBoolean("Connected", "Connected");
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $lwtTopic = $this->ReadPropertyString('LastWillTopic');
        if(!$lwtTopic) {
            $this->SetPropertyString('LastWillTopic', '/LWT');
        }
        $statusTopic = $this->ReadPropertyString('StatusTopic');
        if(!$statusTopic) {
            $this->SetPropertyString('StatusTopic', '/STATUS');
        }

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
        $lwtTopic = $this->ReadPropertyString('LastWillTopic');
        $statusTopic = $this->ReadPropertyString('StatusTopic');

        if($Buffer->Topic === $topic . '/' . $lwtTopic) {
            $this->SetValue("Connected", $Buffer->Payload === 'Online' ? true : false);
        } else if($Buffer->Topic === $topic . '/' . $statusTopic) {
            $values = json_decode($Buffer->Payload, true);
            foreach($values as $key => $value) {
                if(($key === 'Actuators' || $key === 'Sensors') &&
                    is_array($value)) {
                    foreach($value as $key2 => $value2) {
                        $this->UpdateValue($key2, $value2, $key === 'Sensors');
                    }
                } else {
                    $this->UpdateValue($key, $value);
                }
            }
        }
    }

    private function UpdateValue($key, $value, $readonly = true) {
        $type = gettype($value);
        if($type === 'integer' || $type === 'double') {
            $this->RegisterVariableFloat($key, $key);
        } else if($type === 'boolean') {
            $this->RegisterVariableBoolean($key, $key, $readonly ? '': '~Switch');
        } else {
            $this->RegisterVariableString($key, $key);
        }
        if(!$readonly) {
            $this->EnableAction($key);
        }
        $this->SetValue($key, $value);
    }

    public function RequestAction($Ident, $Value)
    {
        //MQTT Server
        $Server['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Server['PacketType'] = 3;
        $Server['QualityOfService'] = 0;
        $Server['Retain'] = $this->ReadPropertyBoolean('RetainActuatorValues');
        $Server['Topic'] = $this->ReadPropertyString('Topic') . '/CMD/' . $Ident;
        $Server['Payload'] = is_string($Value) ? $Value : json_encode($Value);
        $ServerJSON = json_encode($Server, JSON_UNESCAPED_SLASHES);
        $resultServer = $this->SendDataToParent($ServerJSON);
    }
}