<?php

namespace App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendSMSCommand extends Command {

    protected function configure() {
        $this->setName('app:send_sms')
                ->setDescription('Poslat SMS pomoci zabudovane GQ');
        $this->addArgument('cislo', InputArgument::REQUIRED, 'Cilove cislo SMS');
        $this->addArgument('text', InputArgument::REQUIRED, 'Text pro odeslani');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $smsNumber = $input->getArgument('cislo');
        $smsText = $input->getArgument('text');

        $mexSender = $this->getHelper('container')->getByType('\App\Services\MexSmsSender');
        $mexSender->sendSMS([$smsNumber], $smsText);
    }

}
