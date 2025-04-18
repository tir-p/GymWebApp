<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInitfcaf91f6652914f2a8555449b6d001ab
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        require __DIR__ . '/platform_check.php';

        spl_autoload_register(array('ComposerAutoloaderInitfcaf91f6652914f2a8555449b6d001ab', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInitfcaf91f6652914f2a8555449b6d001ab', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInitfcaf91f6652914f2a8555449b6d001ab::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
