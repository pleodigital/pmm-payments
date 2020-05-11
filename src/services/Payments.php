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

use OpenPayU_Configuration;
use OpenPayU_Order;
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
//        $command = Yii::$app->db->createCommand();
//        $command->update('pmmpayments_payment', array(
//            'status'=>"COMPLETED",
//        ), 'uid=:uid', array(':uid'=>'1b3dd04a-c9cb-41d1-80ca-a1693153f510'));

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
        }
        OpenPayU_Configuration::setEnvironment('secure');
        OpenPayU_Configuration::setMerchantPosId('145227');
        OpenPayU_Configuration::setSignatureKey('13a980d4f851f3d9a1cfc792fb1f5e50');
        OpenPayU_Configuration::setOauthClientId('145227');
        OpenPayU_Configuration::setOauthClientSecret('12f071174cb7eb79d4aac5bc2f07563f');

        // Check our Plugin's settings for `someAttribute`
        // Pmmpayments::$plugin->getSettings()->someAttribute
        $order['continueUrl'] = 'http://localhost/'; //customer will be redirected to this page after successfull payment
        $order['notifyUrl'] = 'http://craft.polska-misja-medyczna.pleodev.usermd.net/actions/pmm-payments/payments/check-status';
        $order['customerIp'] = $_SERVER['REMOTE_ADDR'];
        $order['merchantPosId'] = OpenPayU_Configuration::getMerchantPosId();
        $order['description'] = $request -> getBodyParam('project');
        $order['currencyCode'] = $request -> getBodyParam('currencyCode');
        $order['totalAmount'] = $request -> getBodyParam('totalAmount')*100;
        $order['extOrderId'] = $payment->uid;

        $order['products'][0]['name'] = $request -> getBodyParam('project');
        $order['products'][0]['unitPrice'] = $request -> getBodyParam('totalAmount')*100;
        $order['products'][0]['quantity'] = 1;

        $order['buyer']['email'] = $request -> getBodyParam('email');
        $order['buyer']['firstName'] = $request -> getBodyParam('firstName');
        $order['buyer']['lastName'] = $request -> getBodyParam('lastName');
        $order['buyer']['language'] = $request -> getBodyParam('language');

        $response = OpenPayU_Order::create($order);
//        return print_r($response);
//        header('Location:'.$response->getResponse()->redirectUri);
        
        Craft::$app->getMailer()->compose()->setTo($order['buyer']['email'])->setSubject('Polska Misja Medyczna')->setHtmlBody("
        <p>Drogi Darczyńco!</p>
        <p>Twoja darowizna to lekarstwa dla niemowląt w Zambii, paczka żywnościowa dla rodziny syryjskich uchodźców lub pomoc medyczna dla najuboższych kobiet w Senegalu.
        Prowadzimy wiele działań na całym świecie. Nie byłoby to możliwe bez Twojej wpłaty. Razem budujemy pomoc.</p>
        <p>Dziękujemy!</p>
        <p>Zespół Polskiej Misji Medycznej</p>
        ")->send();
        
        return $response->getResponse();

    }

    public function checkStatus($request) {
        $body = file_get_contents('php://input');
        $data = trim($body);
        $json = json_decode(json_decode(json_encode($data)));
        $status = json_decode($data,true)['order']['status'];
        $id = json_decode($data,true)['order']['extOrderId'];

        $payment = Payment::findOne(['uid'=>$id]);
        $payment->status = $status;
        $payment->save();
    }

    public function getAllMonthsTotal($projectFilter = "%", $yearFilter = "%", $monthFilter = "%") {
        $sql = "SELECT YEAR(dateCreated) as year,
            MONTH(dateCreated) as month,
            SUM(amount) AS total
            FROM pmmpayments_payment
            WHERE (project LIKE '%$projectFilter%') AND (MONTH(dateCreated) LIKE '%$monthFilter%') AND (YEAR(dateCreated) LIKE '%$yearFilter%') AND status='COMPLETED'
            GROUP BY YEAR(dateCreated), MONTH(dateCreated)
        ";

        $activeDataProvider = new SqlDataProvider([
            'sql' => $sql,
            'pagination' => [
                'pageSize' => false
            ],
        ]);

        $totals = $activeDataProvider->getModels();
        return $totals;
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

    private function getEntries($provider, $page, $sortBy = 'dateCreated', $sortOrder = 'DESC', $projectFilter = null, $yearFilter = null, $monthFilter = null)
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


        if ($projectFilter) {
            array_push($filters, "project = '$projectFilter'");
        }
        if ($yearFilter) {
            array_push($filters, "YEAR(dateCreated) = '$yearFilter'");
        }
        if ($monthFilter) {
            array_push($filters, "MONTH(dateCreated) = '$monthFilter'");
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
