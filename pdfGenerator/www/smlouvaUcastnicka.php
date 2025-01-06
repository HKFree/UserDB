<?php

/**
 * Generátor PDF dokumentu podle ODS šablony s nahrazením placeholderů
 * Použití: http://localhost:10109/smlouvaUcastnicka.php?jmeno_prijmeni=Josef+Skočdopole&telefon=158
 */

$TEMPLATE_FILE_NAME = "SmlouvaUcastnicka_v7_template.odt";

chdir("/tmp");
$templateRandomizedName = str_replace('.odt', sprintf('_%u', rand(1, 1e9)), $TEMPLATE_FILE_NAME);

/**
 * Rozbalit ODS
 */
system("unzip -q -o /opt/templates/$TEMPLATE_FILE_NAME -d $templateRandomizedName");

/**
 * Načíst content.xml a nahradit placeholdery: {jmeno_prijmeni}, {telefon} a podobně
 */
$content = file_get_contents("$templateRandomizedName/content.xml");
$content = str_replace(
    array_map(fn ($s): string => '{'.$s.'}', array_keys($_GET)),
    array_values($_GET),
    $content
);
// Zbylý placeholdery vyhodit
$content = preg_replace('/\{[a-zA-Z0-9-._]+\}/', '', $content, -1);

file_put_contents("$templateRandomizedName/content.xml", $content);

/**
 * Zpátky zabalit ODS
 */
system("cd $templateRandomizedName && zip -q -0 -X ../$templateRandomizedName.odt mimetype && zip -q -r ../$templateRandomizedName.odt * -x mimetype");

/**
 * Konverze ODS -> PDF
 */
$num_attempts = 0;
while (++$num_attempts <= 10) {
    system("/usr/bin/libreoffice --headless --convert-to pdf $templateRandomizedName.odt --outdir /tmp >/dev/null");

    if (file_exists("/tmp/$templateRandomizedName.pdf")) {
        break;
    }
    sleep(5);
}

/**
 * výstup - hotové PDFko
 */
header("Content-type: application/pdf");
echo file_get_contents("/tmp/$templateRandomizedName.pdf");

/**
 * úklid
 */
system("rm -r /tmp/$templateRandomizedName");
system("rm -r /tmp/$templateRandomizedName.odt");
system("rm -r /tmp/$templateRandomizedName.pdf");
