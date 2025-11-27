<?php
namespace App\Core;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ImprovedSoapClient
{
   private $openSoapEnvelope;
   private $closeSoapEnvelope;
   public function run($url, $token, $command)
   {
      try {

         $this->openSoapEnvelope = '<?xml version="1.0" encoding="utf-8"?>
         <SOAP-ENV:Envelope xmlns:ns1="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://videcom.com/">';
         $this->closeSoapEnvelope = '</SOAP-ENV:Envelope>';
         $soapBody =
            '<SOAP-ENV:Body>
               <ns1:Token>' . $token . '</ns1:Token>
               <ns1:Command>' . $command . '</ns1:Command>
            </SOAP-ENV:Body>';

         $xmlRequest = $this->generateSoapRequest($soapBody);
         //
         // return $xmlRequest;
         $client = new Client();
         $options = [
            'body' => $xmlRequest,
            'headers' => [
               "Content-Type" => "text/xml",
               "Accept" => "*/*",
               "Accept-Encoding" => "gzip, deflate,br",
               // "Host" => "uat.Intenal.com",
               "SOAPAction" => "http://videcom.com/RunVRSCommand"
            ]
         ];
         $res = $client->request(
            'POST',
            $url,
            $options
         );
         // return $res->getBody();

         $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $res->getBody()->getContents());
         $xml = new \SimpleXMLElement($response);
         $body = $xml->xpath('//soapBody')[0];
         $array = json_decode(json_encode((array) $body), TRUE);
         // dd($array["LoginResponse"]["LoginResult"]);
         return ["data" => $res->getBody(), "ResponseCode" => 200];
      } catch (\Exception $e) {
         Log::error("Login Error", [$e->getMessage()]);
         return ["error" => "Login Failed " . $e->getMessage() . $e->getFile() . ':' . $e->getLine(), "ResponseCode" => 500];
      } catch (GuzzleException $e) {
         Log::error("Guzzle Error", [$e->getMessage()]);
         return ["error" => "Login Failed " . $e->getMessage(), "ResponseCode" => 500];
      }
   }
   public function generateSoapRequest($soapBody)
   {
      return $this->openSoapEnvelope . $soapBody . $this->closeSoapEnvelope;
   }
}