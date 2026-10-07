<?php

use App\Services\Push\PushObsah;
use Tester\Assert;

require __DIR__ . '/../../vendor/autoload.php';
Tester\Environment::setup();

Assert::same([], PushObsah::chyby('Výpadek', 'AP Testovací je mimo provoz.', null));
Assert::same([], PushObsah::chyby('Výpadek', 'Text', 'https://moje.hkfree.org/userdb/clen/notifikace'));
Assert::count(1, PushObsah::chyby('', 'Text', null));
Assert::count(1, PushObsah::chyby(str_repeat('ž', 81), 'Text', null));
Assert::same([], PushObsah::chyby(str_repeat('ž', 80), str_repeat('ž', 250), null));
Assert::count(1, PushObsah::chyby('T', str_repeat('a', 251), null));

foreach (['https://hkfree.org', 'https://www.hkfree.org/x?y=1#z', 'https://a.b.hkfree.org/'] as $ok) {
    Assert::true(PushObsah::jePovolenaUrl($ok), $ok);
}
foreach ([
    'http://hkfree.org/', 'https://evilhkfree.org/', 'https://hkfree.org.evil.com/', 'https://evil.com\.hkfree.org/',
    'https://evil.com/.hkfree.org', 'https://user@hkfree.org/', 'https://hkfree.org:8443/', 'javascript:alert(1)//hkfree.org',
    "https://hkfree.org/\nx", 'https://hkfree.org\@evil.com',
] as $bad) {
    Assert::false(PushObsah::jePovolenaUrl($bad), $bad);
}

$povolene = ['https://fcm.googleapis.com', 'https://*.push.apple.com', 'http://127.0.0.1:9999'];
Assert::true(PushObsah::jePovolenyEndpoint('https://fcm.googleapis.com/fcm/send/abc', $povolene));
Assert::true(PushObsah::jePovolenyEndpoint('https://web.push.apple.com/QGx', $povolene));
Assert::true(PushObsah::jePovolenyEndpoint('http://127.0.0.1:9999/push/1', $povolene));
foreach ([
    'http://fcm.googleapis.com/x', 'https://fcm.googleapis.com.evil.com/x', 'https://evil.com/fcm.googleapis.com',
    'https://u:p@fcm.googleapis.com/x', 'https://fcm.googleapis.com:444/x', 'http://127.0.0.1/x', 'http://10.107.0.1:9999/x',
    'https://fcm.googleapis.com\@evil.com/', 'nonsense',
] as $bad) {
    Assert::false(PushObsah::jePovolenyEndpoint($bad, $povolene), $bad);
}
