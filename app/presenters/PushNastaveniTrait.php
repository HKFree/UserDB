<?php

namespace App\Presenters;

use App\Services\Push\PushException;
use App\Services\Push\PushOdbery;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;
use Nette\Http\IResponse;

/** Nastavení notifikací pro správce (userdb) i členy (moje); presenter definuje PUBLIKUM. */
trait PushNastaveniTrait
{
    private PushOdbery $pushOdbery;

    public function injectPushOdbery(PushOdbery $pushOdbery): void {
        $this->pushOdbery = $pushOdbery;
    }

    private function uid(): int {
        return (int) $this->getUser()->getId();
    }

    public function renderDefault(): void {
        $this->template->zarizeni = $this->pushOdbery->zarizeni($this->uid(), self::PUBLIKUM);
        $this->template->vapidPublicKey = $this->settings->vapidPublicKey;
    }

    #[Requires(methods: 'POST')]
    public function handleUlozOdber(): void {
        $sub = json_decode($this->getHttpRequest()->getRawBody() ?? '', true);
        try {
            $this->pushOdbery->uloz($this->uid(), self::PUBLIKUM, is_array($sub) ? $sub : [], $this->getHttpRequest()->getHeader('User-Agent'));
        } catch (PushException $e) {
            $this->getHttpResponse()->setCode(IResponse::S400_BadRequest);
            $this->sendJson(['chyba' => $e->getMessage()]);
        }
        $this->sendJson(['ok' => true]);
    }

    #[Requires(methods: 'POST')]
    public function handleSmazOdber(int $odber): void {
        $this->pushOdbery->smaz($this->uid(), $odber);
        $this->flashMessage('Zařízení bylo odebráno.');
        $this->redirect('this');
    }

    protected function createComponentKanalyForm(): Form {
        $form = new Form();
        $kanaly = $form->addContainer('kanaly');
        foreach ($this->pushOdbery->kanaly($this->uid(), self::PUBLIKUM) as $id => $k) {
            $kanaly->addCheckbox((string) $id, $k['kanal']->nazev)
                ->setOption('description', $k['kanal']->popis)->setDefaultValue($k['zapnuto']);
        }
        $form->addSubmit('ulozit', 'Uložit')->setHtmlAttribute('class', 'btn btn-primary btn-sm');
        $form->onSuccess[] = function (Form $form, array $values): void {
            foreach ($values['kanaly'] as $id => $zapnuto) {
                $this->pushOdbery->nastav($this->uid(), self::PUBLIKUM, (int) $id, $zapnuto);
            }
            $this->flashMessage('Nastavení kanálů uloženo.');
            $this->redirect('this');
        };
        return $form;
    }
}
