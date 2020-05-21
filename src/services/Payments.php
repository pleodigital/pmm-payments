<?php

namespace pleodigital\pmmpayments\services;

use craft\records\Entry;
use OpenPayU_Configuration;
use OpenPayU_Order;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use pleodigital\pmmpayments\Pmmpayments;

use Craft;
//use craft\elements\Entry;
use Yii;
use yii\data\ActiveDataProvider;
use League\Csv\Writer;
use SplTempFileObject;
use craft\base\Component;
use craft\helpers\Json;
use pleodigital\pmmpayments\records\Payment;
use yii\data\SqlDataProvider;

class Payments extends Component
{
    const ENTRIES_ON_PAGE= 50;

    private function sendEmail($email) {
        Craft::$app->getMailer()->compose()->setTo($email)->setSubject('Polska Misja Medyczna')->setHtmlBody("
            <p>Drogi Darczyńco!</p>
                <p>Twoja darowizna to lekarstwa dla niemowląt w Zambii, paczka żywnościowa dla rodziny syryjskich uchodźców lub pomoc medyczna dla najuboższych kobiet w Senegalu.
        Prowadzimy wiele działań na całym świecie. Nie byłoby to możliwe bez Twojej wpłaty. Razem budujemy pomoc.</p>
                <p>Dziękujemy!</p>
                <p>Zespół Polskiej Misji Medycznej</p>
        ")->send();
    }

    private function payuPayment($request, $payment) {
        $entry;
        $idWplaty;

        if($request->getBodyParam('craftId') == 0) {
            $tempEntry = \craft\elements\Entry::find()->section('moduly')->slug('moduł-wpłaty')->one()->modulWplatyPmm;

            foreach ($tempEntry as $subentry) {
                if ($subentry->payuDrugiKlucz) {
                    $entry = $subentry;
                }
            }
            OpenPayU_Configuration::setEnvironment('secure');
            OpenPayU_Configuration::setMerchantPosId($entry->identyfikatorWplaty);
            OpenPayU_Configuration::setSignatureKey($entry->payuDrugiKlucz);
            OpenPayU_Configuration::setOauthClientId($entry->identyfikatorWplaty);
            OpenPayU_Configuration::setOauthClientSecret($entry->payuOAuth);
        } else {
            $entry = \craft\elements\Entry::find()->id($request->getBodyParam('craftId'))->one();
            OpenPayU_Configuration::setEnvironment('secure');
            OpenPayU_Configuration::setMerchantPosId($entry->wplatyIdentyfikatorWplaty);
            OpenPayU_Configuration::setSignatureKey($entry->wplatyPayuDrugiKlucz);
            OpenPayU_Configuration::setOauthClientId($entry->wplatyIdentyfikatorWplaty);
            OpenPayU_Configuration::setOauthClientSecret($entry->wplatyPayuOAuth);
        }

        $payment -> setAttribute('status', "STARTED");
        if( !$payment -> validate() ) {
            return [
                'error' => 'Nie udało się zapisać płatności. Niepoprawne dane.',
            ];
        } else {
            $payment -> save();
        }

        $order['continueUrl'] = Craft::$app->config->general->payUPaymentThanksPage;
        $order['notifyUrl'] = Craft::$app->config->general->paymentStatus;
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

        if ($payment->isRecurring) {
            $order['recurring'] = "FIRST";
            $command = Yii::$app->db->createCommand(
                "INSERT INTO pmmpayments_tokens(`token`,`project`,`title`,`provider`,`currencyCode`,`totalAmount`,`email`, `language`, `firstName`, `lastName`)VALUES ('"
                .$payment->uid."', '" .$request -> getBodyParam("project")."', '".$request -> getBodyParam("title")."', '".$request -> getBodyParam("provider")."','"
                .$request -> getBodyParam("currencyCode")."', '".$request -> getBodyParam("totalAmount")."', '".$request -> getBodyParam("email")."','"
                .$request -> getBodyParam("language")."', '".$request -> getBodyParam("firstName")."', '".$request -> getBodyParam("lastName")."')");
            $command->execute();
        }

        $response = OpenPayU_Order::create($order);

        return $response;
    }

    private function paypalPayment($request, $payment) {
        $payment -> setAttribute('status', "STARTED");
        if( !$payment -> validate() ) {
            return [
                'error' => 'Nie udało się zapisać płatności. Niepoprawne dane.',
            ];
        } else {
            $payment -> save();
        }
//        $clientId = 'AbaGyMki95r33bdnwlPX_2hgYTk-wgV-VDrQ9iCE_wYdunYJGNPA6MiujboJt8zdlL2IcI4rMpos_R1p';
//        $clientSecret = 'EP7XSoF8vVJztsQExb1mqSMMm4Af2YBIGmsaGGL28Dc2LT3PO4UcG95JToBB5TyefzEqVEQtkUqouWn7';
        $clientId = 'ARVs5OXr63WtrG15a1hAQYPAnknN9H1IdyXgT6tqkh7-QdTAQWnOuK43XVgkBxYYZOx5wA1H-EuUXcVJ';
        $clientSecret = 'EGB_nZqRoH7JAXzchuF4Gmaht2i0m3wcA4J3w_XAYM_3sDiMANO-mi5CgbQAzMGVQ6whVkYbHCu2L4si';
        $env = new SandboxEnvironment($clientId, $clientSecret);
        $client = new PayPalHttpClient($env);

        $order = new OrdersCreateRequest();
        $order->prefer('return=representation');
        $order->body = [
            'intent' => 'CAPTURE',
            'application_context' =>
                array(
                    'return_url' => Craft::$app->config->general->paymentStatus,
                    'cancel_url' => Craft::$app->config->general->payUPaymentThanksPage
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
//        $asd = PayPalClient::client();
        $response = $client->execute($order);
//        return $response->result->links[1]->href;
        return print_r($response);
    }

    public function processRequestData($request)
    {
        $response;
        $payment = new Payment();
        $payment -> setAttribute('project', $request -> getBodyParam('project'));
        $payment -> setAttribute('title', $request -> getBodyParam('title'));
        $payment -> setAttribute('firstName', $request -> getBodyParam('firstName'));
        $payment -> setAttribute('lastName', $request -> getBodyParam('lastName'));
        $payment -> setAttribute('email', $request -> getBodyParam('email'));
        $payment -> setAttribute('amount', $request -> getBodyParam('totalAmount'));
        $payment -> setAttribute('isRecurring', $request -> getBodyParam('isRecurring') == 'true');
        $payment -> setAttribute('provider', (int)$request -> getBodyParam('provider'));

        if ($request->getBodyParam('provider') == '1') {
            $response = $this->payuPayment($request, $payment);

            return $response->getResponse()->redirectUri;
        } else {
            $response = $this->paypalPayment($request, $payment);

            return $response;
        }

        if( !$payment -> validate() ) {
            return [
                'error' => 'Nie udało się zapisać płatności. Niepoprawne dane.',
            ];
        } else {
            $payment -> save();
        }

        return true;
    }

    public function checkStatus($request) {
        $body = file_get_contents('php://input');
        $data = trim($body);
//        $json = json_decode(json_decode(json_encode($data)));
        $status = json_decode($data,true)['order']['status'];
        $id = json_decode($data,true)['order']['extOrderId'];
        $email = json_decode($data,true)['order']['buyer']['email'];

        $payment = Payment::findOne(['uid'=>$id]);
        $payment->status = $status;
        $payment->save();
        $this->sendEmail($email);
    }

    public function checkPaypalStatus($request) {
        $body = file_get_contents('php://input');
        $data = trim($body);
        $json = json_decode(json_decode(json_encode($data)));
//        $file = fopen("notify.txt", 'w');
//        fwrite($file, $data);
        return $json;
//        $body = file_get_contents('php://input');
//        $data = trim($body);
////        $json = json_decode(json_decode(json_encode($data)));
//        $status = json_decode($data,true)['order']['status'];
//        $id = json_decode($data,true)['order']['extOrderId'];
//        $email = json_decode($data,true)['order']['buyer']['email'];
//
//        $payment = Payment::findOne(['uid'=>$id]);
//        $payment->status = $status;
//        $payment->save();
//        $this->sendEmail($email);
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

