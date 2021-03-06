<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */

namespace Truonglv\Api\DevHelper;

/**
 * @version 2019011701
 * @see \DevHelper\Autogen\SetupTrait
 */
trait SetupTrait
{
    /**
     * @param array $tables
     *
     * @return void
     */
    protected function doCreateTables(array $tables)
    {
        $sm = \XF::db()->getSchemaManager();

        foreach ($tables as $tableName => $apply) {
            $sm->createTable($tableName, $apply);
        }
    }

    /**
     * @param array $alters
     *
     * @return void
     */
    protected function doAlterTables(array $alters)
    {
        $sm = \XF::db()->getSchemaManager();
        foreach ($alters as $tableName => $columns) {
            if (!$sm->tableExists($tableName)) {
                continue;
            }

            foreach ($columns as $applies) {
                if (!is_array($applies)) {
                    $applies = [$applies];
                }

                foreach ($applies as $apply) {
                    $sm->alterTable($tableName, $apply);
                }
            }
        }
    }

    /**
     * @param array $tables
     *
     * @return void
     */
    protected function doDropTables(array $tables)
    {
        $sm = \XF::db()->getSchemaManager();
        foreach (array_keys($tables) as $tableName) {
            $sm->dropTable($tableName);
        }
    }

    /**
     * @param array $alters
     *
     * @return void
     */
    protected function doDropColumns(array $alters)
    {
        $sm = \XF::db()->getSchemaManager();
        foreach ($alters as $tableName => $columns) {
            if (!$sm->tableExists($tableName)) {
                continue;
            }

            $sm->alterTable($tableName, function (\XF\Db\Schema\Alter $table) use ($columns) {
                $table->dropColumns(array_keys($columns));
            });
        }
    }

    /**
     * @return array
     */
    protected function getTables()
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

    /**
     * Data template:
     *
     * [
     *      xf_example_table => [
     *          example_column => [
     *              function () {...},
     *              function () {...},
     *              ...
     *          ]
     *      ],
     *      ...
     * ]
     *
     * @return array
     */
    protected function getAlters()
    {
        $alters = [];

        $index = 1;
        while (true) {
            $callable = [$this, 'getAlters' . $index];
            if (!is_callable($callable)) {
                break;
            }

            $versionAlters = call_user_func($callable);
            foreach ($versionAlters as $tableName => $columns) {
                if (!isset($alters[$tableName])) {
                    $alters[$tableName] = [];
                }

                foreach ($columns as $columnName => $apply) {
                    if (!isset($alters[$tableName][$columnName])) {
                        $alters[$tableName][$columnName] = [];
                    }

                    $alters[$tableName][$columnName][] = $apply;
                }
            }

            $index++;
        }

        return $alters;
    }
}
