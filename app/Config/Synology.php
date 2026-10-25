<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Synology Chat incoming-webhook bot settings.
 * Values are overridden from .env (synology.*).
 */
class Synology extends BaseConfig
{
    public string $baseUrl   = 'https://192.168.32.130:5001/webapi/entry.cgi';
    public string $botToken  = '';
    public bool $verifySSL   = false;
    public int $timeout      = 10;
    public string $botPrefix = '[NEXUS-L]';
}
