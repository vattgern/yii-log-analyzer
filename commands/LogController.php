<?php

namespace app\commands;

use app\components\LogParser;
use app\models\Log;
use yii\console\Controller;
use yii\console\ExitCode;

class LogController extends Controller
{
    /**
     * Импортировать данные из логов
     */
    public function actionImport(string $filePath)
    {
        if (!file_exists($filePath)) {
            $this->stderr("Файл не найден: {$filePath}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $fp = fopen($filePath, 'r');
        if (!$fp) {
            $this->stderr("Невозможно открыть файл: {$filePath}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $success = 0;
        $failed = 0;
        $this->stdout("Начало операции обработки\n");
        while (($line = fgets($fp)) !== false) {
            $data = LogParser::parse($line);
            if ($data) {
                $log = new Log();
                $log->attributes = $data;

                if ($log->save())
                    $success++;
                else
                    $failed++;
            } else {
                $failed++;
            }

            if (($success + $failed) % 1000 === 0)
                $this->stdout("Обработано: " . ($success + $failed) . "\n");
        }

        fclose($fp);

        $this->stdout("Операция завершина\n Успешно: {$success}\n Провалено: {$failed}\n");
        return ExitCode::OK;
    }
}
