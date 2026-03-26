<?php

namespace NextPointer\Aade\Services;

use NextPointer\Aade\Responses\AadeResponse;

class Aade
{
    protected string $uri;
    protected string $username;
    protected string $password;
    protected string $calledBy;

    public function __construct()
    {
        $this->uri = config('aade.uri');
        $this->username = config('aade.username');
        $this->password = config('aade.password');
        $this->calledBy = config('aade.called_by');
    }

    public function isValid(string $afm): bool
    {
        if (!preg_match('/^\d{9}$/', $afm) || $afm === '000000000') {
            return false;
        }

        $m = 1;
        $sum = 0;

        for ($i = 7; $i >= 0; $i--) {
            $m *= 2;
            $sum += $afm[$i] * $m;
        }

        return ($sum % 11) % 10 === (int)$afm[8];
    }

    public function exists(string $afm): bool
    {
        return $this->info($afm)->success();
    }

    public function info(string $afm): AadeResponse
    {
        if (!$this->isValid($afm)) {
            return new AadeResponse([
                'success' => false,
                'reason' => 'Invalid AFM'
            ], '');
        }

        $xml = $this->buildRequest($afm);

        return $this->request($xml);
    }

    protected function buildRequest(string $afm): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"
              xmlns:ns="http://gr/gsis/rgwspublic/RgWsPublic.wsdl"
              xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
              xmlns:xsd="http://www.w3.org/2001/XMLSchema"
              xmlns:ns1="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">

    <env:Header>
        <ns1:Security>
            <ns1:UsernameToken>
                <ns1:Username>{$this->username}</ns1:Username>
                <ns1:Password>{$this->password}</ns1:Password>
            </ns1:UsernameToken>
        </ns1:Security>
    </env:Header>

    <env:Body>
        <ns:rgWsPublicAfmMethod>

            <RgWsPublicInputRt_in xsi:type="ns:RgWsPublicInputRtUser">
                <ns:afmCalledBy>{$this->calledBy}</ns:afmCalledBy>
                <ns:afmCalledFor>{$afm}</ns:afmCalledFor>
            </RgWsPublicInputRt_in>

            <RgWsPublicBasicRt_out xsi:type="ns:RgWsPublicBasicRtUser">
                <ns:afm xsi:nil="true"/>
                <ns:stopDate xsi:nil="true"/>
                <ns:postalAddressNo xsi:nil="true"/>
                <ns:doyDescr xsi:nil="true"/>
                <ns:doy xsi:nil="true"/>
                <ns:onomasia xsi:nil="true"/>
                <ns:legalStatusDescr xsi:nil="true"/>
                <ns:registDate xsi:nil="true"/>
                <ns:deactivationFlag xsi:nil="true"/>
                <ns:deactivationFlagDescr xsi:nil="true"/>
                <ns:postalAddress xsi:nil="true"/>
                <ns:firmFlagDescr xsi:nil="true"/>
                <ns:commerTitle xsi:nil="true"/>
                <ns:postalAreaDescription xsi:nil="true"/>
                <ns:INiFlagDescr xsi:nil="true"/>
                <ns:postalZipCode xsi:nil="true"/>
            </RgWsPublicBasicRt_out>

            <arrayOfRgWsPublicFirmActRt_out xsi:type="ns:RgWsPublicFirmActRtUserArray"/>

            <pCallSeqId_out xsi:type="xsd:decimal">0</pCallSeqId_out>

            <pErrorRec_out xsi:type="ns:GenWsErrorRtUser">
                <ns:errorDescr xsi:nil="true"/>
                <ns:errorCode xsi:nil="true"/>
            </pErrorRec_out>

        </ns:rgWsPublicAfmMethod>
    </env:Body>

</env:Envelope>
XML;
    }

    protected function request(string $xml): AadeResponse
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                "Content-Type: text/xml; charset=utf-8",
                "Connection: Close",
            ],
            CURLOPT_POSTFIELDS => $xml
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return new AadeResponse([
                'success' => false,
                'reason' => curl_error($ch)
            ], '');
        }

        curl_close($ch);

        // SOAP Fault handling
        if (str_contains($response, '<env:Fault>')) {
            return new AadeResponse([
                'success' => false,
                'reason' => 'SOAP Fault'
            ], $response);
        }

        $parsed = $this->parseResponse($response);

        return new AadeResponse($parsed, $response);
    }

    public function checkConnection(): array
    {
        $xml = $this->buildVersionRequest();

        $response = $this->requestRaw($xml);

        if (!$response['success']) {
            return [
                'success' => false,
                'type' => 'connection_error',
                'message' => $response['reason']
            ];
        }

        if (str_contains($response['raw'], '<env:Fault>')) {
            return [
                'success' => false,
                'type' => 'soap_fault',
                'message' => $response['raw']
            ];
        }

        return [
            'success' => true,
            'message' => 'AADE connection OK'
        ];
    }


    protected function parseResponse(string $xmlString): array
    {
        // remove namespaces
        $xmlString = preg_replace('/(<\/?)[a-zA-Z0-9]+:([^>]*>)/', '$1$2', $xmlString);

        $xml = simplexml_load_string($xmlString, "SimpleXMLElement", LIBXML_NOCDATA);

        if (!$xml) {
            return [
                'success' => false,
                'reason' => 'Invalid XML response'
            ];
        }

        $json = json_encode($xml, JSON_UNESCAPED_UNICODE);
        $array = json_decode($json, true);

        $body = $array['Body']['rgWsPublicAfmMethodResponse'] ?? null;

        if (!$body) {
            return [
                'success' => false,
                'reason' => 'Invalid AADE response'
            ];
        }

        // error handling
        if (
            isset($body['pErrorRec_out']['errorCode']) &&
            !empty($body['pErrorRec_out']['errorCode'])
        ) {
            return [
                'success' => false,
                'reason' => $body['pErrorRec_out']['errorDescr'] ?? 'AADE error'
            ];
        }

        // =========================
        // BASIC DATA
        // =========================
        $basic = $body['RgWsPublicBasicRt_out'] ?? [];
        $cleanBasic = [];

        foreach ($basic as $key => $value) {
            $cleanBasic[$key] = is_array($value) ? null : $value;
        }

        // =========================
        // ACTIVITIES
        // =========================
        $activitiesRaw = $body['arrayOfRgWsPublicFirmActRt_out']['RgWsPublicFirmActRtUser'] ?? [];

        if (isset($activitiesRaw['firmActCode'])) {
            $activitiesRaw = [$activitiesRaw];
        }

        $activities = [];

        foreach ($activitiesRaw as $act) {

            $descr = $act['firmActDescr'] ?? null;

            $codeFromDescr = null;
            $cleanDescr = null;

            if ($descr) {
                $parts = preg_split('/\s+/', trim($descr), 2);
                $codeFromDescr = $parts[0] ?? null;
                $cleanDescr = $parts[1] ?? null;
            }

            $activities[] = [
                'code' => $act['firmActCode'] ?? null,
                'code_from_descr' => $codeFromDescr,
                'description' => $cleanDescr,
                'type' => $act['firmActKind'] ?? null,
                'type_label' => $act['firmActKindDescr'] ?? null,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'tax_id' => $cleanBasic['afm'] ?? null,
                'name' => trim(preg_replace('/\s+/', ' ', $cleanBasic['onomasia'] ?? '')),
                'address' => $cleanBasic['postalAddress'] ?? null,
                'address_number' => $cleanBasic['postalAddressNo'] ?? null,
                'city' => $cleanBasic['postalAreaDescription'] ?? null,
                'postal_code' => $cleanBasic['postalZipCode'] ?? null,
                'tax_office' => $cleanBasic['doyDescr'] ?? null,
                'status' => $cleanBasic['deactivationFlagDescr'] ?? null,
                'activities' => $activities,
            ]
        ];
    }

    protected function buildVersionRequest(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"
              xmlns:ns="http://gr/gsis/rgwspublic/RgWsPublic.wsdl">
    <env:Header/>
    <env:Body>
        <ns:rgWsPublicVersionInfo/>
    </env:Body>
</env:Envelope>
XML;
    }

    protected function requestRaw(string $xml): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                "Content-Type: text/xml; charset=utf-8",
                "Connection: Close",
            ],
            CURLOPT_POSTFIELDS => $xml
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return [
                'success' => false,
                'reason' => curl_error($ch)
            ];
        }

        curl_close($ch);

        return [
            'success' => true,
            'raw' => $response
        ];
    }

    public function healthCheck(): array
    {
        $testAfm = $this->calledBy;
        $response = $this->info($testAfm);
        if (!$response->success()) {
            return [
                'success' => false,
                'type' => 'auth_error',
                'message' => $response->reason()
            ];
        }

        return [
            'success' => true,
            'message' => 'AADE fully working'
        ];
    }
}
