<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\HkfIdentity;
use App\Model\AwegUser;

/**
 * Class SmsSender
 * @author Pavel Kriz <pavkriz@hkfree.org>
 */
class SmsSender
{
    public const STATUS_OK = 0;
    public const STATUS_NO_ACCOUNT = 1;

    /** @var string */
    private $pythonScript;

    /** @var App\Model\AwegUser */
    private $awegUser;

    public function __construct(string $pythonScript, AwegUser $awegUser) {
        $this->pythonScript = $pythonScript;
        $this->awegUser = $awegUser;
    }

    /**
     * Odesle SMS zpravu $message adresatum $recipientMsisdns. Odesilatelem bude uzivatel $senderIdentity.
     *
     * @param HkfIdentity $senderIdentity
     * @param array $recipientMsisdns
     * @param string $message
     * @return string
     */
    public function sendSms(HkfIdentity $senderIdentity, array $recipientMsisdns, string $message) {
        $awegUser = $this->awegUser->getAwegUser($senderIdentity->getUid());

        if (!$awegUser) {
            return (["status" => SmsSender::STATUS_NO_ACCOUNT, "msg" => "Nemáte účet v AWEG SMS systému!"]);
        }

        $locale = 'cs_CZ.UTF-8';
        setlocale(LC_ALL, $locale);
        putenv('LC_ALL='.$locale);
        $command = escapeshellcmd('python '.$this->pythonScript.
            ' -a https://aweg3.maternacz.com -l '.$awegUser->aweg_name.
            ':'.base64_decode($senderIdentity->getPasswordHash()).'
             -d '.implode(',', $recipientMsisdns).
            ' "'.$message.'"');
        return (["status" => SmsSender::STATUS_OK, "msg" => shell_exec($command)]);
    }
}
