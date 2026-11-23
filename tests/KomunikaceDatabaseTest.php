<?php

declare(strict_types=1);

namespace Test;

use App\Model\Komunikace;
use App\Services\SmsSenderException;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class KomunikaceDatabaseTest extends DatabaseTestCase
{
    public function testContainerUsesDatabaseRecipientsAndRecordingSender(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $valid = $this->fixtures->member($ap->id, ['telefon' => '+420 777 222 555']);
        $invalid = $this->fixtures->member($ap->id, ['telefon' => 'neplatné']);
        $model = $this->container->getByType(Komunikace::class);
        $sender = $this->container->getByType(RecordingMexSmsSender::class);

        Assert::true($model->maValidniCislo($valid->id));
        Assert::false($model->maValidniCislo($invalid->id));
        foreach ([[], [$invalid->id]] as $recipients) {
            Assert::exception(
                fn () => $model->posliSMS($recipients, 'Test'),
                SmsSenderException::class,
                'Žádný z vybraných účastníků nemá platné české telefonní číslo.',
            );
            Assert::same([], $sender->calls);
        }

        Assert::same(1, $model->posliSMS([$invalid->id, $valid->id], 'Testovací zpráva'));
        Assert::same([[['+420 777 222 555'], 'Testovací zpráva']], $sender->calls);
    }
}

(new KomunikaceDatabaseTest())->run();
