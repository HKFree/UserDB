<?php

/**
 * Generátor PDF dokumentu podle ODT šablony s nahrazením placeholderů
 * Použití: http://localhost:10109/smlouvaUcastnicka.php?jmeno_prijmeni=Josef+Skočdopole&telefon=158
 */

require_once __DIR__ . '/ContractPdf.php';
$pdf = ContractPdf::generate(
    $_GET,
    (getenv('USERDB_PDF_TEMPLATES') ?: '/opt/templates') . '/SmlouvaUcastnicka_v7_template.odt',
    getenv('USERDB_LIBREOFFICE') ?: '/usr/bin/libreoffice',
    getenv('USERDB_PDF_CACHE') ?: sys_get_temp_dir()
);

/**
 * výstup - hotové PDFko
 */
header("Content-type: application/pdf");
echo $pdf;
