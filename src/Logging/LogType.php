<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Logging;

/**
 * Types of log records, added to context to assist with parsing.
 *
 * HTTP request/response messages are split into sections by ERROR_SEPARATOR and
 * BODY_SEPARATOR, with the body last.
 */
enum LogType: string
{
    public const string CONTEXT_KEY = 'canadapost_api.log_type';
    public const string BODY_SEPARATOR = "\n-------- BODY\n";
    public const string ERROR_SEPARATOR = "\n-------- ERRORS\n";

    case Request = 'request';
    case Response = 'response';
}
