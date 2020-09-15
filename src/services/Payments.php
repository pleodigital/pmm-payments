<?php

namespace pleodigital\pmmpayments\services;

use craft\records\Entry;
use OpenPayU_Configuration;
use OpenPayU_Order;
use OpenPayU_Retrieve;
use OauthGrantType;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Core\AccessTokenRequest;
use PayPalHttp\HttpClient;
use pleodigital\pmmpayments\Pmmpayments;
use DateTime;

use Craft; 
use Yii;
use yii\data\ActiveDataProvider;
use League\Csv\Writer;
use SplTempFileObject;
use craft\base\Component;
use craft\helpers\Json;
use pleodigital\pmmpayments\records\Payment;
use pleodigital\pmmpayments\records\Recurring;
use yii\data\SqlDataProvider;

class Payments extends Component
{
    const ENTRIES_ON_PAGE= 50;
    CONST ENVIRONMENT = 'secure';

    private function sendEmail($email, $additives = '') {
        Craft::$app->getMailer()->compose()->setTo($email)->setSubject('Polska Misja Medyczna')->setHtmlBody("
            <p>Drogi Darczyńco!</p>
                <p>Dziękujemy za Twoją wpłatę! Twoja darowizna to realna pomoc dla kobiet i dzieci, która pomoże zmienić
                 ich życie na lepsze. Prowadzimy wiele działań na całym świecie. Nie byłoby to możliwe bez Twojego
                  wsparcia. Razem budujemy pomoc! 
                </p>
                <p>Dziękujemy!</p>
                <p>Zespół Polskiej Misji Medycznej</p>
        ".$additives)->send();
    }

    public function checkPaypalActivation($request) {
        $body = file_get_contents('php://input');
        $data = trim($body);
        $json = json_decode($data);
        $file = fopen("paypalActivate.txt", 'w');
        fwrite($file, $data);

        $id = $json->resource->id;
        $status = $json->resource->status;
        $email = json_decode($data,true)['resource']['subscriber']['email_address'];

        $command = Yii::$app->db->createCommand("UPDATE pmmpayments_tokens SET status='ACTIVE' where token='".$id."'");
        $command->execute();
        $this->sendEmail($email,
            "<br><br><p style='font-size: 10px; color: lightgray'>Anulowanie subskrypcji:
            ".Craft::$app->config->general->cancelSubscription."/?id=".$id."</p>");

        return $json;
    }

    public function checkMonthlyPayments() {
        $sql = 'SELECT * FROM pmmpayments_tokens WHERE dateUpdated <= (now() - interval 1 MONTH)';

        $activeDataProvider = new SqlDataProvider([
            'sql' => $sql,
            'pagination' => [
                'pageSize' => false
            ],
        ]);

        $totals = $activeDataProvider->getModels();
        $result = [];

        foreach ($totals as $field) {
            if ($field['provider'] == 1) {
                $this->payuMonthlyPayment();
            } else {
                $result[] = $this->paypalMonthlyPayment($field);
            }
        }

//        return $totals;
        return $result;
    }

    public function paypalMonthlyPayment($field) {
        $id = $field['token'];
        $clientId = Craft::$app->config->general->paypalId;
        $clientSecret = Craft::$app->config->general->paypalSecret;
        $token = $this->getPaypalAuth($clientId, $clientSecret);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Craft::$app->config->general->paypalUrl."v1/billing/subscriptions/$id");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$token,
            'Content-type: applications/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($ch));


        curl_close($ch);
        $sql;
        if (isset($response->status)) {
            $sql = "UPDATE pmmpayments_tokens SET status='".$response->status."', dateUpdated=now() WHERE token='$id'";
            $command = Yii::$app->db->createCommand($sql);
            $command->execute();

            if ($response->status == "ACTIVE") {
                $payment = new Payment();
                $payment -> setAttribute('project', $field['project']);
                $payment -> setAttribute('title', $field['title']);
                $payment -> setAttribute('firstName', $field['firstName']);
                $payment -> setAttribute('lastName', $field['lastName']);
                $payment -> setAttribute('email', $field['email']);
                $payment -> setAttribute('amount', $field['totalAmount']);
                $payment -> setAttribute('isRecurring', 'true');
                $payment -> setAttribute('provider', 2);
                $payment -> setAttribute('status', "COMPLETED");
                $payment->save();

                $this->sendEmail($field['email'],
                    "<br><br><p style='font-size: 10px; color: lightgray'>Anulowanie subskrypcji:
                    ".Craft::$app->config->general->cancelSubscription."/?id=".$response->id."</p>");
            }
        } else {
            $sql = "UPDATE pmmpayments_tokens SET status='CANCELLED' WHERE token='$id'";
            $command = Yii::$app->db->createCommand($sql);
            $command->execute();
//            return "error";
        }


        return $response;
    }

    public function cancelSubscription($request) {
        $id = $_GET['id'];
        $clientId = Craft::$app->config->general->paypalId;
        $clientSecret = Craft::$app->config->general->paypalSecret;
        $token = $this->getPaypalAuth($clientId, $clientSecret);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Craft::$app->config->general->paypalUrl."v1/billing/subscriptions/$id/cancel");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$token,
            'Content-type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        $data = [
            "reason" => "No reason"
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS,  json_encode($data));
        $response = json_decode(curl_exec($ch));

        curl_close($ch);

        header('Location: '.Craft::$app->config->general->cancelSubscriptionRedirect);
        return $response;
    } 

    private function setPayUAuths($craftId = null)
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
            $merchantPosId = 2516447;
            $signatureKey = 'df356e27714e16f2616c1fe9889ac6b7';
            $oAuthClientId = 2516447;
            $oAuthClientSecret = '6d064970ada5084fd4bf1db51e8ff4bf';
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

    public function payuPaymentRecursive($request) { 
        $this -> setPayUAuths($request -> getBodyParam('craftId'));
        $recurringPayment = new Recurring(); 
        $recurringPayment -> setAttribute('project', $request -> getBodyParam('project'));
        $recurringPayment -> setAttribute('title', $request -> getBodyParam('title'));
        $recurringPayment -> setAttribute('firstName', $request -> getBodyParam('firstName'));
        $recurringPayment -> setAttribute('lastName', $request -> getBodyParam('lastName'));
        $recurringPayment -> setAttribute('email', $request -> getBodyParam('email'));
        $recurringPayment -> setAttribute('amount', (int)$request -> getBodyParam('amount') / 100);
        $recurringPayment -> setAttribute('provider', 1);
        $recurringPayment -> setAttribute('lastNotification', date('Y-m-d h:s:00', strtotime("-1 week")));
        $recurringPayment -> setAttribute('lastPayment', date('Y-m-d h:s:00'));
        $recurringPayment -> setAttribute('currency', $request -> getBodyParam('currency'));
        $recurringPayment -> setAttribute('language', $request -> getBodyParam('language'));
        $recurringPayment -> setAttribute('merchantPosId', OpenPayU_Configuration :: getMerchantPosId());
        $recurringPayment -> setAttribute('merchantSecondaryKey', OpenPayU_Configuration :: getSignatureKey());
        $recurringPayment -> setAttribute('active', true);

        if(!$recurringPayment -> validate()) {
            return [
                'error' => 'Nie udało się zapisać płatności. Niepoprawne dane.',
            ];
        } else {
            $recurringPayment -> save();
        } 

        $token = $request -> getBodyParam('value');
        $tokenType = $request -> getBodyParam('type');

        $this -> makePayURecursivePayment(true, $recurringPayment, $token, $tokenType);
    }

    private function makePayURecursivePayment($isFirst = false, $recurringPayment, $token, $tokenType) {
        $payment = new Payment();
        $payment -> setAttribute('project', $recurringPayment -> project);
        $payment -> setAttribute('title', $recurringPayment -> title);
        $payment -> setAttribute('firstName', $recurringPayment -> firstName);
        $payment -> setAttribute('lastName', $recurringPayment -> lastName);
        $payment -> setAttribute('email', $recurringPayment -> email);
        $payment -> setAttribute('amount', $recurringPayment -> amount);
        $payment -> setAttribute('currency', $recurringPayment -> currency);
        $payment -> setAttribute('language', $recurringPayment -> language);
        $payment -> setAttribute('isRecurring', true);
        $payment -> setAttribute('recurringId', $recurringPayment -> id);
        $payment -> setAttribute('provider', 1);
        $payment -> setAttribute('status', "WAITING");
        $payment -> save(); 

        $order['continueUrl'] = Craft :: $app -> config -> general -> payUPaymentThanksPage;
        $order['notifyUrl'] = Craft :: $app -> config -> general -> paymentPayuStatus;
        $order['customerIp'] = $_SERVER['REMOTE_ADDR'];
        $order['merchantPosId'] = OpenPayU_Configuration :: getMerchantPosId();
        $order['description'] = $payment -> title;
        $order['currencyCode'] = $payment -> currency;
        $order['totalAmount'] = $payment -> amount * 100;  
        $order['extOrderId'] = $payment -> uid;  
        $order['cardOnFile'] = $isFirst ? "FIRST" : "STANDARD_MERCHANT";

        // Czy trzeba te pola? Nie wiem, ale co szkodzi wysłać Polakowi?
        //$order['email'] = $payment -> email;
        //$order['extCustomerId'] = $recurringPayment -> uid;

        $order['products'][0]['name'] = $payment -> project;
        $order['products'][0]['unitPrice'] = $payment -> amount * 100;
        $order['products'][0]['quantity'] = 1;

        $order['buyer']['extCustomerId'] = $recurringPayment -> uid;
        $order['buyer']['email'] = $payment -> email;
        $order['buyer']['firstName'] = $payment -> firstName;
        $order['buyer']['lastName'] = $payment -> lastName;
        $order['buyer']['language'] = $payment -> language; 

        $order['payMethods']['payMethod']['value'] = $token;
        $order['payMethods']['payMethod']['type'] = $tokenType; 
        
        // TODO: Wysyłka maila o pomyślnej płatności. Siema, wykonaliśmy wpłate na jakis tam projekt cos tam cos tam. 
  
        $responseObj = OpenPayU_Order :: create($order);
        $response = $responseObj -> getResponse();   
        if(isset($response -> redirectUri)) {
            Craft :: $app -> getResponse() -> redirect($response -> redirectUri);
        } else {
            Craft :: $app -> getResponse() -> redirect(Craft :: $app -> config -> general -> payUPaymentThanksPage);
        }
    }

    // TODO: Cron co 0.5h 
    public function payuMonthlyPayment() {
        $this -> setPayUAuths(null);
        $recurringPayments = Recurring :: find() -> where(['provider' => 1]) -> andWhere(['and', ["active" => true]]) -> asArray() -> all();
        foreach($recurringPayments as $recurringPayment) { 
            $endpointDomain = self :: ENVIRONMENT === 'secure' ? 'secure' : 'secure.snd';  
            // 1. GET TOKEN
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

            curl_setopt($ch, CURLOPT_POSTFIELDS, $authString);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
              "Content-Type: application/x-www-form-urlencoded"
            ));

            // TODO: Jeśli minęło 30 dni od lastNotification w tabeli recurring to wysyłamy maila z linkiem do anulowania subskrybcji. 
            // Siema, za 7 dni przeprowadzimy płątnośc za tyle i tyle szekli na to i to, jesli chcesz anulować to kliknij se tu.  
            
            $response = curl_exec($ch);
            curl_close($ch);
            $responseObj = json_decode($response);           

            $accessToken = $responseObj -> access_token; 

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://" . $endpointDomain . ".payu.com/api/v2_1/paymethods/");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer $accessToken"));
            $response = curl_exec($ch);
            curl_close($ch);

            // Tu po pobraniu tokenów będzie płatność cykliczna. 

            echo "https://" . $endpointDomain . ".payu.com/api/v2_1/paymethods/";
 
            echo '<pre>'. print_r(json_decode($response), true) .'</pre>';
        }
    }

    private function paypalPayment($request, $payment) {
        if ($request->getBodyParam('isRecurring') == 'true') {
            $payment->status = "COMPLETED";
            $payment->save();
            return $this->paypalRecurring($request);

        } else {
            $clientId = Craft::$app->config->general->paypalId;
            $clientSecret = Craft::$app->config->general->paypalSecret;
            $env = new ProductionEnvironment($clientId, $clientSecret);
            $client = new PayPalHttpClient($env);

            $order = new OrdersCreateRequest();
            $order->prefer('return=representation');
            $order->body = [
                'intent' => 'CAPTURE',
                'application_context' => array(
                    'return_url' => Craft :: $app -> config -> general -> payUPaymentThanksPage,
                    'cancel_url' => Craft :: $app -> config -> general -> payUPaymentThanksPage
                ),
                'purchase_units' =>
                    array(
                        0 =>
                            array(
                                'amount' =>
                                    array(
                                        'currency_code' => 'PLN',
                                        'value' => $request->getBodyParam('totalAmount')
                                    ),
                                'custom_id' => $payment->uid,
                                'description' => $request->getBodyParam('project')
                            )
                    )
            ];
            $response = $client->execute($order);
            return $response->result->links[1]->href;
//            return print_r($response);
        }
    }

    private function paypalRecurring($request) {
        $clientId = Craft::$app->config->general->paypalId;
        $clientSecret = Craft::$app->config->general->paypalSecret;
        $token = $this->getPaypalAuth($clientId, $clientSecret);

//        $productId = 'PROD-54B45003386621515'; // sandbox
//        $planId = 'P-9VB881974E410483PL3FXXWI'; // sandbox
        $productId = 'PROD-0BG38935129714459'; // production
        $planId = 'P-4560462162705783DL3HCSSI'; // production
        $date = new DateTime();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Craft::$app->config->general->paypalUrl."v1/billing/subscriptions");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$token,
            'Content-type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        $data = [
            'plan_id' => $planId,
            'quantity' => $request->getBodyParam('totalAmount'),
            'subscriber' => [
                'name' => [
                    'given_name' => $request->getBodyParam('firstName'),
                    'surname' => $request->getBodyParam('lastName')
                ],
                'email_address' => $request->getBodyParam('email')
            ],
            'application_context' => [
                'brand_name' => 'Polska Misja Medyczna',
                'locale' => 'pl-PL',
                'user_action' => 'SUBSCRIBE_NOW',
                'payment_method' => [
                    'payer_selected' => 'PAYPAL',
                    'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED'
                ],
                'return_url' => 'http://polska-misja-medyczna.pleodev.usermd.net',
                'cancel_url' => 'http://polska-misja-medyczna.pleodev.usermd.net'
            ]
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS,  json_encode($data));
        $response = json_decode(curl_exec($ch));

        curl_close($ch);

        $command = Yii::$app->db->createCommand(
            "INSERT INTO pmmpayments_tokens(`token`,`project`,`title`,`provider`,`currencyCode`,`totalAmount`,`email`, `language`, `firstName`, `lastName`, `status`)VALUES ('"
            .$response->id."', '" .$request -> getBodyParam("project")."', '".$request -> getBodyParam("title")."', '".$request -> getBodyParam("provider")."','"
            .$request -> getBodyParam("currencyCode")."', '".$request -> getBodyParam("totalAmount")."', '".$request -> getBodyParam("email")."','"
            .$request -> getBodyParam("language")."', '".$request -> getBodyParam("firstName")."', '".$request -> getBodyParam("lastName")."', '".$response->status."')");
        $command->execute();

        return $response;
    }

    public function processRequestData($request)
    {
        $response;
        $payment = new Payment();
        $payment -> setAttribute('project', $request -> getBodyParam('project'));
        $payment -> setAttribute('title', $request -> getBodyParam('title'));
        $payment -> setAttribute('firstName', $request -> getBodyParam('firstName'));
        $payment -> setAttribute('lastName', $request -> getBodyParam('lastName'));
        $payment -> setAttribute('email', $request -> getBodyParam('email'));
        $payment -> setAttribute('amount', $request -> getBodyParam('totalAmount'));
        $payment -> setAttribute('isRecurring', $request -> getBodyParam('isRecurring') == 'true');
        $payment -> setAttribute('provider', (int)$request -> getBodyParam('provider'));
        $payment -> setAttribute('status', "STARTED");

        if( !$payment -> validate() ) {
            return [
                'error' => 'Nie udało się zapisać płatności. Niepoprawne dane.',
            ];
        } else {
            $payment -> save();
        }

        if ($request->getBodyParam('provider') == '1') {
            $response = $this->payuPayment($request, $payment);

            return $response->getResponse()->redirectUri;
        } else {
            $response = $this->paypalPayment($request, $payment);
            
            return $response;
        }

        return true;
    }

    private function payuStatus($data) {
        $status = json_decode($data,true)['order']['status'];
        $id = json_decode($data,true)['order']['extOrderId'];
        $email = json_decode($data,true)['order']['buyer']['email'];

        $payment = Payment::findOne(['uid'=>$id]);
        $payment->status = $status;
        $payment->save();
        $this->sendEmail($email);
    }

    private function paypalStatus($data) {
        $json = json_decode(json_decode(json_encode($data)));
        $file = fopen("paypalstatus.txt", 'w');
        fwrite($file, $data);


//        $id = json_decode($data, true)['id'];
//        $subId = json_decode($data, true)['resource']['id'];
//        $email = json_decode($data,true)['resource']['purchase_units']['payee']['email_address'];
//        $status = json_decode($data,true)['resource']['status'];
        $id = $json->resource->purchase_units[0]->custom_id;
//        $subId = json_decode($data, true)['resource']['id'];
        $email = $json->resource->purchase_units[0]->payee->email_address;
        $status = $json->resource->status;
        $payment = Payment::findOne(['uid'=>$id]);
        if($status == "APPROVED") {
            $payment->status = "COMPLETED";
        } else {
            $payment->status = $status;
        }
        $payment->save();
        $this->sendEmail($payment->email);

        $clientId = Craft::$app->config->general->paypalId;
        $clientSecret = Craft::$app->config->general->paypalSecret;
        $env;
        if (Craft::$app->config->general->paypalSandbox) {
            $env = new SandBoxEnvironment($clientId, $clientSecret);
        } else {
            $env = new ProductionEnvironment($clientId, $clientSecret);
        }
        $client = new PayPalHttpClient($env);
        $request = new OrdersCaptureRequest($json->resource->id);
        $request->prefer('return=representation');
        try {
            $response = $client->execute($request);
        }catch (HttpException $ex) {
            return $ex->getMessage();
        }

        return $response;

        // return $payment->email;
    }

    public function checkPaypalSubStatus($request) {
        $body = file_get_contents('php://input');
        $data = trim($body);
        $json = json_decode($data);
        $file = fopen("paypalSubStatus.txt", 'w');
        fwrite($file, $data);

        $id = $json->resource->id;
        $status = $json->resource->status;
        $email = json_decode($data,true)['resource']['subscriber']['email_address'];

        $command = Yii::$app->db->createCommand("UPDATE pmmpayments_tokens SET status='".$status."' where token='".$id."'");
        $command->execute();
//        $this->sendEmail($email,
//            "<br><br><p style='font-size: 10px; color: lightgray'>Anulowanie subskrypcji:
//            ".Craft::$app->config->general->cancelSubscription."/?id=".$id."</p>");

        return $json;
    }

    public function checkPayuStatus($request) {
        $body = file_get_contents('php://input');
        $data = trim($body);
        $json = json_decode(json_decode(json_encode($data)));
        $file = fopen("paypal.txt", 'w');
        fwrite($file, $data);

        $body = file_get_contents('php://input');
        $data = trim($body);

        return $this->payuStatus($data);
    }

    public function checkPaypalStatus($request) {
        $body = file_get_contents('php://input');
        $data = trim($body);
        $json = json_decode(json_decode(json_encode($data)));
        $file = fopen("paypal.txt", 'w');
        fwrite($file, $data);

        $body = file_get_contents('php://input');
        $data = trim($body);
        return $this->paypalStatus($data);
    }

    private function getPaypalAuth($clientId, $clientSecret) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Craft::$app->config->general->paypalUrl."v1/oauth2/token");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$clientId:$clientSecret");
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response,true)['access_token'];
    }

    public function getAllMonthsTotal($projectFilter = "%", $startRangeFilter = "%", $endRangeFilter = "%") {
        // $sql = "SELECT YEAR(dateCreated) as year,
        //     MONTH(dateCreated) as month,
        //     SUM(amount) AS total
        //     FROM pmmpayments_payment
        //     WHERE (project LIKE '%$projectFilter%') AND (MONTH(dateCreated) LIKE '%$monthFilter%') AND (YEAR(dateCreated) LIKE '%$yearFilter%') AND status='COMPLETED'
        //     GROUP BY YEAR(dateCreated), MONTH(dateCreated)
        // ";
        $sql = "SELECT YEAR(dateCreated) as year,
            MONTH(dateCreated) as month,
            SUM(amount) AS total
            FROM pmmpayments_payment
            WHERE (project LIKE '%$projectFilter%')
        ";

        if ($startRangeFilter && $endRangeFilter) {
            $sql .= " AND (dateCreated BETWEEN '$startRangeFilter' AND '$endRangeFilter')";
        }
        $sql .= " AND status='COMPLETED'
        GROUP BY YEAR(dateCreated), MONTH(dateCreated)";

        $activeDataProvider = new SqlDataProvider([
            'sql' => $sql,
            'pagination' => [
                'pageSize' => false
            ],
        ]);

        $totals = $activeDataProvider->getModels();
        return $totals;
    }

    public function getPayUPayments($page, $sordBy, $sortOrder, $projectFilter, $startRangeFilter, $endRangeFilter)
    {
        $provider = 1;
        return $this -> getEntries($provider, $page, $sordBy, $sortOrder, $projectFilter, $startRangeFilter, $endRangeFilter);
    }

    public function getPayPalPayments($page, $sordBy, $sortOrder, $projectFilter, $startRangeFilter, $endRangeFilter)
    {
        $provider = 2;
        return $this -> getEntries($provider, $page, $sordBy, $sortOrder, $startRangeFilter, $endRangeFilter);
    }

    public function getSortOptions()
    {
        return [
            ['value' => 'firstName', 'label' => 'Imię'],
            ['value' => 'lastName', 'label' => 'Nazwisko'],
            ['value' => 'email', 'label' => 'Email'],
            ['value' => 'amount', 'label' => 'Kwota'],
            ['value' => 'dateCreated', 'label' => 'Data płatności', 'selected' => true],
        ];
    }

    public function getProjectFilterOptions()
    {
        $activeDataProvider = new SqlDataProvider([
            'sql' => "SELECT DISTINCT(project) FROM pmmpayments_payment",
        ]);

        $projects= $activeDataProvider->getModels();

        return $projects;
    }

    public function getYearFilterOptions()
    {
        $activeDataProvider = new SqlDataProvider([
            'sql' => "SELECT DISTINCT(YEAR(dateCreated)) AS year FROM pmmpayments_payment",
        ]);

        $years= $activeDataProvider->getModels();

        return $years;
    }

    public function getMonthFilterOptions()
    {
        return [
            "Styczeń", "Luty", "Marzec", "Kwiecień", "Maj", "Czerwiec", "Lipiec", "Sierpień", "Wrzesień", "Październik", "Listopad", "Grudzień"
        ];
    }

    public function exportCsv($providerName)
    {
        $provider = $this -> getProviderByName($providerName);
        $entries = Payment :: find() -> where(['provider' => $provider]) -> andWhere(['and', "status='COMPLETED'"]) -> asArray() -> all();

        $writer = Writer :: createFromFileObject(new SplTempFileObject());
        $writer -> insertOne(array_keys($entries[0]));
        $writer -> insertAll($entries);
        $writer -> output('payments-' . $providerName . '.csv');
        exit(0);
    }

    private function getProviderByName($providerName)
    {
        $provider = 3;
        switch($providerName) {
            case 'payu':
                $provider = 1;
                break;
            case 'paypal':
                $provider = 2;
                break;
        }
        return $provider;
    }

    private function getEntries($provider, $page, $sortBy = 'dateCreated', $sortOrder = 'DESC', $projectFilter = null, $startRangeFilter = null, $endRangeFilter = null)
    {
        $firstDayOfThisMonth = date('Y-m-01 00:00:00');
        $lastDayOfThisMonth = date('Y-m-t 12:59:59');
        $firstDayOfThisYear = date('Y-01-01 00:00:00');
        $lastDayOfThisYear = date('Y-12-31 00:00:00');
        $entriesMonth = Payment :: find() -> where(['provider' => $provider]) -> andWhere(['and', "status='COMPLETED'", "dateCreated >= '$firstDayOfThisMonth'", "dateCreated <= '$lastDayOfThisMonth'"]) -> asArray() -> all();
        $entriesYear = Payment :: find() -> where(['provider' => $provider]) -> andWhere(['and', "status='COMPLETED'", "dateCreated >= '$firstDayOfThisYear'", "dateCreated <= '$lastDayOfThisYear'"]) -> asArray() -> all();
        $entriesTotal = Payment :: find() -> where(['provider' => $provider]) -> andWhere(['and', "status='COMPLETED'"]) -> asArray() -> all();
//        $query = Payment :: find() -> where(['provider' => $provider]);
        $filters = ['and', "status = 'COMPLETED'"];
        $query;


        if ($projectFilter) {
            array_push($filters, "project = '$projectFilter'");
        }
        if ($startRangeFilter && $endRangeFilter) {
            $start = date($startRangeFilter);
            $end = date("Y-m-d", strtotime($endRangeFilter.' +1 day'));
            $query = Payment::find()->where(['provider' => $provider])->andWhere($filters)->andWhere(["between", "dateCreated", $start, $end]);
        } else {
            $query = Payment::find()->where(['provider' => $provider])->andWhere($filters);
        }



        $activeDataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => self :: ENTRIES_ON_PAGE,
                'page' => $page - 1
            ],
            'sort' => [
                'defaultOrder' => [
                    $sortBy => $sortOrder === 'DESC' ? SORT_DESC: SORT_ASC,
                    'dateCreated' => SORT_DESC,
                ]
            ]
        ]);

        $entries = $activeDataProvider -> getModels();
        $countFrom = self :: ENTRIES_ON_PAGE* ($page - 1) + 1;
        $countTo = $countFrom + count($entries) - 1;
        $countAll = $activeDataProvider -> getTotalCount();


        // {# <td>{{ row.isRecurring == '0' ? 'Płatność jednorazowa' : 'Płatność cykliczna' }}</td> #}
        return [
            'columns' => $this -> getColumns(),
            'entries' => array_map("self::mapEntries", $entries),
            'sum' => array_reduce($entries, "self::sum"),
            'sumMonth' => array_reduce($entriesMonth, "self::sum"),
            'sumYear' => array_reduce($entriesYear, "self::sum"),
            'sumTotal' => array_reduce($entriesTotal, "self::sum"),
            'sumFilter' => array_reduce($query->asArray()->all(), "self::sum"),
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'page' => $page,
            'isPrevPage' => $countFrom > self :: ENTRIES_ON_PAGE,
            'isNextPage' => $countTo < $countAll,
            'countFrom' => $countFrom,
            'countTo' => $countTo,
            'countAll' => $countAll,
        ];
    }

    static function mapEntries($entry)
    {
        $entry -> isRecurring = $entry -> isRecurring ? 'Płatność cykliczna' : 'Płatność jednorazowa' ;
        return $entry;
    }

    private function getColumns()
    {
        return [
            ['label' => 'Projekt', 'key' => 'project'],
            ['label' => 'Tytuł', 'key' => 'title'],
            ['label' => 'Imię', 'key' => 'firstName'],
            ['label' => 'Nazwisko', 'key' => 'lastName'],
            ['label' => 'Email', 'key' => 'email'],
            ['label' => 'Kwota', 'key' => 'amount'],
            // ['label' => 'Rodzaj', 'key' => 'isRecurring'],
            ['label' => 'Data płatności', 'key' => 'dateCreated']
        ];
    }

    static function sum($carry, $item)
    {
        $carry += $item['amount'];
        return $carry;
    }

}

