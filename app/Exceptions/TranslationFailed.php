<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Claude could not translate a piece of website content; the record keeps its previous translation.
 */
class TranslationFailed extends RuntimeException {}
