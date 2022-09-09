<?php

namespace ManCurl;

/**
 * Print the proccess to CLI, set `debug: true` in config to print the marked process,
 * initialize with `Debug::set($config->debug)`
 */
class Debug
{
    /** The level markdown & color */
    private const LEVEL = [
        'info' => [
            'color'   => 'cyan',
            'marking' => '[i]',
        ],
        'success' => [
            'color'   => 'green',
            'marking' => '[+]',
        ],
        'warning' => [
            'color'   => 'yellow',
            'marking' => '[!]',
        ],
        'error' => [
            'color'   => 'red',
            'marking' => '[x]',
        ],
    ];

    /**
     * Indicate the debug is enable or disabled
     */
    private static bool $debug = false;

    /**
     * Activate the debug
     */
    public static function activate(bool $debug): void
    {
        self::$debug = $debug;
    }

    /**
     * Print debug info mode
     *
     * @param string              $function function name or identity
     * @param string|array|object $data     result data or note
     */
    public static function info(string $function, $data): void
    {
        self::show('info', $function, $data);
    }

    /**
     * Print debug success mode
     *
     * @param string              $function function name or identity
     * @param string|array|object $data     result data or note
     */
    public static function success(string $function, $data): void
    {
        self::show('success', $function, $data);
    }

    /**
     * Print debug warning mode
     *
     * @param string              $function function name or identity
     * @param string|array|object $data     result data or note
     */
    public static function warning(string $function, $data): void
    {
        self::show('warning', $function, $data);
    }

    /**
     * Echo debug error mode
     *
     * @param string              $function function name or identity
     * @param string|array|object $data     result data or note
     */
    public static function error(string $function, $data): void
    {
        self::show('error', $function, $data);
    }

    /**
     * Format debug
     *
     * @param string|array|object $data result data or note
     */
    private static function show(string $level, string $function, $data): void
    {
        if (self::$debug && \PHP_SAPI === 'cli') {
            if (\is_array($data) || \is_object($data)) {
                $data = json_encode($data);
            }

            $color   = self::LEVEL[$level]['color'];
            $marking = self::LEVEL[$level]['marking'];

            $time = Color::yellow('[' . date('h:i:s A') . ']: ');
            echo $time .
                Color::yellow($function) .
                self::getSpace($function) .
                Color::$color($marking . ' ' . $data) .
                \PHP_EOL;
        }
    }

    /**
     * Get indent space
     */
    private static function getSpace(string $function): string
    {
        $space = 18 - \strlen($function);
        $space = ($space < 0) ? 0 : $space;

        return str_repeat(' ', $space) . ': ';
    }
}
