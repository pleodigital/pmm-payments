<?php
/**
 * pmm-payments plugin for Craft CMS 3.x
 *
 * Payment processing plugin for the Polish Medical Mission
 *
 * @link      https://pleodigital.com/
 * @copyright Copyright (c) 2020 Pleo Digtial
 */

namespace pleodigital\pmmpayments\services;

use pleodigital\pmmpayments\Pmmpayments;

use Craft;
use craft\base\Component;
use craft\helpers\Json;
use pleodigital\pmmpayments\records\Payment;

/**
 * Payments Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Pleo Digtial
 * @package   Pmmpayments
 * @since     1.0.0
 */
class Payments extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * This function can literally be anything you want, and you can have as many service
     * functions as you want
     *
     * From any other plugin file, call it like this:
     *
     *     Pmmpayments::$plugin->payments->processRequestData()
     *
     * @return mixed
     */
    public function processRequestData($request)
    {
        // Check our Plugin's settings for `someAttribute`
        // Pmmpayments::$plugin->getSettings()->someAttribute

        $payment = new Payment();
        $payment -> setAttribute('project', $request -> getBodyParam('project'));
        $payment -> setAttribute('firstName', $request -> getBodyParam('firstName'));
        $payment -> setAttribute('lastName', $request -> getBodyParam('lastName'));
        $payment -> setAttribute('email', $request -> getBodyParam('email'));
        $payment -> setAttribute('amount', $request -> getBodyParam('amount'));
        $payment -> setAttribute('isRecurring', $request -> getBodyParam('isRecurring') === 'true');
        $payment -> setAttribute('provider', (int)$request -> getBodyParam('provider'));

        if( !$payment -> validate() ) {
            return [
                'error' => 'Nie udało się zapisać płatności. Niepoprawne dane.',
            ];
        } else {
            $payment -> save();
            return [
                'message' => 'Pomyślnie zapisano płatność.',
            ];
        }
    }

    public function getPayUPayments()
    {
        return Payment :: find() -> where(['provider' => 1]) -> all();
    }

    public function getPayPalPayments()
    {
        return Payment :: find() -> where(['provider' => 2]) -> all();
    }

}
