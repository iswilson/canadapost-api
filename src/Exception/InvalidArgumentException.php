<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Exception;

/**
 * Invalid input to library before request is sent.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface {}
