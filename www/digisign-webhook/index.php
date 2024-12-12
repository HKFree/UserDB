<?php

include 'WebhookReceiver.php';

$body = file_get_contents('php://input');
// $body = '{"id":"0193b88b-67d0-70c2-a4e6-fed356998bac","event":"envelopeCompleted","name":"envelope.completed","time":"2024-12-12T02:46:04+01:00","entityName":"envelope","entityId":"0193b88a-e045-70c7-ac0f-e6adc93009c3","data":{"status":"completed"},"envelope":{"id":"0193b88a-e045-70c7-ac0f-e6adc93009c3","status":"completed"}}';
// $body = '{"id":"0193b7bc-3bee-7393-b19d-02c49b5781a9","event":"envelopeSent","name":"envelope.sent","time":"2024-12-11T22:59:46+01:00","entityName":"envelope","entityId":"0193b88a-e045-70c7-ac0f-e6adc93009c3","data":{"status":"sent"},"envelope":{"id":"0193b7bc-1797-70f6-a095-231c750d3487","status":"sent"}}';
// $body = '{"id":"0193bc3c-08d2-73ef-b4e4-a4660319bf08","event":"envelopeDeclined","name":"envelope.declined","time":"2024-12-12T19:57:51+01:00","entityName":"envelope","entityId":"0193bc3b-46f0-73fa-8d73-c9f0452a08ca","data":{"status":"declined"},"envelope":{"id":"0193bc3b-46f0-73fa-8d73-c9f0452a08ca","status":"declined"}}';

if (strlen($body) < 10000) {
    error_log("digisign_webhook: payload: " . print_r($body, true));
}

$hook = json_decode($body);

if (!$hook) {
    print_and_log("Invalidni request body, asi neplatny JSON");
    http_response_code(400);
    return;
}

print_and_log(sprintf("%s %s %s", $hook->event, $hook->entityName, $hook->entityId));

process_digisign_webhook($hook);

http_response_code(200);
