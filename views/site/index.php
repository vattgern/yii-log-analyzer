<?php

/**
 * @var yii\web\View $this
 * @var array $filters
 * @var array $osList - список OS
 * @var array $archList - список архитектур
 * @var \yii\data\ArrayDataProvider $dataProvider
 *
 * @var string $chart1Data
 * @var string $chart2Data
 * @var array $topBrowsers
 */

use yii\grid\GridView;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'My Yii Application';
$this->params['meta_description'] = 'A high-performance PHP framework best for developing web applications. Fast, secure, and professional.';
$this->params['meta_keywords'] = 'yii, yii2, php, framework, web application, high-performance';
?>
<div class="site-index">

    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3>Фильтры</h3>
                </div>
                <div class="panel-body">
                    <?php ActiveForm::begin(['method' => 'get', 'options' => ['class' => '']]) ?>

                    <div class="form-group mb-3">
                        <label for="date_from">Дата от:</label>
                        <?= Html::input('date', 'date_from', $filters['date_from'], ['class' => 'form-control']) ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="date_to">Дата до:</label>
                        <?= Html::input('date', 'date_to', $filters['date_to'], ['class' => 'form-control']) ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="os">OS:</label>
                        <?= Html::dropDownList('os', $filters['os'], ['' => 'Все'] + array_combine($osList, $osList), ['class' => 'form-control']) ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="architecture">Архитектура:</label>
                        <?= Html::dropDownList('architecture', $filters['os'], ['' => 'Все'] + array_combine($archList, $archList), ['class' => 'form-control']) ?>
                    </div>

                    <?= Html::submitButton('Применить', ['class' => 'btn btn-primary']) ?>
                    <?= Html::a('Сбросить', ['index'], ['class' => 'btn btn-default']) ?>
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3>Количество запросов по дням</h3>
                </div>
                <div class="panel-body">
                    <canvas id="chart1" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3>Доля популярных браузеров (%)</h3>
                </div>
                <div class="panel-body">
                    <canvas id="chart2" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3>Статистика по дням</h3>
                </div>
                <div class="panel-body">
                    <?= GridView::widget([
                        'dataProvider' => $dataProvider,
                        'columns' => [
                            [
                                'attribute' => 'date',
                                'label' => 'Дата',
                                'enableSorting' => true,
                            ],
                            [
                                'attribute' => 'total_requests',
                                'label' => 'Число запросов',
                                'enableSorting' => true,
                            ],
                            [
                                'attribute' => 'top_url',
                                'label' => 'Самый популярный URL',
                                'enableSorting' => true,
                            ],
                            [
                                'attribute' => 'top_browser',
                                'label' => 'Самый популярный браузер',
                                'enableSorting' => true,
                            ],
                        ],
                    ]); ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const chart1Data = <?= $chart1Data ?>;
    new Chart(document.getElementById('chart1'), {
        type: 'line',
        data: {
            labels: chart1Data.map(item => item.date),
            datasets: [{
                label: 'Запросы',
                data: chart1Data.map(item => item.count),
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });

    const chart2Data = <?= $chart2Data ?>;
    const browsers = <?= json_encode($topBrowsers) ?>;
    const datasets = browsers.map((browser, index) => ({
        label: browser,
        data: chart2Data.dates.map(date => chart2Data.series[date]?.[browser] || 0),
        borderColor: `hsl(${index * 120}, 70%, 50%)`,
        fill: false
    }));

    new Chart(document.getElementById('chart2'), {
        type: 'line',
        data: {
            labels: chart2Data.dates,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Доля запросов (%)'
                    }
                }
            }
        }
    });
</script>
