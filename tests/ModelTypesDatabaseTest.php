<?php

declare(strict_types=1);

namespace Test;

use App\Model\ApiKlic;
use App\Model\AplikaceToken;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class ModelTypesDatabaseTest extends DatabaseTestCase
{
    public function testKeyExpiryAndDecorationKeepDatabaseValues(): void {
        $model = $this->container->getByType(ApiKlic::class);
        $forever = $this->fixtures->apiKey(expires: null);
        $expired = $this->fixtures->apiKey(expires: '2001-01-01');
        $valid = $this->fixtures->apiKey(expires: '2099-12-31');
        $keys = $model->getApiKliceTable(['id' => [$forever->id, $expired->id, $valid->id]])->fetchAssoc('id');
        $decorated = $model->decorateKeys($keys);

        Assert::false($decorated[$forever->id]['expired']);
        Assert::true($decorated[$expired->id]['expired']);
        Assert::false($decorated[$valid->id]['expired']);
        Assert::same($decorated, $keys);
        Assert::null($keys[$forever->id]['plati_do']);
        Assert::same('2001-01-01', $keys[$expired->id]['plati_do']->format('Y-m-d'));
        Assert::true($model->deleteApiKlice([]));
        Assert::same(1, $model->deleteApiKlice([(string) $expired->id]));
        Assert::null($model->getApiKlic($expired->id));
    }

    public function testTokenBelongsToItsMemberAndCanBeRevoked(): void {
        $model = $this->container->getByType(AplikaceToken::class);
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id);
        $other = $this->fixtures->member($ap->id);
        $created = $model->createAplikaceToken((string) $member->id);
        Assert::type(ActiveRow::class, $created);
        Assert::same(64, strlen($created->token));
        $created->update(['pouzit_naposledy' => '2001-01-01 00:00:00']);

        Assert::false($model->verifyToken($other->id, $created->token));
        Assert::false($model->verifyToken($member->id, 'unknown-token'));
        Assert::true($model->verifyToken((string) $member->id, $created->token));
        Assert::notSame('2001-01-01', $this->requireRow($this->database->table('AplikaceToken')->get($created->id))->pouzit_naposledy->format('Y-m-d'));

        $model->deleteTokensForUID((string) $member->id);
        Assert::false($model->verifyToken($member->id, $created->token));
        Assert::null($this->database->table('AplikaceToken')->get($created->id));
    }

    public function testMissingTokenDoesNotAuthenticateOrUpdateUsage(): void {
        $model = $this->container->getByType(AplikaceToken::class);
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id);
        $token = $this->fixtures->insert('AplikaceToken', [
            'Uzivatel_id' => $member->id,
            'token' => str_repeat('x', 64),
            'pouzit_poprve' => '2001-01-01 00:00:00',
            'pouzit_naposledy' => '2001-01-01 00:00:00',
        ]);

        foreach ([null, ''] as $input) {
            Assert::false($model->verifyToken($member->id, $input));
        }
        Assert::same('2001-01-01 00:00:00', $this->requireRow($this->database->table('AplikaceToken')->get($token->id))->pouzit_naposledy->format('Y-m-d H:i:s'));
    }

    public function testMissingPasswordKeepsLegacyHashInputHandling(): void {
        $model = $this->container->getByType(\App\Model\Uzivatel::class);
        Assert::same('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $model->generateStrongHash(null));
    }
}

(new ModelTypesDatabaseTest())->run();
