<?php

namespace App\Presenters;

use App\Model;
use App\Model\Smlouva;
use DateTime;

class SpravaSmluvPresenter extends BasePresenter
{
    public string $smlouva_id;

    protected Model\Smlouva $smlouva;
    protected Model\PodpisSmlouvy $podpis;

    public function __construct(Model\Smlouva $smlouva, Model\PodpisSmlouvy $podpis) {
        parent::__construct();
        $this->smlouva = $smlouva;
        $this->podpis = $podpis;
    }

    // Zkontroluje ze jsme dostali ID a existuje smlouva s timto ID
    /** @param int|string|null $contract_id */
    private function idAndContractExists($contract_id): \Nette\Database\Table\ActiveRow {
        $smlouva = $contract_id !== null ? $this->smlouva->find($contract_id) : null;
        if ($smlouva === null) {
            $this->flashMessage('Nezname ID smlouvy.');
            $this->redirect('Homepage:default');
        }
        return $smlouva;
    }

    private function userCanChange(int $contract_id): void {
        // TODO: Dodelat tuto logiku
    }

    private function userCanView(int $contract_id): void {
        // TODO: Dodelat tuto logiku
    }

    public function renderShow(): void {
        $contract_id = $this->getParameter('id');
        $smlouva = $this->idAndContractExists($contract_id);
        $this->userCanView($contract_id);

        $this->smlouva_id = $this->getParameter('id');

        $this->template->id = $this->smlouva_id;
        $this->template->smlouva = $smlouva;

        $podpisy = $this->podpis->findBy(['Smlouva_id' => $this->smlouva_id]);
        $this->template->podpisy = $podpisy;
    }

    public function actionhandleDownload(): void {
        $contract_id = $this->getParameter('id');
        $smlouva = $this->idAndContractExists($contract_id);
        $this->userCanView($contract_id);

        if (is_file($smlouva->podepsany_dokument_path) === false) {
            $this->flashMessage("❌ chybí soubor ($smlouva->podepsany_dokument_path)", 'danger');
            $this->redirect('SpravaSmluv:show');
        }

        $this->sendResponse(
            new \Nette\Application\Responses\FileResponse(
                $smlouva->podepsany_dokument_path,
                $smlouva->podepsany_dokument_nazev,
                'application/pdf',
                false
            )
        );
    }

    public function parseDate(string $timestamp): DateTime {
        return \Nette\Utils\DateTime::from($timestamp);
    }

    public function actionCancelContract(): void {
        // TODO: Logování změn

        $contract_id = $this->getParameter('id');
        $smlouva = $this->idAndContractExists($contract_id);
        $this->userCanChange($contract_id);

        $current_contract = $smlouva;
        if (isset($current_contract->kdy_ukonceno)) {
            $this->flashMessage('Tato smlouva je již vypovězena. Byla vypovězena ' . $current_contract->kdy_ukonceno->format('d.m.Y \v h:m'), 'warning');
            $this->redirect('SpravaSmluv:show');
        }

        $updated_row = $current_contract->update([
            'kdy_ukonceno' => new DateTime()
        ]);

        if ($updated_row) {
            $this->flashMessage('Smlouva č. ' . $contract_id . ' vypovězena!');
        } else {
            $this->flashMessage('Chyba ve vypovezení smlouvy.', 'danger');
        }
        $this->redirect('SpravaSmluv:show');
    }

    public function actionUpdateNote(): void {
        // TODO: Logování změn

        $request = $this->getHttpRequest();
        $contract_id = $this->getParameter('id');

        if ($request->isMethod('POST') === false) {
            $this->flashMessage('Tento endpoint nepodporuje metodu ' . $request->getMethod(), 'warning');
            $this->redirect('SpravaSmluv:show');
        }

        $smlouva = $this->idAndContractExists($contract_id);
        $this->userCanChange($contract_id);

        $updated_row = $smlouva->update([
            'poznamka' => $request->getPost('interni-poznamka')
        ]);

        if ($updated_row) {
            $this->flashMessage('Poznámka uložena');
        } else {
            $this->flashMessage('Chyba při update poznámky.', 'danger');
        }
        $this->redirect('SpravaSmluv:show');
    }

}
