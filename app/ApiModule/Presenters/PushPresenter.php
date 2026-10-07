<?php

namespace App\ApiModule\Presenters;

use App\Services\Push\PushException;
use App\Services\Push\PushOdesilani;
use Nette\Application\Responses\JsonResponse;

/** POST /api/push/send – notifikace od systémových odesílatelů (R9). */
class PushPresenter extends ApiPresenter
{
    public function __construct(private PushOdesilani $odesilani) {
    }

    public function actionSend(): void {
        $this->forceMethod('POST');
        $p = fn (string $k) => is_scalar($v = $this->getHttpRequest()->getPost($k)) ? (string) $v : null;
        $cilId = $p('cil_id') === null ? null : (int) $p('cil_id');

        // Klíč omezený na AP smí posílat jen do tohoto AP
        if ($this->keyApID && ($p('rozsah') !== 'ap' || $cilId !== (int) $this->keyApID)) {
            $this->sendForbidden('key restricted to AP ' . $this->keyApID);
        }
        try {
            $id = $this->odesilani->vytvor([
                'kanal' => $p('kanal') ?? '',
                'rozsah' => $p('rozsah') ?? '',
                'cil_id' => $cilId,
                'titulek' => $p('titulek') ?? '',
                'text' => $p('text') ?? '',
                'url' => $p('url'),
            ], null, [], $this->keyID);
        } catch (PushException $e) {
            $this->httpResponse->setCode($e->getCode());
            $this->sendResponse(new JsonResponse(['result' => $e->getMessage(), 'resultNumeric' => -1]));
        }
        $this->sendResponse(new JsonResponse(['result' => 'OK', 'resultNumeric' => 1, 'id' => $id]));
    }
}
