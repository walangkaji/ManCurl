<?php

namespace ManCurl\Exception;

/**
 * Exception when response cannot parse to DTO model
 */
class ResponseModelException extends \Exception
{
    public function __construct(\Exception|\TypeError $e)
    {
        $trace = $e->getTrace();
        /** @var array $lastTrace */
        $lastTrace = end($trace);
        $function  = (string) $lastTrace['function'];
        $message   = "Error from '$function' with: " . $e->getMessage();
        parent::__construct($message);
    }
}
