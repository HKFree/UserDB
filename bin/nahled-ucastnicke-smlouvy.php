#!/usr/bin/env php
<?php

/**
 * Vygeneruje PDF účastnickou smlouvu podle šablony, viz ../pdfGenerator
 */

require __DIR__ . '/parametrySmlouvy.php';

$container =  require __DIR__ . '/../app/bootstrap.php';

if (!getenv('PDF_GENERATOR_URL')) {
    print("Missing PDF_GENERATOR_URL environment variable\n");
    die();
}

if (!isset($argv[1])) {
    print("Use: {$argv[0]} <uid>\n");
    die();
}

$uid = $argv[1];
$cislo_smlouvy = isset($argv[2]) ? $argv[2] : null;

$uzivatel = $container->getByType('\App\Model\Uzivatel')->find($uid);
if (!$uzivatel) {
    print("Uzivatel id [$uid] neni v DB\n");
    die();
}

$parametry = parametryUcastnickeSmlouvy($uid, );
if ($cislo_smlouvy) {
    $parametry['cislo'] = $cislo_smlouvy;
}

echo file_get_contents(getenv('PDF_GENERATOR_URL')."/smlouvaUcastnicka.php?".http_build_query($parametry));
