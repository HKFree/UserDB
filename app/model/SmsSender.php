<?php declare(strict_types=1);

namespace App\Model;


/**
 * Class SmsSender
 * @author Pavel Kriz <pavkriz@hkfree.org>
 */
class SmsSender
{
    /** @var string */
    private $pythonScript;

    public function __construct(string $pythonScript) {
        $this->pythonScript = $pythonScript;
    }

    /**
     * Odesle SMS zpravu $message adresatum $recipientMsisdns. Odesilatelem bude uzivatel $senderIdentity.
     *
     * @param HkfIdentity $senderIdentity
     * @param array $recipientMsisdns
     * @param string $message
     * @return string
     */
    public function sendSms(HkfIdentity $senderIdentity, array $recipientMsisdns, string $message): string {
        $locale = 'cs_CZ.UTF-8';
        setlocale(LC_ALL, $locale);
        putenv('LC_ALL='.$locale);
        $command = escapeshellcmd('python '.$this->pythonScript.
            ' -a https://aweg3.maternacz.com -l hkf'.$senderIdentity->getUid().'-'.$senderIdentity->getNick().
            ':'.base64_decode($senderIdentity->getPasswordHash()).'
             -d '.implode(',', $recipientMsisdns).
            ' "'.$message.'"');
        return shell_exec($command);
    }
}
