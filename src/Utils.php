<?php

namespace ManCurl;

use ManCurl\Exception\FileNotFoundException;

final class Utils
{
    public const JSON_PATTERN = '/^(?:application|text)\/(?:[a-z]+(?:[\.-]' .
                                '[0-9a-z]+){0,}[\+\.]|x-)?json(?:-[a-z]+)?/i';

    /**
     * Calculates Java hashCode() for a given string.
     *
     * WARNING: This method is not Unicode-aware, so use it only on ANSI strings.
     *
     * @see https://en.wikipedia.org/wiki/Java_hashCode()#The_java.lang.String_hash_function
     */
    public static function hashCode(string $string): int
    {
        $result = 0;
        for ($i = 0, $len = \strlen($string); $i < $len; ++$i) {
            $result = (-$result + ($result << 5) + \ord($string[$i])) & 0xFFFFFFFF;
        }

        if (\PHP_INT_SIZE > 4) {
            if ($result > 0x7FFFFFFF) {
                $result -= 0x100000000;
            } elseif ($result < -0x80000000) {
                $result += 0x100000000;
            }
        }

        return $result;
    }

    /**
     * Sometime we need to add content-type as Content-Type to matching request from app,
     * in this section we will use the the key from request input.
     *
     *  @return bool true if already have content-type, false otherwise
     */
    public static function contentTypeMatch(array $headers): bool
    {
        if (\in_array('content-type', array_map('strtolower', array_keys($headers)))) {
            return true;
        }

        return false;
    }

    /**
     * Reorders array by hashCode() of its keys.
     */
    public static function reorderByHashCode(array $data): array
    {
        $hashCodes = [];
        foreach ($data as $key => $value) {
            $hashCodes[$key] = self::hashCode($key);
        }

        uksort($data, function ($a, $b) use ($hashCodes) {
            $a = $hashCodes[$a];
            $b = $hashCodes[$b];

            if ($a < $b) {
                return -1;
            } elseif ($a > $b) {
                return 1;
            }

            return 0;
        });

        return $data;
    }

    /**
     * Generates random multipart boundary string.
     */
    public static function generateMultipartBoundary(): string
    {
        $boundaryChar = '-_1234567890abcdefghijklmnopqrst' .
                        'uvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $boundaryLength = 30;

        $result = '';
        $max    = \strlen($boundaryChar) - 1;
        for ($i = 0; $i < $boundaryLength; ++$i) {
            $result .= $boundaryChar[mt_rand(0, $max)];
        }

        return $result;
    }

    /**
     * Convert image to base64 string
     *
     * @throws FileNotFoundException
     */
    public static function imageToBase64(string $file): string
    {
        $type = pathinfo($file, \PATHINFO_EXTENSION);
        $data = file_get_contents($file);

        if (false === $data) {
            throw new FileNotFoundException(null, 0, null, $file);
        }

        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }

    /**
     * Check if json is valid
     *
     * @param mixed $value
     */
    public static function isJson($value): bool
    {
        if (
            \is_string($value) && \is_array(json_decode($value, true)) && (\JSON_ERROR_NONE == json_last_error())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Create folder.
     *
     * @param string $folder nama folder, bisa recursive(/var/www/html/oke/oke1)
     */
    public static function createFolder(string $folder): void
    {
        if (! file_exists($folder)) {
            mkdir($folder, 0775, true);
        } elseif (!is_dir($folder)) {
            unlink($folder);
            mkdir($folder, 0775, true);
        }
    }

    /**
     * Atomic filewriter.
     *
     * Safely writes new contents to a file using an atomic two-step process.
     * If the script is killed before the write is complete, only the temporary
     * trash file will be corrupted.
     *
     * The algorithm also ensures that 100% of the bytes were written to disk.
     *
     * @param string $filename     filename to write the data to
     * @param string $data         data to write to file
     * @param string $atomicSuffix lets you optionally provide a different
     *                             suffix for the temporary file
     */
    public static function atomicWrite(
        string $filename,
        string $data,
        string $atomicSuffix = 'atomictmp'
    ): bool {
        // Perform an exclusive (locked) overwrite to a temporary file.
        $filenameTmp = sprintf('%s.%s', $filename, $atomicSuffix);
        $writeResult = @file_put_contents($filenameTmp, $data, \LOCK_EX);

        // Only proceed if we wrote 100% of the data bytes to disk.
        if (false !== $writeResult && $writeResult === \strlen($data)) {
            // Now move the file to its real destination (replaces if exists).
            $moveResult = @rename($filenameTmp, $filename);

            if (true === $moveResult) {
                // Successful write and move. Return true.
                return true;
            }
        }

        // We've failed. Remove the temporary file if it exists.
        if (is_file($filenameTmp)) {
            @unlink($filenameTmp);
        }

        return false; // Failed.
    }

    /**
     * Convert array to object
     *
     * @param array $array array data to convert
     */
    public static function toObject(array $array): object
    {
        return (object) json_decode(json_encode($array));
    }

    /**
     * Convert object to array
     *
     * @param mixed $data input a object or JSON string
     */
    public static function toArray($data): array
    {
        if (\is_string($data) && self::isJson($data)) {
            return (array) json_decode($data, true);
        }

        $result = [];
        $data   = (array) $data;
        foreach ($data as $key => $value) {
            if (\is_object($value)) {
                $value = (array) $value;
            }

            if (\is_array($value)) {
                $result[$key] = self::toArray($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Wrapper for json_decode that throws when an error occurs.
     *
     * @param string $json  JSON data to parse
     * @param bool   $assoc when true, returned objects will be converted
     *                      into associative arrays
     *
     * @throws \InvalidArgumentException if the JSON cannot be decoded
     *
     * @return mixed
     *
     * @link https://www.php.net/manual/en/function.json-decode.php
     */
    public static function jsonDecode(string $json, bool $assoc = false)
    {
        $data = json_decode($json, $assoc);

        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException('json_decode error: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Wrapper for JSON encoding that throws when an error occurs.
     *
     * @param mixed $value   The value being encoded
     * @param int   $options JSON encode option bitmask
     *
     * @throws \InvalidArgumentException if the JSON cannot be encoded
     *
     * @link https://www.php.net/manual/en/function.json-encode.php
     */
    public static function jsonEncode($value, int $options = 0): string
    {
        $json = json_encode($value, $options);

        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException('json_encode error: ' . json_last_error_msg());
        }

        return (string) $json;
    }
}
