<?php
/**
 * pmm-payments plugin for Craft CMS 3.x
 *
 * Payment processing plugin for the Polish Medical Mission
 *
 * @link      https://pleodigital.com/
 * @copyright Copyright (c) 2020 Pleo Digtial
 */

namespace pleodigital\pmmpayments\migrations;

use pleodigital\pmmpayments\Pmmpayments;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

/**
 * pmm-payments Install Migration
 *
 * If your plugin needs to create any custom database tables when it gets installed,
 * create a migrations/ folder within your plugin folder, and save an Install.php file
 * within it using the following template:
 *
 * If you need to perform any additional actions on install/uninstall, override the
 * safeUp() and safeDown() methods.
 *
 * @author    Pleo Digtial
 * @package   Pmmpayments
 * @since     1.0.0
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables needed for the Records used by the plugin
     *
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

    // pmmpayments_payment table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%pmmpayments_payment}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%pmmpayments_payment}}',
                [
                    'id' => $this -> primaryKey(),
                    'dateCreated' => $this -> dateTime() -> notNull(),
                    'dateUpdated' => $this -> dateTime() -> notNull(),
                    'uid' => $this -> uid(),
                // Custom columns in the table 
                    'project' => $this -> string(500) -> notNull() -> defaultValue(''),
                    'firstName' => $this -> string(255) -> notNull() -> defaultValue(''),
                    'lastName' => $this -> string(255) -> notNull() -> defaultValue(''),
                    'email' => $this -> string(255) -> notNull() -> defaultValue(''),
                    'amount' => $this -> money(8,2) -> notNull() -> defaultValue(null),
                    'isRecurring' => $this -> integer(1) -> notNull() -> defaultValue(0), 
                    'provider' => $this -> integer(4) -> notNull() -> defaultValue(0)
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * Creates the indexes needed for the Records used by the plugin
     *
     * @return void
     */
    protected function createIndexes()
    {
    // pmmpayments_payment table
        $this->createIndex(
            $this->db->getIndexName(
                '{{%pmmpayments_payment}}',
                true
            ),
            '{{%pmmpayments_payment}}',
            true
        );
        // Additional commands depending on the db driver
        switch ($this->driver) {
            case DbConfig::DRIVER_MYSQL:
                break;
            case DbConfig::DRIVER_PGSQL:
                break;
        }
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    protected function addForeignKeys()
    {
    // pmmpayments_payment table
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%pmmpayments_payment}}', 'siteId'),
            '{{%pmmpayments_payment}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * Populates the DB with the default data.
     *
     * @return void
     */
    protected function insertDefaultData()
    {
    }

    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables()
    {
    // pmmpayments_payment table
        $this->dropTableIfExists('{{%pmmpayments_payment}}');
    }
}
