<?php

namespace pleodigital\pmmpayments\services;

use craft\records\Entry;
use craft\web\View;
use OpenPayU_Configuration;
use OpenPayU_Order;
use OpenPayU_Retrieve;
use OpenPayU_Token;
use OauthGrantType;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Core\AccessTokenRequest;
use PayPalHttp\HttpClient;
use pleodigital\pmmpayments\models\Settings;
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



    public function sendEmail($email, $isNotification = false, $additives = '', $name = '', $project = '') {
//        return Craft::$app->plugins->getPlugin("pmm-payments")->getSettings()["notifyEmail"];
//        Craft::$app->getMailer()->compose()->setTo($email)->setSubject('Polska Misja Medyczna')->setHtmlBody("
//            <p>Drogi Darczyńco!</p>
//                <p>Dziękujemy za Twoją wpłatę! Twoja darowizna to realna pomoc dla kobiet i dzieci, która pomoże zmienić
//                 ich życie na lepsze. Prowadzimy wiele działań na całym świecie. Nie byłoby to możliwe bez Twojego
//                  wsparcia. Razem budujemy pomoc!
//                </p>
//                <p>Dziękujemy!</p>
//                <p>Zespół Polskiej Misji Medycznej</p>
//        ".$additives)->send();

        $text = $isNotification ? Craft::$app->plugins->getPlugin("pmm-payments")->getSettings()["notifyEmail"] :
            Craft::$app->plugins->getPlugin("pmm-payments")->getSettings()["paymentEmail"];
        $search = "/[{]{2}(imie)[}]{2}/";
        $text = preg_replace($search,$name,$text);
        $search = "/[{]{2}(projekt)[}]{2}/";
        $text = preg_replace($search,$project,$text);
        Craft::$app
            ->getMailer()
            ->compose()
            ->setTo($email)
            ->setSubject('Polska Misja Medyczna')
            ->setTextBody($text.$additives)->send();
    }

    public function getSettingsHtml() {
        return Craft::$app->plugins->getPlugin("pmm-payments")->settingsHtml();
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
                Payu::instance()->monthlyPayment();
            } else {
                $result[] = Paypal::instance()->monthlyPayment($field);
            }
        }

//        return $totals;
        return $result;
    }

    public function processRequestData($request)
    {
        $response;
//        $payment = new Payment();
//        $payment -> setAttribute('project', $request -> getBodyParam('project'));
//        $payment -> setAttribute('title', $request -> getBodyParam('title'));
//        $payment -> setAttribute('firstName', $request -> getBodyParam('firstName'));
//        $payment -> setAttribute('lastName', $request -> getBodyParam('lastName'));
//        $payment -> setAttribute('email', $request -> getBodyParam('email'));
//        $payment -> setAttribute('amount', $request -> getBodyParam('totalAmount'));
//        $payment -> setAttribute('isRecurring', $request -> getBodyParam('isRecurring') == 'true');
//        $payment -> setAttribute('provider', (int)$request -> getBodyParam('provider'));
//        $payment -> setAttribute('status', "STARTED");
//
//        if( !$payment -> validate() ) {
//            return [
//                'error' => 'Nie udało się zapisać płatności. Niepoprawne dane.',
//            ];
//        } else {
//            $payment -> save();
//        }

        if ($request->getBodyParam('provider') == '1') {
            $response = Payu::instance()->makePayment(false, $request);
        
            return $response;
        } else {
            $response = Paypal::instance()->payment($request);
            
            return $response;
        }

        return true;
    }

    public function getAllMonthsTotal($projectFilter = "%", $startRangeFilter = "%", $endRangeFilter = "%", $paymentType = 3) {
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
        if ($paymentType != 3) {
            $sql .= " AND isRecurring = '$paymentType'";
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

    public function getPayUPayments($page, $sortBy, $sortOrder, $projectFilter, $startRangeFilter, $endRangeFilter, $paymentTypeFilter)
    {
        $provider = 1;
        return $this -> getEntries($provider, $page, $sortBy, $sortOrder, $projectFilter, $startRangeFilter, $endRangeFilter, $paymentTypeFilter);
    }

    public function getPayPalPayments($page, $sortBy, $sortOrder, $projectFilter, $startRangeFilter, $endRangeFilter, $paymentTypeFilter)
    {
        $provider = 2;
        return $this -> getEntries($provider, $page, $sortBy, $sortOrder, $projectFilter, $startRangeFilter, $endRangeFilter, $paymentTypeFilter);
    }

    public function getRecursivePayments($page, $sortBy, $sortOrder, $projectFilter, $startRangeFilter, $endRangeFilter)
    {
        $active = 1;
        return $this -> getRecursiveEntries($active, $page, $sortBy, $sortOrder, $projectFilter, $startRangeFilter, $endRangeFilter);
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

    public function exportCsv($provider, $projectFilter, $startRangeFilter, $endRangeFilter, $paymentTypeFilter)
    {
        $filters = ['and', "status = 'COMPLETED'"];
        $query;


        if ($projectFilter) {
            array_push($filters, "project = '$projectFilter'");
        }

        if ($paymentTypeFilter == 0 || $paymentTypeFilter == 1) {
            array_push($filters, "isRecurring = '$paymentTypeFilter'");
        }
        if ($startRangeFilter && $endRangeFilter) {
            $start = date($startRangeFilter);
            $end = date("Y-m-d", strtotime($endRangeFilter.' +1 day'));
            $query = Payment::find()->where(['provider' => $provider])->andWhere($filters)->andWhere(["between", "dateCreated", $start, $end]) -> asArray() -> all();;
        } else {
            $query = Payment::find()->where(['provider' => $provider])->andWhere($filters) -> asArray() -> all();;
        }

        // $query = Payment :: find() -> where(['provider' => $provider]) -> andWhere(['and', "status='COMPLETED'"]) -> asArray() -> all();

        $writer = Writer :: createFromFileObject(new SplTempFileObject());
        $writer -> insertOne(array_keys($query[0]));
        $writer -> insertAll($query);
        $writer -> output('payments-' . $this->getNameByProviderId($provider) . '.csv');
        exit(0);
    }

    private function getNameByProviderId($providerName)
    {
        $provider = 3;
        switch($providerName) {
            case 1:
                $provider = "payu";
                break;
            case 2:
                $provider = "paypal";
                break;
        }
        return $provider;
    }

    private function getRecursiveEntries($active,
                                         $page,
                                         $sortBy = 'dateCreated',
                                         $sortOrder = 'DESC',
                                         $projectFilter = null,
                                         $startRangeFilter = null,
                                         $endRangeFilter = null
    ) {

        $firstDayOfThisMonth = date('Y-m-01 00:00:00');
        $lastDayOfThisMonth = date('Y-m-t 12:59:59');
        $firstDayOfThisYear = date('Y-01-01 00:00:00');
        $lastDayOfThisYear = date('Y-12-31 00:00:00');
        $entriesMonth = Recurring :: find() -> where(['active' => $active]) -> andWhere(['and', "dateCreated >= '$firstDayOfThisMonth'", "dateCreated <= '$lastDayOfThisMonth'"]) -> asArray() -> all();
        $entriesYear = Recurring :: find() -> where(['active' => $active]) -> andWhere(['and', "dateCreated >= '$firstDayOfThisYear'", "dateCreated <= '$lastDayOfThisYear'"]) -> asArray() -> all();
        $entriesTotal = Recurring :: find() -> where(['active' => $active]) -> asArray() -> all();
//        $query = Payment :: find() -> where(['provider' => $provider]);
        $filters = ['and', "active = 1"];
        $query;


        if ($projectFilter) {
            array_push($filters, "project = '$projectFilter'");
        }
        if ($startRangeFilter && $endRangeFilter) {
            $start = date($startRangeFilter);
            $end = date("Y-m-d", strtotime($endRangeFilter.' +1 day'));
            $query = Recurring::find()->where(['active' => $active])->andWhere($filters)->andWhere(["between", "dateCreated", $start, $end]);
        } else {
            $query = Recurring::find()->where(['active' => $active])->andWhere($filters);
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
            'columns' => $this -> getRecursiveColumns(),
            'entries' => $entries,
        //     'isRecurring' => true,
        //    'sum' => array_reduce($entries, "self::sum"),
        //    'sumMonth' => array_reduce($entriesMonth, "self::sum"),
        //    'sumYear' => array_reduce($entriesYear, "self::sum"),
        //    'sumTotal' => array_reduce($entriesTotal, "self::sum"),
        //    'sumFilter' => array_reduce($query->asArray()->all(), "self::sum"),
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


    public function cancelSubscription($id) {
        $recursivePayment = Recurring::findOne(["cancelHash" => $id]);
        // exit(print_r($recursivePayment,true));
        $recursivePayment->active = 0;

        if ($recursivePayment->provider == 1) {
            $token = Payu::instance()->getCardToken($recursivePayment);

            // TODO: request na usuwanie otrzymanego tokenu z payu
            // return OpenPayu_Token::delete($token[0]);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://secure.payu.com/api/v2_1/tokens/".$token[0]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$token[1]));
            $response = curl_exec($ch);
            curl_close($ch);
            // return $response;
        }

        $recursivePayment->save();

        // return $recursivePayment;
        Craft :: $app -> getResponse() -> redirect(Craft :: $app -> config -> general -> payUPaymentCancelPage);

    }


    private function getEntries($provider,
                                $page,
                                $sortBy = 'dateCreated',
                                $sortOrder = 'DESC',
                                $projectFilter = null,
                                $startRangeFilter = null,
                                $endRangeFilter = null,
                                $paymentType = null
    ) {

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
        // echo print($projectFilter);
        if ($paymentType == 0 || $paymentType == 1) {
            array_push($filters, "isRecurring = '$paymentType'");
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
            'columns' => $this -> getPaymentColumns(),
            'entries' => $entries,
            'isRecurring' => false,
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
        $arr = $entry;
        $arr -> isRecurring = $entry -> isRecurring ? 'Płatność cykliczna' : 'Płatność jednorazowa' ;
        return $arr;
    }

    private function getPaymentColumns()
    {
        return [
            ['label' => 'Projekt', 'key' => 'project'],
            ['label' => 'Tytuł', 'key' => 'title'],
            ['label' => 'Imię', 'key' => 'firstName'],
            ['label' => 'Nazwisko', 'key' => 'lastName'],
            ['label' => 'Email', 'key' => 'email'],
            ['label' => 'Kwota', 'key' => 'amount'],
            ['label' => 'Rodzaj', 'key' => 'isRecurring'],
            ['label' => 'Data płatności', 'key' => 'dateCreated']
        ];
    }
    private function getRecursiveColumns()
    {
        return [
            ['label' => 'Projekt', 'key' => 'project'],
            ['label' => 'Imię', 'key' => 'firstName'],
            ['label' => 'Nazwisko', 'key' => 'lastName'],
            ['label' => 'Email', 'key' => 'email'],
            ['label' => 'Kwota', 'key' => 'amount'],
            // ['label' => 'Rodzaj', 'key' => 'isRecurring'],
            ['label' => 'Data płatności', 'key' => 'dateCreated'],
            ['label' => '', 'key' => 'id']
        ];
    }

    static function sum($carry, $item)
    {
        $carry += $item['amount'];
        return $carry;
    }

}

