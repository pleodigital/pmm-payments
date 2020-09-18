<?php


namespace pleodigital\pmmpayments\controllers;


use craft\web\Controller;
use pleodigital\pmmpayments\Pmmpayments;

class PaypalController extends Controller
{
    protected $allowAnonymous = ['check-status', 'check-sub', 'active-sub', 'ed2a2a984c0289c0a1ddb44029121aae'];

    public function actionCheckStatus()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> paypal -> checkStatus( $request );

            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Saving data went wrong.';
        }
    }

    public function actionCheckSub()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> paypal -> checkSubStatus( $request );

            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Saving data went wrong.';
        }
    }

    public function actionActivateSub()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> paypal -> checkActivation( $request );

            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Saving data went wrong.';
        }
    }

//    public function actionMonthlyPayment()
//    {
//        try {
//            $request = Craft :: $app -> getRequest();
//            $response = Pmmpayments :: $plugin -> payments -> monthlyPayment( $request );
//
//            return $this -> asJson($response);
//        } catch (Exception $e) {
//            return 'Exporting went wrong.';
//        }
//    }

    public function actionEd2a2a984c0289c0a1ddb44029121aae()
    {
        try {
            $response = Pmmpayments :: $plugin -> paypal -> checkMonthlyPayments();
            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Saving data went wrong.';
        }
    }
}