<?php


namespace pleodigital\pmmpayments\services;

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Core\AccessTokenRequest;
use PayPalHttp\HttpClient;
use DateTime;
use Craft;
use Yii;
use pleodigital\pmmpayments\records\Payment;
use craft\base\Component;

class Paypal extends Component
{
    public function checkActivation($request) {
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
        Payments::instance()->sendEmail($email,
            "<br><br><p style='font-size: 10px; color: lightgray'>Anulowanie subskrypcji:
            ".Craft::$app->config->general->cancelSubscription."/?id=".$id."</p>");

        return $json;
    }


    public function monthlyPayment($field) {
        $id = $field['token'];
        $clientId = Craft::$app->config->general->paypalId;
        $clientSecret = Craft::$app->config->general->paypalSecret;
        $token = $this->getAuth($clientId, $clientSecret);

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

                Payments::instance()->sendEmail($field['email'],
                    "<br><br><p style='font-size: 10px; color: lightgray'>Anulowanie subskrypcji:
                    ".Craft::$app->config->general->cancelSubscription."/?id=".$response->id."</p>", false, $field["firstName"], $field["project"]);
            }
        } else {
            $sql = "UPDATE pmmpayments_tokens SET status='CANCELLED' WHERE token='$id'";
            $command = Yii::$app->db->createCommand($sql);
            $command->execute();
//            return "error";
        }


        return $response;
    }

    public function checkStatus($request) {
        $body = file_get_contents('php://input');
        $data = trim($body);
        $json = json_decode(json_decode(json_encode($data)));
        $file = fopen("paypal.txt", 'w');
        fwrite($file, $data);

        $body = file_get_contents('php://input');
        $data = trim($body);
        return $this->getStatus($data);
    }

    private function getAuth($clientId, $clientSecret) {
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

    private function getStatus($data) {
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
        Payments::instance()->sendEmail($payment->email, "", false, $payment->firstName, $payment->project);

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

    public function checkSubStatus($request) {
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


    public function cancelSubscription($request) {
        $id = $_GET['id'];
        $clientId = Craft::$app->config->general->paypalId;
        $clientSecret = Craft::$app->config->general->paypalSecret;
        $token = $this->getAuth($clientId, $clientSecret);

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

    public function payment($request) {
        $fp = fopen('res.txt', 'w');
        fwrite($fp, json_encode($request->bodyParams));
        fclose($fp);

        $payment = new Payment();
        $payment -> setAttribute('project', $request -> getBodyParam("project"));
        $payment -> setAttribute('title', $request -> getBodyParam("title"));
        $payment -> setAttribute('firstName', $request -> getBodyParam("firstName"));
        $payment -> setAttribute('lastName', $request -> getBodyParam("lastName"));
        $payment -> setAttribute('email', $request -> getBodyParam("email"));
        $payment -> setAttribute('amount', $request -> getBodyParam("totalAmount"));
        $payment -> setAttribute('currency', $request -> getBodyParam("currencyCode"));
        $payment -> setAttribute('language', $request -> getBodyParam("language"));
        $payment -> setAttribute('isRecurring', false);
        $payment -> setAttribute('recurringId', $request -> getBodyParam("id") ? $request->getBodyParam("id") : 0);
        $payment -> setAttribute('provider', 2);
        $payment -> setAttribute('status', "WAITING");
        $payment -> save();

        if ($request->getBodyParam('isRecurring') == 'true') {
            $payment->status = "COMPLETED";
            $payment->save();
            return $this->setRecurring($request);

        } else {
            $clientId = Craft::$app->config->general->paypalId;
            $clientSecret = Craft::$app->config->general->paypalSecret;
            // echo print_r(Craft::$app->config->general,true);
            

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

    private function setRecurring($request) {
        $clientId = Craft::$app->config->general->paypalId;
        $clientSecret = Craft::$app->config->general->paypalSecret;
        $token = $this->getAuth($clientId, $clientSecret);

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

}