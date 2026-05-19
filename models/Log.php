<?php

namespace app\models;

use yii\db\ActiveRecord;

class Log extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%logs}}';
    }

    public function rules(): array
    {
        return [
            [['ip', 'datetime', 'url'], 'required'],
            [['datetime', 'created_at'], 'safe'],
            [['user_agent'], 'string'],
            [['ip'], 'string', 'max' => 45],
            [['url'], 'string', 'max' => 2048],
            [['os', 'browser'], 'string', 'max' => 255],
            [['architecture'], 'string', 'max' => 10],
        ];
    }
}
