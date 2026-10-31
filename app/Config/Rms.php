<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Connection settings for the shared RMS messaging backend (SQL Server `dbRMSAndroid`,
 * the same database the RMS Android app uses). Values come from .env (rms.*).
 */
class Rms extends BaseConfig
{
    public string $dbServer = '192.168.32.45\sqlexpress';
    public string $dbName   = 'dbRMSAndroid';
    public string $dbUser   = '';
    public string $dbPass   = '';

    /** Firebase service-account JSON used for FCM v1 pushes (kept outside this repo). */
    public string $fcmKeyFile  = 'E:\html\rms-alerts-219dd.json';
    public string $fcmChannel  = 'rms_alerts_channel_v6';
    public bool $fcmVerifySSL  = true;
    public int $fcmTimeout     = 4;

    /** Web root that serves chat images (tbl_Messages.imagePath is relative to it). */
    public string $mediaBaseUrl = 'http://192.168.32.43/';
}
