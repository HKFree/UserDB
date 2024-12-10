<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Tracy\Debugger;

/**
 * Sprava oblasti presenter.
 */
class SpravaStitkuPresenter extends SpravaPresenter
{
    public $oblast;
    public $stitek;
    private $uzivatel;
    private $stitekUzivatel;

    public function __construct(Model\Oblast $ob, Model\Stitek $stitek, Model\Uzivatel $uzivatel, Model\StitekUzivatele $stitekUzivatel) {
        $this->oblast = $ob;
        $this->stitek = $stitek;
        $this->uzivatel = $uzivatel;
        $this->stitekUzivatel = $stitekUzivatel;
    }

    public function renderDefault() {
        $this->template->canViewOrEdit = true;
        $this->template->stitky = $this->stitek->getSeznamStitku();
    }

    public function renderEdit() {
        $this->template->canViewOrEdit = true;
    }

    protected function createComponentEditStitekForm() {
        $form = new Form($this, "editStitekForm");
        $id = $this->getParameter('id');

        $form->addText('text', 'Štítek pro oblasti', 50)->setRequired('Štítek pro oblasti');
        $form->addText('Oblast_id', 'ID oblasti', 3);
        $form->addText('barva_pozadi', 'Barva pozadí', 10)
            ->setHtmlType('color')
            ->setRequired('Barva pozadí');
        $form->addText('barva_popredi', 'Barva popředí', 10)
            ->setHtmlType('color')
            ->setRequired('Barva popředí');
        if (empty($id)) {
            $form->addSubmit('send', 'Vytvořit')->setAttribute('class', 'btn btn-success btn-xs btn-white');
        } else {
            $form->addSubmit('send', 'Upravit')->setAttribute('class', 'btn btn-success btn-xs btn-white');
        }
        if ($id) {
            $stitek = $this->stitek->getStitekById($id);
            if ($stitek) {
                $form->setDefaults([
                    'text' => $stitek->text,
                    'Oblast_id' => $stitek->Oblast_id,
                    'barva_pozadi' => $stitek->barva_pozadi,
                    'barva_popredi' => $stitek->barva_popredi
                ]);
            } else {
                $this->flashMessage('Štítek nebyl nalezen.', 'error');
                $this->redirect('default');
            }
        }
        $form->onSuccess[] = array($this, 'processEditStitekForm');
        return $form;
    }

    public function processEditStitekForm(Form $form, \stdClass $values): void {
        $id = $this->getParameter('id');
        $values->Oblast_id = $values->Oblast_id === '' ? null : $values->Oblast_id;

        if ($id) {
            $this->stitek->updateStitek($id, [
                'text' => $values->text,
                'Oblast_id' => $values->Oblast_id,
                'barva_pozadi' => $values->barva_pozadi,
                'barva_popredi' => $values->barva_popredi
            ]);
            $this->flashMessage('Štítek byl úspěšně aktualizován.', 'success');
        } else {
            $this->stitek->createStitek([
                'text' => $values->text,
                'Oblast_id' => $values->Oblast_id,
                'barva_pozadi' => $values->barva_pozadi,
                'barva_popredi' => $values->barva_popredi
            ]);
            $this->flashMessage('Štítek byl úspěšně vytvořen.', 'success');
        }

        $this->redirect('default');
    }

    public function actionDeleteLabel(): void {
        $this->getHttpResponse()->setContentType('application/json');

        // Kontrola HTTP metody
        if (!$this->getHttpRequest()->isMethod("DELETE")) {
            $this->error('Pouze DELETE požadavky jsou povoleny.', Nette\Http\IResponse::S405_METHOD_NOT_ALLOWED);
        }
        $data = json_decode($this->getHttpRequest()->getRawBody(), true);
        $stitek_id = $data['stitek_id'] ?? null;
        $user_id = $data['user_id'] ?? null;
        if (!$stitek_id || !$user_id) {
            echo json_encode(['success' => false, 'message' => 'Neplatné parametry.']);
            $this->terminate();
        }
        try {
            $this->stitekUzivatel->odstranStitek($stitek_id, $user_id);
            echo json_encode(['success' => true, 'stitekId' => $stitek_id, 'userId' => $user_id]);
            //$stitek = $this->stitek->getStitekById($stitekId);
            //echo json_encode(['success' => true, 'barva_popredi' => $stitek->barva_popredi, 'barva_pozadi' => $stitek->barva_pozadi, 'text' => $stitek->text]);

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Nepodarilo se ulozit stitek.', 'exception' => $e]);
        }
        $this->terminate();

    }

    public function actionSaveLabel($stitek_id, $user_id): void {
        $this->getHttpResponse()->setContentType('application/json');

        // Kontrola HTTP metody
        if (!$this->getHttpRequest()->isMethod("POST")) {
            $this->error('Pouze POST požadavky jsou povoleny.', Nette\Http\IResponse::S405_METHOD_NOT_ALLOWED);
        }

        // Ověření, že štítek existuje
        $stitek = $this->stitek->getStitekById($stitek_id);
        if (!$stitek) {
            echo json_encode(['success' => false, 'message' => 'Štítek nebyl nalezen.']);
            $this->terminate();
        }

        // Ověření, že uživatel existuje
        $user = $this->uzivatel->getUzivatel($user_id);
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Uživatel nebyl nalezen.']);
            $this->terminate();
        }

        try {
            $this->stitekUzivatel->createStitekUzivatele([
                'Stitek_id' => $stitek_id,
                'Uzivatel_id' => $user_id,
            ]);
            $stitek = $this->stitek->getStitekById($stitek_id);
            echo json_encode(['success' => true, 'barva_popredi' => $stitek->barva_popredi, 'barva_pozadi' => $stitek->barva_pozadi, 'text' => $stitek->text]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Nepodarilo se ulozit stitek.', 'exception' => $e]);
        }

        $this->terminate();
    }
}
