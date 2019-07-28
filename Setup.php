<?php

namespace Truonglv\Api;

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
    }

    public function uninstallStep1()
    {
        $this->doDropTables($this->getTables());
    }

    public function upgrade1000400Step1()
    {
        $this->doCreateTables($this->getTables1());
    }

    protected function getTables1()
    {
        $tables = [];

        $tables['xf_tapi_log'] = function (Create $table) {
            $table->addColumn('log_id', 'int')
                ->autoIncrement();

            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addColumn('app_version', 'varchar', 50);
            $table->addColumn('user_device', 'varchar', 255);

            $table->addColumn('end_point', 'text');
            $table->addColumn('method', 'varchar', 12);
            $table->addColumn('payload', 'mediumblob');
            $table->addColumn('response_code', 'int')->setDefault(0);
            $table->addColumn('response', 'mediumblob');
            $table->addColumn('log_date', 'int')->setDefault(0);

            $table->addKey('log_date');
        };

        return $tables;
    }
}
