<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\models\ContactForm;
use app\models\Log;
use app\models\LoginForm;
use yii\captcha\CaptchaAction;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\base\Security;
use yii\data\ArrayDataProvider;
use yii\db\ActiveQuery;
use yii\db\Query;
use yii\mail\MailerInterface;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\Response;

class SiteController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly MailerInterface $mailer,
        private readonly Security $security,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
            'captcha' => [
                'class' => CaptchaAction::class,
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
                'transparent' => true,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex(): string
    {
        $request = Yii::$app->request;

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $os = $request->get('os');
        $arch = $request->get('architecture');

        if ($dateFrom && $dateTo) {
            $diff = strtotime($dateTo) - strtotime($dateFrom);
            if ($diff > 365 * 24 * 3600) {
                $dateTo = date('Y-m-d', strtotime($dateFrom . ' + 365 days'));
            }
        }

        $query = Log::find();
        if ($dateFrom)
            $query->andWhere(['>=', 'DATE(datetime)', $dateFrom]);

        if ($dateTo)
            $query->andWhere(['<=', 'DATE(datetime)', $dateTo]);

        if ($os && $os !== '')
            $query->andWhere(['os' => $os]);

        if ($arch && $arch !== '')
            $query->andWhere(['architecture' => $arch]);

        $topBrowsers = (new Query())
            ->select(['browser', 'COUNT(*) as total'])
            ->from('logs')
            ->where($query->where)
            ->groupBy('browser')
            ->orderBy(['total' => SORT_DESC])
            ->limit(3)
            ->column();

        $chart1Data = $this->chartCountRequestsByDate($query);
        $chart2Data = $this->chartTopThreeBrowsers($query, $topBrowsers);

        return $this->render('index', [
            'chart1Data'    => json_encode($chart1Data),
            'chart2Data'    => json_encode([
                'dates' => array_keys($chart2Data),
                'series' => $chart2Data
            ]),
            'dataProvider'  => $this->getTableData($request, $query),
            'osList'        => $this->getOsList(),
            'archList'      => $this->getArchitectureList(),
            'topBrowsers'   => $topBrowsers,
            'filters'       => [
                'date_from'     => $dateFrom,
                'date_to'       => $dateTo,
                'os'            => $os,
                'architecture'  => $arch
            ]
        ]);
    }

    /**
     * Количество запросов по датам
     *
     * @param ActiveQuery $query
     *
     * @return array
     */
    private function chartCountRequestsByDate(ActiveQuery $query): array
    {
        return (new Query())
            ->select(['DATE(datetime) as date', 'COUNT(*) as count'])
            ->from('logs')
            ->where($query->where)
            ->groupBy('DATE(datetime)')
            ->orderBy('date ASC')
            ->all();
    }

    /**
     * Доля популярных браузеров
     *
     * @param ActiveQuery $query
     * @param array $topBrowsers
     *
     * @return array
     */
    private function chartTopThreeBrowsers(ActiveQuery $query, array $topBrowsers): array
    {
        $browserStats = $this->getBrowserStats($query);

        $result = [];
        $dailyTotals = [];
        foreach ($browserStats as $stat) {
            $date = $stat['date'];
            $browser = $stat['browser'];
            if (!in_array($browser, $topBrowsers)) continue;

            if (!isset($dailyTotals[$date])) {
                $queryBuilder = (new Query())
                    ->from('logs')
                    ->where(['DATE(datetime)' => $date]);

                if ($query->where)
                    $queryBuilder->andWhere($query->where);

                $dailyTotals[$date] = $queryBuilder->count();
            }

            $result[$date][$browser] = round(($stat['count'] / $dailyTotals[$date]) * 100, 2);
        }

        return $result;
    }

    /**
     * Данные для отбора
     * @param ActiveQuery $query
     *
     * @return array
     */
    private function getBrowserStats(ActiveQuery $query): array
    {
        return (new Query())
            ->select(['DATE(datetime) as date', 'browser', 'COUNT(*) as count'])
            ->from('logs')
            ->where($query->where)
            ->groupBy(['DATE(datetime)', 'browser'])
            ->orderBy('date ASC')
            ->all();
    }

    /**
     * @param \yii\web\Request|\yii\console\Request $request
     * @param ActiveQuery $query
     *
     * @return \yii\data\ArrayDataProvider
     */
    private function getTableData(
        \yii\web\Request|\yii\console\Request $request,
        ActiveQuery $query
    ) {
        $datesData = $this->getListDatesWithCountRequests($query);

        $tableData = [];

        foreach ($datesData as $row) {
            $date = $row['date'];

            $topUrl = $this->getTopUrlByDate($date, $query);
            $topBrowser = $this->getTopBrowserByDate($date, $query);

            $tableData[] = [
                'date'              => $date,
                'total_requests'    => $row['total_requests'],
                'top_url'           => $topUrl ? $topUrl['url'] : '-',
                'top_browser'       => $topBrowser ? $topBrowser['browser'] : '-',
            ];
        }

        $sort = $request->get('sort', 'date');
        $order = $request->get('order', 'desc');

        if (strpos($sort, '-') === 0) {
            $sort = substr($sort, 1);
            $order = 'desc';
        }

        usort($tableData, function ($a, $b) use ($sort, $order) {
            if ($sort === 'total_requests') {
                $cmp = $a['total_requests'] - $b['total_requests'];
            } else {
                $cmp = strcmp($a[$sort], $b[$sort]);
            }

            return $order === 'asc' ? $cmp : -$cmp;
        });

        return new ArrayDataProvider([
            'allModels' => $tableData,
            'pagination' => ['pageSize' => 20],
            'sort' => [
                'attributes' => [
                    'date' => [
                        'asc' => ['date' => SORT_ASC],
                        'desc' => ['date' => SORT_DESC],
                    ],
                    'total_requests',
                    'top_url',
                    'top_browser',
                ],
                'defaultOrder' => ['date' => SORT_DESC],
            ],
        ]);
    }

    /**
     * Топ запрос по дате
     *
     * @param string $date в какой день искать
     * @param ActiveQuery $query дополнительные условия
     *
     * @return array|bool
     */
    private function getTopUrlByDate(string $date, ActiveQuery $query): array|bool
    {
        $queryBuilder = (new Query())
            ->select(['url', 'COUNT(*) as cnt'])
            ->from('logs')
            ->where(['DATE(datetime)' => $date]);

        if ($query->where)
            $queryBuilder->andWhere($query->where);

        return $queryBuilder->groupBy('url')
            ->orderBy(['cnt' => SORT_DESC])
            ->limit(1)
            ->one();
    }

    /**
     * Топ браузер по дате
     *
     * @param string $date в какой день искать
     * @param ActiveQuery $query дополнительные условия
     *
     * @return array|bool
     */
    private function getTopBrowserByDate(string $date, ActiveQuery $query): array|bool
    {
        $queryBuilder = (new Query())
            ->select(['browser', 'COUNT(*) as cnt'])
            ->from('logs')
            ->where(['DATE(datetime)' => $date]);

        if ($query->where)
            $queryBuilder->andWhere($query->where);

        return $queryBuilder->groupBy('browser')
            ->orderBy(['cnt' => SORT_DESC])
            ->limit(1)
            ->one();
    }
    /**
     * Список дат с количеством запросов
     *
     * @param ActiveQuery $query
     *
     * @return array
     */
    private function getListDatesWithCountRequests(ActiveQuery $query): array
    {
        return (new Query())
            ->select(['DATE(datetime) as date', 'COUNT(*) as total_requests'])
            ->from('logs')
            ->where($query->where)
            ->groupBy('DATE(datetime)')
            ->all();
    }
    /**
     * Список OS для фильтров
     *
     * @return array
     */
    private function getOsList(): array
    {
        return Log::find()
            ->select('os')
            ->distinct()
            ->where(['not', ['os' => null]])
            ->column();
    }

    /**
     * Список архитектур для фильтров
     *
     * @return array
     */
    private function getArchitectureList(): array
    {
        return Log::find()
            ->select('architecture')
            ->distinct()
            ->where(['not', ['architecture' => null]])
            ->column();
    }
    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin(): Response|string
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm($this->security);

        if ($model->load($this->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';

        return $this->render('login', ['model' => $model]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout(): Response
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact(): Response|string
    {
        $model = new ContactForm();

        $contact = $model->load($this->request->post()) && $model->contact(
            $this->mailer,
            Yii::$app->params['adminEmail'],
            Yii::$app->params['senderEmail'],
            Yii::$app->params['senderName'],
        );

        if ($contact) {
            Yii::$app->session->setFlash(
                'success',
                'Thank you for contacting us. We will respond to you as soon as possible.',
            );

            return $this->refresh();
        }

        return $this->render('contact', ['model' => $model]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout(): string
    {
        return $this->render('about');
    }
}
