<?php

namespace App\Console;

use App\Services\Push\PushWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:push_send', description: 'Odeslat čekající push notifikace (spouští cron každou minutu).')]
class PushSendCommand extends Command
{
    public function __construct(private PushWorker $worker) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $output->writeln('Zpracováno notifikací: ' . $this->worker->zpracuj());
        return 0;
    }
}
