<?php

/**
 * Singleton Abstract Class
 *
 * @package WPHelpZone\WCSO
 */

namespace WPHelpZone\WCSO\Abstracts;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Singleton Class
 *
 * Provides singleton pattern implementation for all child classes.
 */
abstract class WCSO_Singleton
{

    /**
     * Instance storage for child classes.
     *
     * @var array
     */
    private static $instances = array();

    /**
     * Get singleton instance
     *
     * @return static
     */
    final public static function get_instance()
    {
        $class = get_called_class();

        if (! isset(self::$instances[$class])) {
            self::$instances[$class] = new $class();
        }

        return self::$instances[$class];
    }

    /**
     * Protected constructor to prevent direct instantiation.
     */
    protected function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the class. Override in child classes.
     *
     * @return void
     */
    protected function init()
    {
        // Override in child classes.
    }

    /**
     * Prevent cloning of the instance.
     */
    final protected function __clone()
    {
        // Singleton pattern - no cloning allowed.
    }

    /**
     * Prevent unserializing of the instance.
     */
    final public function __wakeup()
    {
        // Singleton pattern - no unserialization allowed.
    }
}
