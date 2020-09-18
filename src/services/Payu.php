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

    private function setAuths($craftId = null)
    {
        if($craftId) {
            $craftId = parseInt($craftId);
        }

        if(!$craftId) {
            $tempEntry = \craft\elements\Entry :: find() -> section('moduly') -> slug('moduł-wpłaty') -> one() -> modulWplatyPmm;
            foreach ($tempEntry as $subentry) {
                if ($subentry -> payuDrugiKlucz) {
                    $entry = $subentry;
                }
            }
            $merchantPosId = $entry -> identyfikatorWplaty;
            $signatureKey = $entry -> payuDrugiKlucz;
            $oAuthClientId = $entry -> identyfikatorWplaty;
            $oAuthClientSecret = $entry -> payuOAuth;

            // TODO: Do usunięcia
//            $merchantPosId = 300746;
//            $signatureKey = 'b6ca15b0d1020e8094d9b5f8d163db54';
//            $oAuthClientId = 300746;
//            $oAuthClientSecret = '2ee86a66e5d97e3fadc400c9f19b065d';
//            $oAuthClientSecret = 'eb66cced5ebf45d5776de92896aabbda';
        } else {
            $entry = \craft\elements\Entry :: find() -> id($craftId) -> one();
            $merchantPosId = $entry -> wplatyIdentyfikatorWplaty;
            $signatureKey = $entry -> wplatyPayuDrugiKlucz;
            $oAuthClientId = $entry -> wplatyIdentyfikatorWplaty;
            $oAuthClientSecret = $entry -> wplatyPayuOAuth;
        }

        OpenPayU_Configuration :: setEnvironment(self :: ENVIRONMENT);
        OpenPayU_Configuration :: setMerchantPosId($merchantPosId);
        OpenPayU_Configuration :: setSignatureKey($signatureKey);
        OpenPayU_Configuration :: setOauthClientId($oAuthClientId);
        OpenPayU_Configuration :: setOauthClientSecret($oAuthClientSecret);
    }

    public function paymentRecursive($request) {
//        $fp = fopen('results.json', 'w');
//        fwrite($fp, json_encode($request->bodyParams));
//        fclose($fp);
        Payments::instance()->sendEmail(
            "hubertsosnicki2000@pleodigital.com",
            false,
            "");
        $this -> setAuths($request -> getBodyParam('craftId'));
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
        $fp = fopen('git.txt', 'w');
        fwrite($fp, json_encode($request));
        fclose($fp);

        if(!$recurringPayment -> validate()) {
            return [
                'error' => 'Nie udało się zapisać płatności. Niepoprawne dane.',
            ];
        } else {
            $recurringPayment -> save();
        }

        $token = $request -> getBodyParam('value');
        $tokenType = $request -> getBodyParam('type');


        $this -> makePayment(true, $request, true, $token, $tokenType, true);
    }


    public function makePayment($isFirst = false, $request, $isRecurring = false, $token = null, $tokenType = null, $isRequest = true) {
        $fp = fopen('res.txt', 'w');
        fwrite($fp, json_encode($request->bodyParams));
        fclose($fp);
        $this->setAuths($request->getBodyParam("craftId"));
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
            $payment -> save();
        } else {
            $payment -> setAttribute('project', $request -> project);
            $payment -> setAttribute('title', $request -> title);
            $payment -> setAttribute('firstName', $request -> firstName);
            $payment -> setAttribute('lastName', $request -> lastName);
            $payment -> setAttribute('email', $request -> email);
            $payment -> setAttribute('amount', $request -> amount);
            $payment -> setAttribute('currency', $request -> currency);
            $payment -> setAttribute('language', $request -> language);
            $payment -> setAttribute('isRecurring', $isRecurring);
            $payment -> setAttribute('recurringId', $request -> id ? $request -> id : 0);
            $payment -> setAttribute('provider', 1);
            $payment -> setAttribute('status', "WAITING");
            $payment -> save();
        }

        $order['continueUrl'] = Craft :: $app -> config -> general -> payUPaymentThanksPage;
        $order['notifyUrl'] = Craft :: $app -> config -> general -> paymentPayuStatus;
        $order['customerIp'] = $_SERVER['REMOTE_ADDR'];
        $order['merchantPosId'] = OpenPayU_Configuration :: getMerchantPosId();
        $order['description'] = $payment -> title;
        $order['currencyCode'] = $payment -> currency;
        $order['totalAmount'] = $payment -> amount * 100;
        $order['extOrderId'] = $payment -> uid;

        // Czy trzeba te pola? Nie wiem, ale co szkodzi wysłać Polakowi?
        //$order['email'] = $payment -> email;
        //$order['extCustomerId'] = $payment -> uid;

        $order['products'][0]['name'] = $payment -> project;
        $order['products'][0]['unitPrice'] = $payment -> amount * 100;
        $order['products'][0]['quantity'] = 1;

        $order['buyer']['extCustomerId'] = $payment -> uid;
        $order['buyer']['email'] = $payment -> email;
        $order['buyer']['firstName'] = $payment -> firstName;
        $order['buyer']['lastName'] = $payment -> lastName;
        $order['buyer']['language'] = $payment -> language;


        if ($isRecurring) {
            $order['cardOnFile'] = $isFirst ? "FIRST" : "STANDARD_MERCHANT";
            $order['payMethods']['payMethod']['value'] = $token;
            $order['payMethods']['payMethod']['type'] = $tokenType;
        }

        $recurringId = $payment->recurringId;

        // TODO: Wysyłka maila o pomyślnej płatności. Siema, wykonaliśmy wpłate na jakis tam projekt cos tam cos tam.
        Payments::instance()->sendEmail(
            $payment->email,
            false,
            $isRecurring ? "\nChcesz anulowac subskrypcję? Kliknij w link!".Craft::$app->config->general->cancelSubscription."?id=".
                $recurringId
                : "",
                $payment->firstName,
                $payment->project);
//        return $order;

        $responseObj = OpenPayU_Order :: create($order);
        $response = $responseObj -> getResponse();

        if ($isRecurring) {
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
        $this->setAuths(null);
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
//        return $authString;

        curl_setopt($ch, CURLOPT_POSTFIELDS, $authString);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/x-www-form-urlencoded"
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        $responseObj = json_decode($response);

        return $responseObj;

        $accessToken = $responseObj->access_token;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://" . $endpointDomain . ".payu.com/api/v2_1/paymethods/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer $accessToken"));
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response);
    }

    // TODO: Cron co 0.5h
    public function monthlyPayment() {
        $this -> setAuths(null);
        $recurringPayments = Recurring :: find() -> where(['provider' => 1]) -> andWhere(['and', ["active" => true]]) -> asArray() -> all();
        foreach($recurringPayments as $recurringPayment) {
            $endpointDomain = self :: ENVIRONMENT === 'secure' ? 'secure' : 'secure.snd';
            // 1. GET TOKEN
            $accessToken = $this->getCardToken($recurringPayment, $endpointDomain);

            $email = $recurringPayment["email"];

            // TODO: Jeśli minęło 30 dni od lastNotification w tabeli recurring to wysyłamy maila z linkiem do anulowania subskrybcji.
            // Siema, za 7 dni przeprowadzimy płątnośc za tyle i tyle szekli na to i to, jesli chcesz anulować to kliknij se tu.
            if(strtotime($recurringPayment['lastNotification']) < strtotime('-30 days')) {
                Payments::instance()->sendEmail($email, true, "\nChcesz anulowac subskrypcję? Kliknij w link!".Craft::$app->config->general->cancelSubscription."?id=".$recurringPayment["id"], $recurringPayment->firstName, $recurringPayment->project);
                $model = Recurring::findOne(["id" => $recurringPayment["id"]]);
                $model->setAttribute("lastNotification", date("Y-m-d H:i:s"));
                $model->save();
            }
            // Tu po pobraniu tokenów będzie płatność cykliczna.
            if(strtotime($recurringPayment['lastPayment']) < strtotime('-30 days')) {
                $this->makePayment(false, $recurringPayment, true, $accessToken, $tokenType = null, $isRequest = true);
            }
        }
    }

    public function checkStatus($request) {
        $body = file_get_contents('php://input');
        $data = trim($body);
        $json = json_decode(json_decode(json_encode($data)));
        $file = fopen("paypal.txt", 'w');
        fwrite($file, $data);

        $body = file_get_contents('php://input');
        $data = trim($body);

        $status = json_decode($data,true)['order']['status'];
        $id = json_decode($data,true)['order']['extOrderId'];
        $email = json_decode($data,true)['order']['buyer']['email'];

        $payment = Payment::findOne(['uid'=>$id]);
        $payment->status = $status;
        $payment->save();
        Payments::instance()->sendEmail($email, false, "", $payment->firstName, $payment->project);
    }
}