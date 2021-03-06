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
use pleodigital\pmmpayments\services\Payments;
use pleodigital\pmmpayments\services\Payu;
use yii\db\Exception;

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
    protected $allowAnonymous = ['index', 'export-csv', 'cancel-subscription'];

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

    public function actionCancelSubscription() {
        try {
            $request = Craft::$app->getRequest();
            $response = Pmmpayments::$plugin->payments->cancelSubscription($request->get("id"));
            return $this -> asJson($response);
        } catch (Exception $e) {
            return "Deleting data went wrong";
        }
    }

    public function actionExportCsv()
    {
        try {
            $request = Craft :: $app -> getRequest();
            $response = Pmmpayments :: $plugin -> payments -> exportCsv(
                $request -> getQueryParam('provider'),
                $request -> getQueryParam("project"),
                $request -> getQueryParam("startRange"),
                $request -> getQueryParam("endRange"),
                $request -> getQueryParam("paymentType"),
            );
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
