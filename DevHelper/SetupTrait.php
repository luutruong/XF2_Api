<?php

namespace Truonglv\Api\DevHelper;

use XF;
use function md5;
use function strval;
use function substr;
use function array_map;
use function array_keys;
use function preg_match;
use function is_callable;
use function call_user_func;

/**
 * @version 2022092802
 * @see \DevHelper\Autogen\SetupTrait
 */
trait SetupTrait
{
    protected function doCreateTables(array $tables): void
    {
        $sm = XF::db()->getSchemaManager();

        foreach ($tables as $tableName => $apply) {
            $sm->createTable($tableName, $apply);
        }
    }

    protected function doAlterTables(array $alters): void
    {
        $sm = XF::db()->getSchemaManager();
        foreach ($alters as $tableName => $patches) {
            if (!$sm->tableExists($tableName)) {
                continue;
            }

            foreach ($patches as $patch) {
                $sm->alterTable($tableName, $patch);
            }
        }
    }

    protected function doDropTables(array $tables): void
    {
        $sm = XF::db()->getSchemaManager();
        foreach (array_keys($tables) as $tableName) {
            $sm->dropTable($tableName);
        }
    }

    protected function doDropColumns(array $alters): void
    {
        $sm = XF::db()->getSchemaManager();
        foreach ($alters as $tableName => $patches) {
            if (!$sm->tableExists($tableName)) {
                continue;
            }

            $columns = array_keys($patches);
            $columns = array_map(function ($column) {
                if (preg_match('#^[a-f0-9]{32}#', $column) === 1) {
                    $column = substr($column, 32);
                }

                return $column;
            }, $columns);

            $sm->alterTable($tableName, function (\XF\Db\Schema\Alter $table) use ($columns) {
                $table->dropColumns(array_keys($columns));
            });
        }
    }

    protected function getTables(): array
    {
        $tables = [];

        $index = 1;
        while (true) {
            $callable = [$this, 'getTables' . $index];
            if (!is_callable($callable)) {
                break;
            }

            $tables += call_user_func($callable);

            $index++;
        }

        return $tables;
    }

    protected function getAlters(): array
    {
        $alters = [];

        $index = 1;
        $patchIndex = 0;

        while (true) {
            $callable = [$this, 'getAlters' . $index];
            if (!is_callable($callable)) {
                break;
            }

            $patches = call_user_func($callable);
            foreach ($patches as $tableName => $columnPatches) {
                if (!isset($alters[$tableName])) {
                    $alters[$tableName] = [];
                }

                foreach ($columnPatches as $column => $patch) {
                    ++$patchIndex;
                    $alters[$tableName][md5(strval($patchIndex)) . $column] = $patch;
                }
            }

            $index++;
        }

        return $alters;
    }
}
