<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Claude could not turn a description into a unit design. The message is shown to the admin.
 */
class UnitDesignFailed extends RuntimeException {}
