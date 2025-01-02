<?php

/* migrace 2025 temporary */

namespace App\Console;

use App\Model\Uzivatel;
use App\Services;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:send_email_with_contract_button',
    description: 'Odeslat migracni email Mig1'
)]
class SendEmailWithContractButton extends Command
{
    private $uzivatelModel;
    private $mailService;
    private $cryptosvc;
    private $stitkovac;

    public function __construct(
        Uzivatel $uzivatelModel,
        Services\CryptoSluzba $cryptosvc,
        Services\MailService $mailsvc,
        Services\Stitkovac $stitkovac,
    ) {
        parent::__construct();
        $this->cryptosvc = $cryptosvc;
        $this->mailService = $mailsvc;
        $this->stitkovac = $stitkovac;
        $this->uzivatelModel = $uzivatelModel;
    }

    protected function configure() {
        $this->addArgument('uid', InputArgument::REQUIRED, 'ID uzivatele');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $user_id = $input->getArgument('uid');
        $uzivatel =  $this->uzivatelModel->find($user_id);
        if (!$uzivatel) {
            throw new \Exception("Nezname UID $user_id");
        }

        if (!$uzivatel->oneclick_auth) {
            $code_length = 32;
            $oneclick_auth_code = substr(str_shuffle(str_repeat($x = '23456789abcdefghijmnopqrstuvwxyzABCDEFGHJMNPQRSTUVWXYZ', ceil($code_length / strlen($x)))), 1, $code_length);
            $oneclick_auth_code_encrypted = $this->cryptosvc->encrypt($oneclick_auth_code);
            $this->uzivatelModel->update($uzivatel->id, [
                'oneclick_auth' => $oneclick_auth_code_encrypted,
            ]);
        } else {
            $oneclick_auth_code = $this->cryptosvc->decrypt($uzivatel->oneclick_auth);
            $this->uzivatelModel->update($uzivatel->id, ['oneclick_auth_used_at' => null]);
        }

        $this->mailService->sendSubscriberContractCallToActionEmail($uzivatel, $oneclick_auth_code);
        $this->stitkovac->addStitek($uzivatel, 'Mig1');

        return 0;
    }
}
