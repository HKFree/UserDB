<?php

namespace App\Console;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:push_vapid_keys', description: 'Vygenerovat pár VAPID klíčů pro env proměnné.')]
class PushVapidKeysCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $k = VAPID::createVapidKeys();
        $output->writeln("USERDB_VAPID_PUBLIC_KEY={$k['publicKey']}");
        $output->writeln("USERDB_VAPID_PRIVATE_KEY={$k['privateKey']}");
        return 0;
    }
}
