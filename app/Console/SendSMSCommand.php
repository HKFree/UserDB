<?php

namespace App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Services\MexSmsSender;

#[AsCommand(
    name: 'app:send_sms',
    description: 'Poslat SMS pomoci MexSMS API.'
)]
class SendSMSCommand extends Command {
    private MexSmsSender $mexSender;

    public function __construct(MexSmsSender $m) {
        parent::__construct();
        $this->mexSender = $m;
    }

    protected function configure() {
        $this->addArgument('cislo', InputArgument::REQUIRED, 'Cilove cislo SMS');
        $this->addArgument('text', InputArgument::REQUIRED, 'Text pro odeslani');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $smsNumber = $input->getArgument('cislo');
        $smsText = $input->getArgument('text');

        //$mexSender = $this->getHelper('container')->getByType('\App\Services\MexSmsSender');
        $this->mexSender->sendSMS([$smsNumber], $smsText);
        return 0;
    }

}
