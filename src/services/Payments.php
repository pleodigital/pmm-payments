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
use yii\data\ActiveDataProvider;
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
    const ENTRIES_ON_PAGE = 50;
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

    public function getPayUPayments($page)
    {
        $provider = 1;
        return $this -> getEntries($provider, $page);
    }

    public function getPayPalPayments($page)
    {
        $provider = 2;
        return $this -> getEntries($provider, $page);
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

    private function getEntries($provider, $page)
    {  
        // -> limit( self :: ENTRIES_ON_PAGE )
        // -> offset( self :: ENTRIES_ON_PAGE * ($page - 1) )

        $provider = new ActiveDataProvider([
            'query' => Payment :: find() -> where(['provider' => $provider]), 
            'pagination' => [
                'pageSize' => self :: ENTRIES_ON_PAGE,
                'page' => $page - 1
            ],
        ]);
        
        $entries = $provider -> getModels();
        $countFrom = self :: ENTRIES_ON_PAGE * ($page - 1) + 1;
        $countTo = $countFrom + count($entries) - 1; 
        $countAll = $provider -> getTotalCount();

        return [
            'entries' => $entries, 
            'sum' => array_reduce($entries, "self::sum"),
            'page' => $page,
            'isPrevPage' => $countFrom > self :: ENTRIES_ON_PAGE,
            'isNextPage' => $countTo < $countAll,
            'countFrom' => $countFrom,
            'countTo' => $countTo,
            'countAll' => $countAll,
        ];
    }

    static function sum($carry, $item)
    { 
        $carry += $item -> amount;
        return $carry;
    }

}
