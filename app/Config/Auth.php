<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * NEXUS-L authentication policy. Overridden from .env (auth.*).
 */
class Auth extends BaseConfig
{
    /** Failed logins before the account is temporarily locked. */
    public int $maxLoginAttempts = 5;

    /** Lock duration after too many failed attempts. */
    public int $lockoutMinutes = 15;

    /** Logged-in users are signed out after this period of inactivity. */
    public int $idleTimeoutMinutes = 30;

    /** Lifetime of the "Remember me" cookie. */
    public int $rememberDays = 30;

    public string $rememberCookie = 'nexusl_remember';

    /** Forgot-password confirmation code lifetime. */
    public int $resetCodeTtlMinutes = 10;

    /** Wrong code entries allowed before the code is burned. */
    public int $resetMaxAttempts = 5;

    /** Minimum password length for registration / reset. */
    public int $minPasswordLength = 8;
}
