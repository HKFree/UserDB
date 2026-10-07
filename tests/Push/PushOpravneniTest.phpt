<?php

use App\Services\Push\PushOpravneni;
use Tester\Assert;

require __DIR__ . '/../../vendor/autoload.php';
Tester\Environment::setup();

// VV a TECH smí vše
foreach ([['VV'], ['TECH'], ['SO-1', 'VV']] as $role) {
    Assert::true(PushOpravneni::smiOdeslat($role, 'sit', null));
    Assert::true(PushOpravneni::smiOdeslat($role, 'oblast', 99));
}

// SO/ZSO jen svou oblast (u AP předává volající oblast AP)
Assert::true(PushOpravneni::smiOdeslat(['SO-1'], 'oblast', 1));
Assert::true(PushOpravneni::smiOdeslat(['ZSO-1'], 'ap', 1));
Assert::false(PushOpravneni::smiOdeslat(['SO-1'], 'oblast', 2));
Assert::false(PushOpravneni::smiOdeslat(['SO-1'], 'ap', 2));
Assert::false(PushOpravneni::smiOdeslat(['SO-1'], 'sit', null));
Assert::false(PushOpravneni::smiOdeslat(['SO-12'], 'oblast', 1)); // ne podřetězec
Assert::false(PushOpravneni::smiOdeslat(['DRUŽSTEVNÍK-1', 'KONTROLA'], 'oblast', 1));
Assert::false(PushOpravneni::smiOdeslat([], 'oblast', 1));

Assert::true(PushOpravneni::smiSpravovatKanaly(['VV']));
Assert::false(PushOpravneni::smiSpravovatKanaly(['TECH', 'SO-1']));
