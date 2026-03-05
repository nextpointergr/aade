<?php

namespace NextPointer\Aade\Services;

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

    /**
     * Mathematical validation
     */
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

    /**
     * Check if AFM exists
     */
    public function exists(string $afm): bool
    {
        $result = $this->info($afm);

        return $result['success'] ?? false;
    }

    /**
     * Fetch info from AADE
     */
    public function info(string $afm): array
    {
        if (!$this->isValid($afm)) {
            return [
                'success' => false,
                'reason' => 'Invalid AFM'
            ];
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
  </ns:rgWsPublicAfmMethod>
 </env:Body>

</env:Envelope>
XML;
    }

    protected function request(string $xml): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: text/xml",
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
}