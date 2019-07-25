<?php

namespace Truonglv\Api;

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

        return $tables;
    }
}
