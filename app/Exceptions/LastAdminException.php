<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Deleting or demoting this account would leave the app without an admin,
 * and only an admin can create accounts or reset passwords.
 */
class LastAdminException extends RuntimeException {}
