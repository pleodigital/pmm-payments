<?php
/**
 * pmm-payments plugin for Craft CMS 3.x
 *
 * Payment processing plugin for the Polish Medical Mission
 *
 * @link      https://pleodigital.com/
 * @copyright Copyright (c) 2020 Pleo Digtial
 */

namespace pleodigital\pmmpayments\controllers;

use pleodigital\pmmpayments\Pmmpayments;

use Craft;
use craft\web\Controller;
use craft\helpers\Json;

/**
 * Payments Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Pleo Digtial
 * @package   Pmmpayments
 * @since     1.0.0
 */
class PaymentsController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['index', 'payu-end', 'check-payu-status', 'check-paypal-status', 'paypal-activate-sub', 'check-paypal-sub', 'paypal-monthly-payment', 'ed2a2a984c0289c0a1ddb44029121aae', 'ed2a2a984c0289c0a1ddb44029121abcd', 'cancel-subscription'];

    // Public Methods
    // =========================================================================

    /**
     * Handle a request going to our plugin's index action URL,
     * e.g.: actions/pmm-payments/payments
     *
     * @return mixed
     */
    public function actionIndex()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> payments -> processRequestData( $request );

            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Saving data went wrong.';
        }
    }

    public function actionExportCsv()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> payments -> exportCsv($request -> getQueryParam('provider'));
        } catch (Exception $e) {
            return 'Exporting went wrong.';
        }
    }

    public function actionPayuEnd()
    {   
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> payments -> payuPaymentRecursive( $request );

            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Saving data went wrong.';
        }
    }

    public function actionCheckPayuStatus()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> payments -> checkPayuStatus( $request );

            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Exporting went wrong.';
        }
    }

    public function actionCheckPaypalStatus()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> payments -> checkPaypalStatus( $request );

            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Exporting went wrong.';
        }
    }

    public function actionCheckPaypalSub()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> payments -> checkPaypalSubStatus( $request );

            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Exporting went wrong.';
        }
    }

    public function actionPaypalActivateSub()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> payments -> checkPaypalActivation( $request );

            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Exporting went wrong.';
        }
    }

//    public function actionPaypalMonthlyPayment()
//    {
//        try {
//            $request = Craft :: $app -> getRequest();
//            $response = Pmmpayments :: $plugin -> payments -> paypalMonthlyPayment( $request );
//
//            return $this -> asJson($response);
//        } catch (Exception $e) {
//            return 'Exporting went wrong.';
//        }
//    }

    public function actionEd2a2a984c0289c0a1ddb44029121aae()
    {
        try {
            $response = Pmmpayments :: $plugin -> payments -> checkMonthlyPayments();
            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Exporting went wrong.';
        }
    }

    public function actionEd2a2a984c0289c0a1ddb44029121abcd()
    {
        try {
            $response = Pmmpayments :: $plugin -> payments -> payuMonthlyPayment();
            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Exporting went wrong.';
        }
    }

    public function actionCancelSubscription()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> payments -> cancelSubscription( $request );

            return $this -> asJson($response);
        } catch (Exception $e) {
            return 'Exporting went wrong.';
        }
    }

    // /**
    //  * Handle a request going to our plugin's actionDoSomething URL,
    //  * e.g.: actions/pmm-payments/payments/do-something
    //  *
    //  * @return mixed
    //  */
    // public function actionDoSomething()
    // {
    //     $result = 'Welcome to the PaymentsController actionDoSomething() method';

    //     return $result;
    // }
}
