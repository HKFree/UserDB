<?php

declare(strict_types=1);

namespace Test;

use App\Model\SpravceOblasti;
use App\Model\Uzivatel;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class RecordConditionsDatabaseTest extends DatabaseTestCase
{
    public function testDuplicateContactsDistinguishMissingOwnAndOtherMember(): void {
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        $member = $this->fixtures->member($ap->id, [
            'email' => 'duplicate@example.invalid',
            'email2' => 'secondary@example.invalid',
            'telefon' => '777111222',
            'spolek' => 1,
        ]);
        $other = $this->fixtures->member($ap->id);
        $model = $this->container->getByType(Uzivatel::class);
        $expected = $ap->jmeno . ' (' . $ap->id . ')';

        Assert::null($model->getDuplicateEmailArea('missing@example.invalid', $other->id));
        Assert::null($model->getDuplicatePhoneArea('777999888', $other->id));
        Assert::null($model->getDuplicateEmailArea($member->email, $member->id));
        Assert::null($model->getDuplicatePhoneArea($member->telefon, $member->id));
        Assert::same($expected, $model->getDuplicateEmailArea($member->email, $other->id));
        Assert::same($expected, $model->getDuplicateEmailArea($member->email2, $other->id));
        Assert::same($expected, $model->getDuplicatePhoneArea($member->telefon, $other->id));

        $member->update(['spolek' => 0, 'druzstvo' => 0]);
        Assert::null($model->getDuplicateEmailArea($member->email, $other->id));
        Assert::null($model->getDuplicatePhoneArea($member->telefon, $other->id));
    }

    public function testActiveMemberRuleInPhpMatchesSqlCondition(): void {
        Assert::true(Uzivatel::jeAktivniClen(1, 3, 0, 0));
        Assert::false(Uzivatel::jeAktivniClen(1, 1, 0, 0));
        Assert::false(Uzivatel::jeAktivniClen(1, 0, 0, 0));
        Assert::false(Uzivatel::jeAktivniClen(0, 3, 0, 0));
        Assert::true(Uzivatel::jeAktivniClen(0, null, 1, 0));
        Assert::false(Uzivatel::jeAktivniClen(0, null, 1, 1));

        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $model = $this->container->getByType(Uzivatel::class);
        foreach ([0, 1] as $spolek) {
            foreach ([null, 0, 1, 2, 3] as $typClenstviId) {
                foreach ([0, 1] as $druzstvo) {
                    foreach ([0, 1] as $smazano) {
                        $member->update(['spolek' => $spolek, 'TypClenstvi_id' => $typClenstviId, 'druzstvo' => $druzstvo, 'smazano' => $smazano]);
                        Assert::same(
                            $model->findAll()->where(Uzivatel::AKTIVNI_CLEN)->where('id', $member->id)->count('*') === 1,
                            Uzivatel::jeAktivniClen($spolek, $typClenstviId, $druzstvo, $smazano),
                            'spolek=' . $spolek . ', TypClenstvi_id=' . ($typClenstviId ?? 'null') . ', druzstvo=' . $druzstvo . ', smazano=' . $smazano,
                        );
                    }
                }
            }
        }
    }

    public function testAreaRoleRequiresAnExistingCurrentlyValidRecord(): void {
        $area = $this->fixtures->area();
        $otherArea = $this->fixtures->area();
        $member = $this->fixtures->member($this->fixtures->ap($area->id)->id);
        $model = $this->container->getByType(SpravceOblasti::class);

        Assert::null($model->getUserRole($member->id, $area->id));
        $role = $this->fixtures->role($member->id, 'SO', $area->id);
        Assert::same('SO', $model->getUserRole($member->id, $area->id));
        Assert::null($model->getUserRole($member->id, $otherArea->id));

        $role->update(['do' => '2001-01-01']);
        Assert::null($model->getUserRole($member->id, $area->id));
        $role->update(['od' => '2098-01-01', 'do' => '2099-12-31']);
        Assert::null($model->getUserRole($member->id, $area->id));
        $role->update(['od' => '2000-01-01', 'do' => null]);
        Assert::same('SO', $model->getUserRole($member->id, $area->id));
    }
}

(new RecordConditionsDatabaseTest())->run();
