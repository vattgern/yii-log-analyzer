<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%logs}}`.
 */
class m260518_114837_create_logs_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%logs}}', [
            'id' => $this->primaryKey(),
            'ip' => $this->string()->notNull(),
            'datetime' => $this->dateTime()->notNull(),
            'url' => $this->text()->notNull(),
            'user_agent' => $this->text(),
            'os' => $this->string(),
            'architecture' => $this->string(),
            'browser' => $this->string(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%logs}}');
    }
}
