<?php

namespace App\Imports;

use RuntimeException;

/** Thrown from a progress callback when the user asked to cancel a running import. */
class ImportCancelledException extends RuntimeException {}
