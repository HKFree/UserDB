<?php

declare(strict_types=1);

/** Fixed demo identities and the one chosen by the visitor; usable without Composer, Nette or a database. */
final class UserdbDemoPersonas
{
    public const PATH = '/demo-osoba';

    /** Headings of the switching page, in the order they are shown. */
    public const GROUPS = [
        'vse' => 'Vidí a upravují všechno',
        'oblast' => 'Správci oblastí',
        'omezene' => 'Podpora a role bez práv ve správě',
        'hranicni' => 'Hraniční stavy rolí',
    ];

    /**
     * `roles` are valid today, `inactive` are role rows that exist but do not apply.
     * @var array<int, array{name: string, group: string, roles: list<string>, inactive?: list<string>, note: string}>
     */
    private const PERSONAS = [
        1 => ['name' => 'Tester Testovací', 'group' => 'vse', 'roles' => ['SO-1', 'VV'],
            'note' => 'Výchozí osoba. Role VV otevře každou stránku včetně schvalování a editace oprávnění.'],
        20000 => ['name' => 'Demo Technik', 'group' => 'vse', 'roles' => ['TECH'],
            'note' => 'Upravuje všechny členy a AP a má část nástrojů správy. Sloučení, schvalování čestného členství a oprávnění zůstávají jen pro VV.'],
        20001 => ['name' => 'Demo Kontrolor', 'group' => 'vse', 'roles' => ['KONTROLA'],
            'note' => 'Vidí a upravuje všechny členy a AP, nástroje správy ale nemá.'],
        20070 => ['name' => 'Demo Chybnýodesílatel', 'group' => 'vse', 'roles' => ['KONTROLA'],
            'note' => 'Vlastní e-mail má chybnou syntaxi: e-mail členovi i celému AP odejde jen z adresy oblasti a upozornění odkazuje na editaci vlastních údajů.'],
        20002 => ['name' => 'Demo Malosprávce', 'group' => 'oblast', 'roles' => ['SO-200'],
            'note' => 'Správce malé oblasti s jedním AP. Své členy upravuje, u cizích vidí jen omezený detail.'],
        20003 => ['name' => 'Demo Velkosprávce', 'group' => 'oblast', 'roles' => ['SO-10000'],
            'note' => 'Správce oblasti s mnoha subnety: detail cizího člena, například 100700, se načítá několik sekund. Vyžaduje objemová data.'],
        20004 => ['name' => 'Demo Zástupce', 'group' => 'oblast', 'roles' => ['ZSO-204'],
            'note' => 'Zástupce správce. Cizího člena 20062 smí upravit, protože má IP adresu v jeho subnetu.'],
        20012 => ['name' => 'Demo Chybějícítelefon', 'group' => 'oblast', 'roles' => ['SO-201'],
            'note' => 'Správce rozvětvené oblasti s telefonem „missing“: mezi příjemci SMS správcům chybí.'],
        20013 => ['name' => 'Demo Zahraničnítelefon', 'group' => 'oblast', 'roles' => ['SO-201'],
            'note' => 'Správce rozvětvené oblasti s nečeským číslem, které neprojde kontrolou při odesílání SMS.'],
        20071 => ['name' => 'Demo Odesílatelodjinud', 'group' => 'oblast', 'roles' => ['ZSO--20'],
            'note' => 'Zástupce technické oblasti s chybným vlastním e-mailem, sám patří na AP jiné oblasti: u e-mailu členovi 20015 nebo AP 217 ho upozornění posílá za správcem, své údaje upravit nesmí.'],
        20005 => ['name' => 'Demo Podpora', 'group' => 'omezene', 'roles' => ['EXTSUPPORT'],
            'note' => 'Detail a editace každého člena, ale bez plateb, e-mailů a SMS.'],
        20006 => ['name' => 'Demo Družstevník', 'group' => 'omezene', 'roles' => ['DRUŽSTEVNÍK'],
            'note' => 'Role jen k přihlášení: nástroje správy hlásí, že jsou pro oprávněné, a detail člena je omezený.'],
        20007 => ['name' => 'Demo Týmový', 'group' => 'omezene', 'roles' => ['PMVTEAM'],
            'note' => 'Role jen k přihlášení, stejný pohled jako Družstevník.'],
        20008 => ['name' => 'Demo Skladník', 'group' => 'omezene', 'roles' => ['SKLADNIK'],
            'note' => 'Role jen k přihlášení, stejný pohled jako Družstevník.'],
        20009 => ['name' => 'Demo Představenstvo', 'group' => 'omezene', 'roles' => ['PŘEDSTAVENSTVO'],
            'note' => 'Role jen k přihlášení, stejný pohled jako Družstevník.'],
        20010 => ['name' => 'Demo Prošlárole', 'group' => 'hranicni', 'roles' => ['DRUŽSTEVNÍK'], 'inactive' => ['SO-201 prošlá'],
            'note' => 'Role SO skončila před týdnem, oblast 201 už nespravuje.'],
        20011 => ['name' => 'Demo Budoucírole', 'group' => 'hranicni', 'roles' => ['DRUŽSTEVNÍK'], 'inactive' => ['SO-201 budoucí'],
            'note' => 'Role SO začne až za rok, oblast 201 zatím nespravuje.'],
        20014 => ['name' => 'Demo Dnešníkonec', 'group' => 'hranicni', 'roles' => ['DRUŽSTEVNÍK'], 'inactive' => ['SO-201 do dne načtení'],
            'note' => 'Role SO měla konec v den načtení dat a už neplatí.'],
        20015 => ['name' => 'Demo Systémový', 'group' => 'hranicni', 'roles' => ['ZSO-201'],
            'note' => 'Systémový uživatel jako zástupce správce; exporty správců ho vynechávají.'],
    ];

    private readonly int $id;

    public function __construct(mixed $value) {
        // Check the string before any conversion: arrays, signs, exponents and suffixes are not IDs.
        $this->id = is_string($value) && preg_match('~\A[0-9]+\z~', $value) === 1 && isset(self::PERSONAS[$value])
            ? (int) $value
            : 1;
    }

    public static function cookieName(string $root): string {
        // Cookies share a host across ports; use the same worktree hash as the PHP session name.
        return 'userdb_demo_persona_' . substr(hash('sha256', $root), 0, 20);
    }

    /** @return array{userID: int, userName: string, passwordHash: string} */
    public function fakeUser(): array {
        return ['userID' => $this->id, 'userName' => $this->id === 1 ? 'tester' : 'demo' . $this->id, 'passwordHash' => 'xxx'];
    }

    public function id(): int {
        return $this->id;
    }

    public function name(): string {
        return self::PERSONAS[$this->id]['name'];
    }

    public function label(): string {
        $persona = self::PERSONAS[$this->id];
        return $this->id . ': ' . $persona['name'] . ' (' . implode(', ', $persona['roles']) . ')';
    }

    /** @return array<int, array{name: string, group: string, roles: list<string>, inactive?: list<string>, note: string}> */
    public static function all(): array {
        return self::PERSONAS;
    }
}
