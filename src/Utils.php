<?php

namespace ManCurl;

final class Utils
{
    public const JSON_PATTERN = '/^(?:application|text)\/(?:[a-z]+(?:[\.-][0-9a-z]+){0,}[\+\.]|x-)?json(?:-[a-z]+)?/i';

    /**
     * Sometime we need to add content-type as Content-Type to matching request from app,
     * in this section we will use the the key from request input.
     *
     *  @return bool true if already have content-type, false otherwise
     *
     * @psalm-suppress MixedArgumentTypeCoercion
     */
    public static function contentTypeMatch(array $headers): bool
    {
        return !empty($headers) && \in_array('content-type', array_map('strtolower', array_keys($headers)));
    }

    /**
     * Generates random multipart boundary string.
     */
    public static function generateMultipartBoundary(int $length = 30): string
    {
        return (static function (int $length) {
            $string = '';

            while (($len = \strlen($string)) < $length) {
                $size  = $length - $len;
                $bytes = random_bytes(max(1, $size));
                $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
            }

            return $string;
        })($length);
    }

    /**
     * Determine if a given string is valid JSON.
     *
     * @psalm-suppress UnusedFunctionCall
     */
    public static function isJson(mixed $value): bool
    {
        if (! \is_string($value)) {
            return false;
        }

        try {
            self::jsonDecode($value, true);
        } catch (\JsonException) {
            return false;
        }

        return true;
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

            if ($moveResult) {
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
     *
     * @throws \JsonException
     */
    public static function toObject(array $array): object
    {
        return (object) self::jsonDecode(self::jsonEncode($array));
    }

    /**
     * Convert data to array
     *
     * @throws \JsonException
     */
    public static function toArray(string|object|array $data): array
    {
        if (\is_string($data) && self::isJson($data)) {
            return (array) self::jsonDecode($data, true);
        }

        $result = [];
        $data   = (array) $data;

        /** @var string|object|array $value */
        foreach ($data as $key => $value) {
            if (\is_object($value)) {
                $value = (array) $value;
            }

            $result[$key] = \is_array($value) ? self::toArray($value) : $value;
        }

        return $result;
    }

    /**
     * Wrapper for json_decode that throws when an error occurs.
     *
     * @throws \JsonException if the JSON cannot be decoded
     */
    public static function jsonDecode(string $json, bool $assoc = false): mixed
    {
        return json_decode($json, $assoc, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * Wrapper for JSON encoding that throws when an error occurs.
     *
     * @throws \JsonException if the JSON cannot be encoded
     */
    public static function jsonEncode(mixed $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR);
    }

    /**
     * Query param parser.
     */
    public static function paramParser(mixed $value): mixed
    {
        // change to string if has boolean value
        return \is_bool($value) ? var_export($value, true) : $value;
    }

    /**
     * Merge array and remove the first one duplicate key with case sensitive.
     *
     * @psalm-suppress MixedArgumentTypeCoercion
     */
    public static function mergeCaseless(array $first, array $last): array
    {
        $opt = array_map('strtolower', array_keys($last));
        foreach (array_keys($first) as $key) {
            if (\in_array(strtolower($key), $opt)) {
                unset($first[$key]);
            }
        }

        return array_merge($first, $last);
    }
}
