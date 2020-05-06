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
use Yii;
use yii\data\ActiveDataProvider;
use League\Csv\Writer;
use SplTempFileObject;
use craft\base\Component;
use craft\helpers\Json;
use pleodigital\pmmpayments\records\Payment;
use yii\data\SqlDataProvider;

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
        $payment -> setAttribute('title', $request -> getBodyParam('title'));
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

    public function getPayUPayments($page, $sordBy, $sortOrder, $projectFilter, $yearFilter, $monthFilter)
    {
        $provider = 1;
        return $this -> getEntries($provider, $page, $sordBy, $sortOrder, $projectFilter, $yearFilter, $monthFilter);
    }

    public function getPayPalPayments($page, $sordBy, $sortOrder, $projectFilter, $yearFilter, $monthFilter)
    {
        $provider = 2;
        return $this -> getEntries($provider, $page, $sordBy, $sortOrder, $projectFilter, $yearFilter, $monthFilter);
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
        $entries = Payment :: find() -> where(['provider' => $provider]) -> asArray() -> all(); 

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

    private function getEntries($provider, $page, $sortBy = 'dateCreated', $sortOrder = 'DESC', $projectFilter = null, $yearFilter = null, $monthFilter = null)
    {  
        $firstDayOfThisMonth = date('Y-m-01 00:00:00');
        $lastDayOfThisMonth = date('Y-m-t 12:59:59');
        $firstDayOfThisYear = date('Y-01-01 00:00:00');
        $lastDayOfThisYear = date('Y-12-31 00:00:00');
        $entriesMonth = Payment :: find() -> where(['provider' => $provider]) -> andWhere(['and', "dateCreated >= '$firstDayOfThisMonth'", "dateCreated <= '$lastDayOfThisMonth'"]) -> asArray() -> all(); 
        $entriesYear = Payment :: find() -> where(['provider' => $provider]) -> andWhere(['and', "dateCreated >= '$firstDayOfThisYear'", "dateCreated <= '$lastDayOfThisYear'"]) -> asArray() -> all(); 
        $entriesTotal = Payment :: find() -> where(['provider' => $provider]) -> asArray() -> all(); 
//        $query = Payment :: find() -> where(['provider' => $provider]);
        $filters = ['and'];


        if ($projectFilter) {
            array_push($filters, "project = '$projectFilter'");
        }
        if ($yearFilter) {
            array_push($filters, "YEAR(dateCreated) = '$yearFilter'");
//            $query-> where(['YEAR(dateCreated)' => $yearFilter]);
        }
        if ($monthFilter) {
            array_push($filters, "MONTH(dateCreated) = '$monthFilter'");
//            $query-> where(['MONTH(dateCreated)' => $monthFilter]);
        }

        $query = Payment::find()->where(['provider' => $provider])->andWhere($filters);


        $activeDataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => self :: ENTRIES_ON_PAGE,
                'page' => $page - 1
            ],
            'sort' => [
                'defaultOrder' => [
                    $sortBy => $sortOrder === 'DESC' ? SORT_DESC : SORT_ASC,
                    'dateCreated' => SORT_DESC,
                ]
            ]
        ]);
//        $count = Yii::$app->db->createCommand('SELECT COUNT(*) FROM pmmpayments_payment WHERE provider=:provider', [':provider' => $provider])->queryScalar();
//        $activeDataProvider = new SqlDataProvider([
//            'sql' => "SELECT * FROM pmmpayments_payment WHERE provider=:provider",
//            'params' => [':provider' => $provider],
//            'totalCount' => $count,
//            'pagination' => [
//                'pageSize' => self :: ENTRIES_ON_PAGE,
//                'page' => $page - 1
//            ],
//            'sort' => [
//                'defaultOrder' => [
//                    $sortBy => $sortOrder === 'DESC' ? SORT_DESC : SORT_ASC,
//                    'dateCreated' => SORT_DESC,
//                ]
//            ]
//        ]);

        $entries = $activeDataProvider -> getModels();
        $countFrom = self :: ENTRIES_ON_PAGE * ($page - 1) + 1;
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
