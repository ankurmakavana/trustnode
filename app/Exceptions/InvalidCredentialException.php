<?php

namespace App\Exceptions;

/**
 * Thrown when a stored credential cannot be decrypted.
 *
 * This occurs when:
 * - A legacy bcrypt-hashed value is encountered (one-way, not recoverable)
 * - The encryption key has been rotated and the value cannot be decrypted
 * - The stored value is corrupted
 *
 * The remedy is for the user to re-enter the credential.
 */
class InvalidCredentialException extends \RuntimeException
{
}
