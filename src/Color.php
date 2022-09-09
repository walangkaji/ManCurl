<?php

namespace ManCurl;

/**
 * Coloring the text output in terminal
 */
class Color
{
    /**
     * Blue color format
     */
    public static function blue(string $text): string
    {
        return "\033[1;34m$text\033[0m";
    }

    /**
     * Brown color format
     */
    public static function brown(string $text): string
    {
        return "\033[0;33m$text\033[0m";
    }

    /**
     * Cyan color format
     */
    public static function cyan(string $text): string
    {
        return "\033[1;36m$text\033[0m";
    }

    /**
     * Green color format
     */
    public static function green(string $text): string
    {
        return "\033[1;32m$text\033[0m";
    }

    /**
     * Red color format
     */
    public static function red(string $text): string
    {
        return "\033[1;31m$text\033[0m";
    }

    /**
     * Yellow color format
     */
    public static function yellow(string $text): string
    {
        return "\033[1;33m$text\033[0m";
    }
}
