<?php

namespace App\Imports;

use RuntimeException;

/** A problem with the uploaded file as a whole (unreadable, missing columns, too many rows); its message is shown to the user. */
class ImportFileException extends RuntimeException {}
