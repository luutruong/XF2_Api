<?php

namespace Truonglv\Api;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use Truonglv\Api\DevHelper\SetupTrait;
use XF\AddOn\StepRunnerUninstallTrait;

class Setup extends AbstractSetup
{
    use SetupTrait;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $this->doCreateTables($this->getTables());
        $this->doAlterTables($this->getAlters());
    }

    public function uninstallStep1()
    {
        $this->doDropTables($this->getTables());
    }

    public function upgrade2000000Step1()
    {
        $this->doAlterTables($this->getAlters1());
    }

    public function upgrade3000300Step1()
    {
        $this->doCreateTables($this->getTables2());
    }

    public function upgrade3010100Step1()
    {
        $this->doCreateTables($this->getTables3());
    }

    public function upgrade3010500Step1()
    {
        $this->doCreateTables($this->getTables4());
    }

    /**
     * @return array
     */
    protected function getTables1()
    {
        $tables = [];

        $tables['xf_tapi_log'] = function (Create $table) {
            $table->addColumn('log_id', 'int')
                ->autoIncrement();

            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addColumn('app_version', 'varchar', 50);

            $table->addColumn('end_point', 'text');
            $table->addColumn('method', 'varchar', 12);
            $table->addColumn('payload', 'mediumblob');
            $table->addColumn('response_code', 'int')->setDefault(0);
            $table->addColumn('response', 'mediumblob');
            $table->addColumn('log_date', 'int')->setDefault(0);

            $table->addKey('log_date');
        };

        $tables['xf_tapi_subscription'] = function (Create $table) {
            $table->addColumn('subscription_id', 'int')
                ->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('username', 'varchar', 50);
            $table->addColumn('app_version', 'varchar', 50);
            $table->addColumn('device_token', 'varbinary', 150);
            $table->addColumn('is_device_test', 'tinyint')->setDefault(0);

            $table->addColumn('provider', 'varchar', 25);
            $table->addColumn('provider_key', 'varchar', 100);

            $table->addColumn('subscribed_date', 'int')
                ->setDefault(0);

            $table->addUniqueKey(['user_id', 'device_token']);
            $table->addKey(['provider', 'provider_key']);
            $table->addKey(['subscribed_date']);
        };

        $tables['xf_tapi_alert_queue'] = function (Create $table) {
            $table->addColumn('content_type', 'varchar', 25);
            $table->addColumn('content_id', 'int');
            $table->addColumn('payload', 'blob')->nullable();
            $table->addColumn('queue_date', 'int')->setDefault(0);

            $table->addPrimaryKey(['content_type', 'content_id']);
            $table->addKey(['queue_date']);
        };

        $tables['xf_tapi_access_token'] = function (Create $table) {
            $table->addColumn('token', 'varbinary', 32);
            $table->addColumn('user_id', 'int');
            $table->addColumn('created_date', 'int')->setDefault(0);
            $table->addColumn('expire_date', 'int')->setDefault(0);

            $table->addPrimaryKey('token');
            $table->addKey(['expire_date']);
        };

        return $tables;
    }

    protected function getTables2(): array
    {
        return [
            'xf_tapi_search_query' => function (Create $table) {
                $table->addColumn('search_query_id', 'int')->autoIncrement();
                $table->addColumn('query_text', 'varchar', 255);
                $table->addColumn('user_id', 'int');
                $table->addColumn('created_date', 'int')->setDefault(0);

                $table->addKey('created_date');
            },
        ];
    }

    protected function getTables3(): array
    {
        return [
            'xf_tapi_refresh_token' => function (Create $table) {
                $table->addColumn('token', 'varbinary', 32)->primaryKey();
                $table->addColumn('user_id', 'int');
                $table->addColumn('created_date', 'int')->setDefault(0);
                $table->addColumn('expire_date', 'int')->setDefault(0);

                $table->addKey('expire_date');
            }
        ];
    }

    protected function getTables4(): array
    {
        return [
            'xf_tapi_iap_product' => function (Create $table) {
                $table->addColumn('product_id', 'int')->autoIncrement();
                $table->addColumn('title', 'varchar', 100);
                $table->addColumn('platform', 'enum', ['ios', 'android']);
                $table->addColumn('store_product_id', 'varchar', 255);
                $table->addColumn('user_group_ids', 'varchar', 255);
                $table->addColumn('active', 'tinyint')->setDefault(0);
                $table->addColumn('display_order', 'int')->setDefault(1);

                $table->addUniqueKey(['platform', 'store_product_id']);
            },
        ];
    }

    /**
     * @return array
     */
    protected function getAlters1()
    {
        $alters = [];

        $alters['xf_tapi_subscription'] = [
            'provider_key' => function (Alter $table) {
                $table->changeColumn('provider_key', 'varchar', 200);
            },
            'device_token' => function (Alter $table) {
                $table->changeColumn('device_token', 'varchar', 200);
            },
            'device_type' => function (Alter $table) {
                $table->addColumn('device_type', 'varchar', 25)->setDefault('');
            },
        ];

        return $alters;
    }
}
