<?php

namespace App\Services\GoogleDrive;

use RuntimeException;

/**
 * A Google Drive call that failed. The message is written for the user and safe to show in the UI.
 */
class GoogleDriveException extends RuntimeException {}
