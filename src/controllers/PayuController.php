<?php


namespace pleodigital\pmmpayments\controllers;


use Craft;
use craft\web\Controller;
use pleodigital\pmmpayments\Pmmpayments;
use pleodigital\pmmpayments\services\Payments;

class PayuController extends Controller
{
    protected $allowAnonymous = ['end', 'check-status', 'ed2a2a984c0289c0a1ddb44029121abcd', 'cancel-subscription'];

    public function actionEnd()
    {
        try {
            $fp = fopen('git.txt', 'w');
            fwrite($fp, "0");
            fclose($fp);
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> payu -> paymentRecursive( $request );
            echo json_encode($request->bodyParams);
            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Saving data went wrong.';
        }
    }

    public function actionCheckStatus()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> payu -> checkStatus( $request );

            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Saving data went wrong.';
        }
    }

    public function actionEd2a2a984c0289c0a1ddb44029121abcd()
    {
        try {
            $response = Pmmpayments :: $plugin -> payu -> monthlyPayment();
            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Saving data went wrong.';
        }
    }

    public function actionCancelSubscription()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> payu -> cancelSubscription( $request );

            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Saving data went wrong.';
        }
    }
}