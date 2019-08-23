<?php
/**
 * Copyright (c) Simon Hampel
 * Based on code used by Composer, which is Copyright (c) Nils Adermann, Jordi Boggiano
 */

namespace Truonglv\Api\Util;

class Composer
{
    /**
     * @param \XF\App $app
     * @param bool $prepend
     * @return void
     */
    public static function autoloadNamespaces(\XF\App $app, $prepend = false)
    {
        $namespaces = self::getAddOnDir() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_namespaces.php';

        if (!file_exists($namespaces)) {
            $app->error()->logError('Missing vendor autoload files at %s', $namespaces);
        } else {
            $map = require $namespaces;

            foreach ($map as $namespace => $path) {
                \XF::$autoLoader->add($namespace, $path, $prepend);
            }
        }
    }

    /**
     * @param \XF\App $app
     * @param bool $prepend
     * @return void
     */
    public static function autoloadPsr4(\XF\App $app, $prepend = false)
    {
        $psr4 = self::getAddOnDir() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_psr4.php';

        if (!file_exists($psr4)) {
            $app->error()->logError('Missing vendor autoload files at %s', $psr4);
        } else {
            $map = require $psr4;

            foreach ($map as $namespace => $path) {
                \XF::$autoLoader->addPsr4($namespace, $path, $prepend);
            }
        }
    }

    /**
     * @param \XF\App $app
     * @return void
     */
    public static function autoloadClassmap(\XF\App $app)
    {
        $classmap = self::getAddOnDir() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_classmap.php';

        if (!file_exists($classmap)) {
            $app->error()->logError('Missing vendor autoload files at %s', $classmap);
        } else {
            $map = require $classmap;

            if ($map) {
                \XF::$autoLoader->addClassMap($map);
            }
        }
    }

    /**
     * @param \XF\App $app
     * @return void
     */
    public static function autoloadFiles(\XF\App $app)
    {
        $files = self::getAddOnDir() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_files.php';

        // note that autoload_files.php is only generated if there is actually a 'files' directive somewhere in the dependency chain
        if (file_exists($files)) {
            $includeFiles = require $files;

            foreach ($includeFiles as $fileIdentifier => $file) {
                if (!isset($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
                    require $file;

                    $GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;
                }
            }
        }
    }

    /**
     * @return string
     */
    private static function getAddOnDir()
    {
        return \XF::getAddOnDirectory() . DIRECTORY_SEPARATOR . 'Truonglv/Api';
    }
}
