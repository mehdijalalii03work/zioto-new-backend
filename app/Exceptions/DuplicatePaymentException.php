<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A second payment attempt was claimed while another attempt
 * (pending, processing or initiating) is still open.
 */
final class DuplicatePaymentException extends RuntimeException {}
