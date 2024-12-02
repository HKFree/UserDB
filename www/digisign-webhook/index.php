<?php

$req_dump = print_r($_REQUEST, true);
$fp = file_put_contents('/tmp/digisign-webhook-request.log', $req_dump, FILE_APPEND);

