<?php

namespace Test;

use Nette\Application\BadRequestException;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\DI\Container;
use Tester\Assert;

final class PermissionScenario
{
    public readonly ActiveRow $area;
    public readonly ActiveRow $otherArea;
    public readonly ActiveRow $ap;
    public readonly ActiveRow $otherAp;
    public readonly ActiveRow $member;
    public readonly ActiveRow $otherMember;
    /** @var array<string, ActiveRow> */
    public readonly array $actors;

    public function __construct(Fixtures $fixtures, private Explorer $database, private Container $container) {
        $this->area = $fixtures->area();
        $this->otherArea = $fixtures->area();
        $this->ap = $fixtures->ap($this->area->id);
        $this->otherAp = $fixtures->ap($this->otherArea->id);
        $cooperative = ['druzstvo' => 1, 'spolek' => 0, 'smazano' => 0, 'TypClenstvi_id' => null];
        $this->member = $fixtures->member($this->ap->id, $cooperative);
        $this->otherMember = $fixtures->member($this->otherAp->id, $cooperative);
        $actors = [];
        foreach (['vv' => 'VV', 'tech' => 'TECH', 'ownSo' => 'SO', 'otherSo' => 'SO', 'cooperative' => 'DRUŽSTEVNÍK', 'support' => 'EXTSUPPORT'] as $key => $role) {
            $actors[$key] = $fixtures->member($key === 'otherSo' ? $this->otherAp->id : $this->ap->id, $cooperative);
            $areaId = $role === 'SO' ? ($key === 'otherSo' ? $this->otherArea->id : $this->area->id) : null;
            $fixtures->role($actors[$key]->id, $role, $areaId);
        }
        $this->actors = $actors;
    }

    public function assertDenied(callable $request): void {
        $before = $this->snapshot();
        Assert::exception($request, BadRequestException::class, 'Nemáte oprávnění.', 403);
        Assert::same($before, $this->snapshot(), 'A denied request changed data or recorded an external call.');
    }

    public function snapshot(): array {
        $state = [];
        foreach (['Oblast', 'Ap', 'Uzivatel', 'SpravceOblasti', 'IPAdresa', 'Subnet', 'CestneClenstviUzivatele', 'PrichoziPlatba', 'UzivatelskeKonto', 'SloucenyUzivatel', 'Log', 'ApiKlic', 'AplikaceToken'] as $table) {
            $rows = array_map(static fn (ActiveRow $row) => $row->toArray(), $this->database->table($table)->order('id')->fetchAll());
            $state[$table] = json_encode($rows, JSON_THROW_ON_ERROR);
        }
        foreach ([RecordingMailer::class, RecordingSmsSender::class, RecordingMexSmsSender::class, RecordingContractRequest::class] as $type) {
            $state[$type] = serialize($this->container->getByType($type));
        }
        return $state;
    }

    public static function assertRedirect(PresenterResult $result, string $path, int $status = 303): void {
        Assert::same($status, $result->status);
        Assert::same('http://localhost/userdb/' . $path, explode('?', $result->redirectUrl ?? '')[0]);
    }
}
