<?php

namespace pleodigital\pmmpayments\services;

use OpenPayU_Configuration;
use OpenPayU_Order;
use Craft;
use craft\base\Component;
use pleodigital\pmmpayments\records\Payment;
use pleodigital\pmmpayments\records\Recurring;

class Payu extends Component
{
    CONST ENVIRONMENT = 'secure';
    const ENTRIES_ON_PAGE= 50;

    private function setAuths($craftId = null, $isRecurring = false)
    {
        if($craftId) {
            $craftId = strval($craftId);
        }
        $fp = fopen('res.txt', 'w');
        fwrite($fp, json_encode($craftId,true));
        fclose($fp);

        if(!$craftId) {
            $tempEntry = \craft\elements\Entry :: find() -> section('moduly') -> slug('moduł-wpłaty') -> one() -> modulWplatyPmm;
            foreach ($tempEntry as $subentry) {
                if ($subentry -> payuDrugiKlucz) {
                    $entry = $subentry;
                }
            }
            if ($isRecurring) {
                $merchantPosId = $entry -> cyklicznePayuIdentyfikator;
                $signatureKey = $entry -> cyklicznePayuDrugiKlucz;
                $oAuthClientId = $entry -> cyklicznePayuIdentyfikator;
                $oAuthClientSecret = $entry -> cyklicznePayuOAuth;
            } else {
                $merchantPosId = $entry -> identyfikatorWplaty ? $entry -> identyfikatorWplaty : $entry -> wplatyIdentyfikatorWplaty;
                $signatureKey = $entry -> payuDrugiKlucz ? $entry -> payuDrugiKlucz : wplatyPayuDrugiKlucz;
                $oAuthClientId = $entry -> identyfikatorWplaty ? $entry -> identyfikatorWplaty : $entry -> wplatyIdentyfikatorWplaty;
                $oAuthClientSecret = $entry -> payuOAuth ? $entry -> payuOAuth : wplatyPayuOAuth;
            }
        } else {
            $entry = \craft\elements\Entry :: find() -> id($craftId) -> one();
            
            if ($isRecurring) {
                $merchantPosId = $entry -> cyklicznePayuIdentyfikator ? $entry -> cyklicznePayuIdentyfikator : $entry -> wplatyPayuCykliczneAutoryzacja;
                $signatureKey = $entry -> cyklicznePayuDrugiKlucz ? $entry -> cyklicznePayuDrugiKlucz : $entry -> wplatyPayuCykliczneDrugiKlucz;
                $oAuthClientId = $entry -> cyklicznePayuIdentyfikator ? $entry -> cyklicznePayuIdentyfikator : $entry -> wplatyPayuCykliczneAutoryzacja;
                $oAuthClientSecret = $entry -> cyklicznePayuOAuth ? $entry -> cyklicznePayuOAuth : $entry -> wplatyPayuCykliczneOAuth;
            } else {
                $merchantPosId = $entry -> identyfikatorWplaty ? $entry -> identyfikatorWplaty : $entry -> wplatyIdentyfikatorWplaty;
                $signatureKey = $entry -> payuDrugiKlucz ? $entry -> payuDrugiKlucz : wplatyPayuDrugiKlucz;
                $oAuthClientId = $entry -> identyfikatorWplaty ? $entry -> identyfikatorWplaty : $entry -> wplatyIdentyfikatorWplaty;
                $oAuthClientSecret = $entry -> payuOAuth ? $entry -> payuOAuth : wplatyPayuOAuth;
            }
        }

        OpenPayU_Configuration :: setEnvironment(self :: ENVIRONMENT);
        OpenPayU_Configuration :: setMerchantPosId($merchantPosId);
        OpenPayU_Configuration :: setSignatureKey($signatureKey);
        OpenPayU_Configuration :: setOauthClientId($oAuthClientId);
        OpenPayU_Configuration :: setOauthClientSecret($oAuthClientSecret);
        // OpenPayU_Configuration :: setMerchantPosId("2516446");
        // OpenPayU_Configuration :: setSignatureKey("223ea885c439668a370208385a4cec5f");
        // OpenPayU_Configuration :: setOauthClientId("2516446");
        // OpenPayU_Configuration :: setOauthClientSecret("a67abe8410ea9f34affdd5568b261ea2");
    }

    public function paymentRecursive($request) {
        $this -> setAuths($request -> getBodyParam('craftId'), true);
        $recurringPayment = Recurring :: find() 
            -> where(['provider' => 1]) 
            -> andWhere(['and', ["email" => $request -> getBodyParam("email")]])
            -> one();
        if ($recurringPayment) {
            $recurringPayment -> setAttribute('project', $request -> getBodyParam('project'));
            $recurringPayment -> setAttribute('title', $request -> getBodyParam('title'));
            $recurringPayment -> setAttribute('firstName', $request -> getBodyParam('firstName'));
            $recurringPayment -> setAttribute('lastName', $request -> getBodyParam('lastName'));
            $recurringPayment -> setAttribute('amount', (int)$request -> getBodyParam('amount'));
            $recurringPayment -> setAttribute('lastNotification', date('Y-m-d h:s:00', strtotime("-1 week")));
            $recurringPayment -> setAttribute('lastPayment', date('Y-m-d h:s:00'));
            $recurringPayment -> setAttribute('currency', $request -> getBodyParam('currency'));
            $recurringPayment -> setAttribute('language', $request -> getBodyParam('language'));
            $recurringPayment -> setAttribute('merchantPosId', OpenPayU_Configuration :: getMerchantPosId());
            $recurringPayment -> setAttribute('merchantSecondaryKey', OpenPayU_Configuration :: getSignatureKey());
            $recurringPayment -> setAttribute('active', true);
            $recurringPayment -> setAttribute('craftId', $request->getBodyParam("craftId"));
            $recurringPayment -> setAttribute('cancelHash', uniqid(uniqid(), true));
        } else {
            $recurringPayment = new Recurring();
            $recurringPayment -> setAttribute('project', $request -> getBodyParam('project'));
            $recurringPayment -> setAttribute('title', $request -> getBodyParam('title'));
            $recurringPayment -> setAttribute('firstName', $request -> getBodyParam('firstName'));
            $recurringPayment -> setAttribute('lastName', $request -> getBodyParam('lastName'));
            $recurringPayment -> setAttribute('email', $request -> getBodyParam('email'));
            $recurringPayment -> setAttribute('amount', (int)$request -> getBodyParam('amount'));
            $recurringPayment -> setAttribute('provider', 1);
            $recurringPayment -> setAttribute('lastNotification', date('Y-m-d h:s:00', strtotime("-1 week")));
            $recurringPayment -> setAttribute('lastPayment', date('Y-m-d h:s:00'));
            $recurringPayment -> setAttribute('currency', $request -> getBodyParam('currency'));
            $recurringPayment -> setAttribute('language', $request -> getBodyParam('language'));
            $recurringPayment -> setAttribute('merchantPosId', OpenPayU_Configuration :: getMerchantPosId());
            $recurringPayment -> setAttribute('merchantSecondaryKey', OpenPayU_Configuration :: getSignatureKey());
            $recurringPayment -> setAttribute('active', true);
            $recurringPayment -> setAttribute('craftId', $request->getBodyParam("craftId"));
            $recurringPayment -> setAttribute('cancelHash', uniqid(uniqid(), true));

            if(!$recurringPayment -> validate()) {
                return [
                    'error' => 'Nie udało się zapisać płatności. Niepoprawne dane.',
                ];
            }
        }
        $recurringPayment -> save();
        // $fp = fopen('tworzenie_karty.txt', 'w');
        // fwrite($fp, "<pre>".print_r($request->bodyParams, true)."</pre>");
        // fclose($fp);
        

        $token = $request -> getBodyParam('value');
        $tokenType = $request -> getBodyParam('type');


        $this -> makePayment(true, $recurringPayment, true, $token, $tokenType, false);
    }


    public function makePayment($isFirst = false, $request, $isRecurring = false, $token = null, $tokenType = null, $isRequest = true) {
        // $fp = fopen('res.txt', 'w');
        // fwrite($fp, json_encode($request->bodyParams ? $request->bodyParams : $request));
        // fclose($fp);
        // $fp = fopen('wykonwywanie_platnosci.txt', 'w');
        // fwrite($fp, "<pre>".print_r($isRequest ? $request>bodyParams : $request, true)."</pre>");
        // fclose($fp);
        $this->setAuths($isRequest ? $request->getBodyParam("craftId") : $request["craftId"], $isRecurring);
        $payment = new Payment();
        if ($isRequest) {
            $payment -> setAttribute('project', $request -> getBodyParam("project"));
            $payment -> setAttribute('title', $request -> getBodyParam("title"));
            $payment -> setAttribute('firstName', $request -> getBodyParam("firstName"));
            $payment -> setAttribute('lastName', $request -> getBodyParam("lastName"));
            $payment -> setAttribute('email', $request -> getBodyParam("email"));
            $payment -> setAttribute('amount', $request -> getBodyParam("amount") ? $request -> getBodyParam("amount") : $request->getBodyParam("totalAmount"));
            $payment -> setAttribute('currency', $request -> getBodyParam("currency") ? $request-> getBodyParam("currency") : $request->getBodyParam("currencyCode"));
            $payment -> setAttribute('language', $request -> getBodyParam("language"));
            $payment -> setAttribute('isRecurring', $isRecurring);
            $payment -> setAttribute('recurringId', $request -> getBodyParam("id") ? $request->getBodyParam("id") : 0);
            $payment -> setAttribute('provider', 1);
            $payment -> setAttribute('status', "WAITING");
        } else {
            $payment -> setAttribute('project', $request["project"]);
            $payment -> setAttribute('title', $request["title"]);
            $payment -> setAttribute('firstName', $request["firstName"]);
            $payment -> setAttribute('lastName', $request["lastName"]);
            $payment -> setAttribute('email', $request["email"]);
            $payment -> setAttribute('amount', $request["amount"]);
            $payment -> setAttribute('currency', $request["currency"]);
            $payment -> setAttribute('language', $request["language"]);
            $payment -> setAttribute('isRecurring', $isRecurring);
            $payment -> setAttribute('recurringId', $request["id"] ? $request["id"] : 0);
            $payment -> setAttribute('provider', 1);
            $payment -> setAttribute('status', "WAITING");
        }
        if ($isFirst) {
            $payment -> setAttribute("status", "COMPLETED");
        }
        $payment -> save();

        $order['continueUrl'] = Craft :: $app -> config -> general -> payUPaymentThanksPage;
        $order['notifyUrl'] = Craft :: $app -> config -> general -> paymentPayuStatus;
        $order['customerIp'] = $_SERVER['REMOTE_ADDR'];
        $order['merchantPosId'] = OpenPayU_Configuration :: getMerchantPosId();
        $order['description'] = $payment -> title;
        $order['currencyCode'] = $payment -> currency;
        $order['totalAmount'] = $payment -> amount * 100;
        $order['extOrderId'] = $payment["uid"];

        $extCustomerId = $isRecurring ? $request["uid"] : $payment["uid"];

        // Czy trzeba te pola? Nie wiem, ale co szkodzi wysłać Polakowi?
        $order['email'] = $payment -> email;
        $order['extCustomerId'] = $extCustomerId;

        $order['products'][0]['name'] = $payment -> project;
        $order['products'][0]['unitPrice'] = $payment -> amount * 100;
        $order['products'][0]['quantity'] = 1;

        $order['buyer']['extCustomerId'] = $extCustomerId;
        $order['buyer']['email'] = $payment -> email;
        $order['buyer']['firstName'] = $payment -> firstName;
        $order['buyer']['lastName'] = $payment -> lastName;
        $order['buyer']['language'] = $payment -> language;


        if ($isRecurring) {
            $order['cardOnFile'] = $isFirst ? "FIRST" : "STANDARD_MERCHANT";
            $order['payMethods']['payMethod']['value'] = $token;
            $order['payMethods']['payMethod']['type'] = $tokenType;
            if(!$isRequest) {
                $request -> setAttribute('cancelHash', uniqid(uniqid(), true));
                $request->save();
            } 
        }
                
        
        $fp = fopen('order.txt', 'w');
        fwrite($fp, "<pre>".print_r($order, true)."</pre>");
        fclose($fp);
        $responseObj = OpenPayU_Order :: create($order);
        $response = $responseObj -> getResponse();

        // return $response;

        if ($isRecurring) {
            Payments::instance()->sendEmail(
                $payment->email,
                false,
                $isRecurring 
                    ? "\nChcesz anulowac subskrypcję? Kliknij w link! ".Craft::$app->config->general->cancelSubscription."?id=".$request["cancelHash"]
                    : "",
                    $payment->firstName,
                    $payment->project);
            if(isset($response -> redirectUri)) {
                Craft :: $app -> getResponse() -> redirect($response -> redirectUri);
            } else {
                $payment->setAttribute("status", "COMPLETED");
                $payment->save();
                Craft :: $app -> getResponse() -> redirect(Craft :: $app -> config -> general -> payUPaymentThanksPage);
            }
        } else {
            return $response->redirectUri;
        }
    }

    public function getCardToken($recurringPayment) {
        $this->setAuths($recurringPayment["craftId"], true);
        $endpointDomain = self :: ENVIRONMENT === 'secure' ? 'secure' : 'secure.snd';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://" . $endpointDomain . ".payu.com/pl/standard/user/oauth/authorize");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        
        $oAuthClientId = OpenPayU_Configuration :: getOauthClientId();
        $oAuthClientSecret = OpenPayU_Configuration :: getOauthClientSecret();
        $email = $recurringPayment['email'];
        $customerId = $recurringPayment['uid'];

        $authString = "grant_type=trusted_merchant&client_id=$oAuthClientId&client_secret=$oAuthClientSecret&email=$email&ext_customer_id=$customerId";
        // echo $authString;

        curl_setopt($ch, CURLOPT_POSTFIELDS, $authString);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/x-www-form-urlencoded"
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        $responseObj = json_decode($response);
        // exit(print_r($responseObj, true));

        $accessToken = $responseObj->access_token;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://" . $endpointDomain . ".payu.com/api/v2_1/paymethods/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer $accessToken"));
        $response = curl_exec($ch);
        curl_close($ch);

        // $obj["card"] = json_decode($response)->cardTokens[0]->value;
        // $obj["accessToken"] = $accessToken;

        return array(json_decode($response)->cardTokens[0]->value, $accessToken);
    }

    // TODO: Cron co 0.5h
    public function monthlyPayment() {
        $recurringPayments = Recurring :: find() -> where(['provider' => 1]) -> andWhere(['and', ["active" => true]]) -> asArray() -> all();
        foreach($recurringPayments as $recurringPayment) {
            $this -> setAuths($recurringPayment["craftId"], true);
            $endpointDomain = self :: ENVIRONMENT === 'secure' ? 'secure' : 'secure.snd';
            // 1. GET TOKEN
            $accessToken = $this->getCardToken($recurringPayment, $endpointDomain)[0];
            $email = $recurringPayment["email"];

            // TODO: Jeśli minęło 30 dni od lastNotification w tabeli recurring to wysyłamy maila z linkiem do anulowania subskrybcji.
            // Siema, za 7 dni przeprowadzimy płątnośc za tyle i tyle szekli na to i to, jesli chcesz anulować to kliknij se tu.
            if(strtotime($recurringPayment['lastNotification']) < strtotime('-30 days')) {
                $model = Recurring::findOne(["id" => $recurringPayment["id"]]);
                $model->setAttribute("lastNotification", date("Y-m-d H:i:s"));
                $model -> setAttribute('cancelHash', uniqid(uniqid(), true));
                $model->save();
                Payments::instance()->sendEmail($email, true, "\nChcesz anulowac subskrypcję? Kliknij w link! ".Craft::$app->config->general->cancelSubscription."?id=".$model->cancelHash, $recurringPayment["firstName"], $recurringPayment["project"]);
            }
            // Tu po pobraniu tokenów będzie płatność cykliczna.
            if(strtotime($recurringPayment['lastPayment']) < strtotime('-30 days')) {
                $model = Recurring::findOne(["id" => $recurringPayment["id"]]);
                $this->makePayment(false, $model, true, $accessToken, "CARD_TOKEN", false);
                $model->setAttribute("lastPayment", date("Y-m-d H:i:s"));
                $model->save();
            }
        }
    }

    public function checkStatus($request) {
        $body = file_get_contents('php://input');
        $data = trim($body);
        $json = json_decode(json_decode(json_encode($data)));
        $file = fopen("payuRes.txt", 'w');
        fwrite($file, $data);

        $body = file_get_contents('php://input');
        $data = trim($body);

        $status = json_decode($data,true)['order']['status'];
        $id = json_decode($data,true)['order']['extOrderId'];
        $email = json_decode($data,true)['order']['buyer']['email'];

        $payment = Payment::findOne(['uid'=>$id]);
        $payment->status = "COMPLETED";
        $payment->save();
        Payments::instance()->sendEmail($email, false, "", $payment->firstName, $payment->project);
    }
}