<?php

namespace App\Presenters;

use App\Services\Push\PushException;
use App\Services\Push\PushOdesilani;
use App\Services\Push\PushOpravneni;
use Nette\Application\UI\Form;
use Nette\Database\Explorer;
use Nette\Database\UniqueConstraintViolationException;

/** Push notifikace pro správce: nastavení odběru, odeslání, správa kanálů (VV). */
class PushPresenter extends BasePresenter
{
    use PushNastaveniTrait;

    private const PUBLIKUM = 'spravci';
    private const ROZSAHY = ['oblast' => 'Oblast', 'ap' => 'AP', 'sit' => 'Celá síť'];

    public function __construct(private Explorer $db, private PushOdesilani $odesilani) {
    }

    public function startup() {
        parent::startup();
        if (!PushOpravneni::jeSpravce($this->getUser()->getRoles())) {
            $this->error('Notifikace pro správce jsou jen pro SO, ZSO, TECH a VV.', 403);
        }
    }

    /** Oblasti, kam smí uživatel posílat; null = všechny (VV, TECH). */
    private function mojeOblasti(): ?array {
        $role = $this->getUser()->getRoles();
        if (PushOpravneni::jeGlobalni($role)) {
            return null;
        }
        $ids = [];
        foreach ($role as $r) {
            if (preg_match('~^Z?SO-(\d+)$~D', $r, $m)) {
                $ids[] = (int) $m[1];
            }
        }
        return $ids;
    }

    public function renderOdeslat(): void {
        $historie = $this->db->table('PushNotifikace')->order('id DESC')->limit(30);
        $this->template->historie = $this->getUser()->isInRole('VV') ? $historie : $historie->where('odesilatel_Uzivatel_id', $this->uid());
        $this->template->rozsahy = self::ROZSAHY;
    }

    protected function createComponentOdeslatForm(): Form {
        $oblasti = $this->db->table('Oblast')->order('jmeno');
        if (($moje = $this->mojeOblasti()) !== null) {
            $oblasti->where('id', $moje);
        }
        $apcka = [];
        foreach ($oblasti as $o) {
            $apcka[$o->jmeno] = $o->related('Ap.Oblast_id')->order('jmeno')->fetchPairs('id', 'jmeno');
        }

        $form = new Form();
        $form->addSelect('kanal', 'Kanál', $this->db->table('PushKanal')->where('aktivni', 1)->order('publikum, nazev')->fetchPairs('kod', 'nazev'))
            ->setRequired();
        $form->addSelect('rozsah', 'Rozsah', $this->mojeOblasti() === null ? self::ROZSAHY : array_diff_key(self::ROZSAHY, ['sit' => 1]))
            ->setRequired();
        $form->addSelect('oblast', 'Oblast', $oblasti->fetchPairs('id', 'jmeno'))->setPrompt('—');
        $form->addSelect('ap', 'AP', $apcka)->setPrompt('—');
        $form->addText('titulek', 'Titulek')->setRequired()->setMaxLength(80);
        $form->addTextArea('text', 'Text', 60, 4)->setRequired()->setMaxLength(250)
            ->setOption('description', 'Zobrazí se i na zamčené obrazovce – žádné osobní ani finanční údaje.');
        $form->addText('url', 'Odkaz (nepovinný)')->setHtmlType('url')->setMaxLength(255);
        $form->addSubmit('odeslat', 'Odeslat')->setHtmlAttribute('class', 'btn btn-success btn-sm');
        $form->onSuccess[] = function (Form $form, array $v): void {
            try {
                $this->odesilani->vytvor([
                    'kanal' => $v['kanal'],
                    'rozsah' => $v['rozsah'],
                    'cil_id' => ['oblast' => $v['oblast'], 'ap' => $v['ap'], 'sit' => null][$v['rozsah']],
                    'titulek' => $v['titulek'],
                    'text' => $v['text'],
                    'url' => $v['url'],
                ], $this->uid(), $this->getUser()->getRoles());
            } catch (PushException $e) {
                $form->addError($e->getMessage());
                return;
            }
            $this->flashMessage('Notifikace je ve frontě a odejde do minuty.');
            $this->redirect('this');
        };
        return $form;
    }

    public function actionKanaly(?int $kanal = null): void {
        if (!PushOpravneni::smiSpravovatKanaly($this->getUser()->getRoles())) {
            $this->error('Kanály spravuje jen VV.', 403);
        }
        if ($kanal) {
            $this['kanalForm']->setDefaults(($this->db->table('PushKanal')->get($kanal) ?: $this->error())->toArray());
        }
    }

    public function renderKanaly(): void {
        $this->template->kanaly = $this->db->table('PushKanal')->order('publikum, nazev');
    }

    protected function createComponentKanalForm(): Form {
        $form = new Form();
        $form->addHidden('id');
        $form->addText('kod', 'Kód')->setRequired()->addRule(Form::Pattern, 'Jen a-z, 0-9 a pomlčka.', '[a-z0-9-]{1,50}');
        $form->addText('nazev', 'Název')->setRequired()->setMaxLength(100);
        $form->addText('popis', 'Popis')->setMaxLength(255);
        $form->addSelect('publikum', 'Publikum', ['clenove' => 'Členové (moje.hkfree.org)', 'spravci' => 'Správci (userdb)'])->setRequired();
        $form->addCheckbox('vychozi_zapnuto', 'Ve výchozím stavu zapnuto');
        $form->addCheckbox('aktivni', 'Aktivní')->setDefaultValue(true);
        $form->addSubmit('ulozit', 'Uložit')->setHtmlAttribute('class', 'btn btn-primary btn-sm');
        $form->onSuccess[] = function (Form $form, array $v): void {
            if (!PushOpravneni::smiSpravovatKanaly($this->getUser()->getRoles())) {
                $this->error('Kanály spravuje jen VV.', 403);
            }
            $id = (int) $v['id'];
            unset($v['id']);
            try {
                $id ? $this->db->table('PushKanal')->where('id', $id)->update($v) : $this->db->table('PushKanal')->insert($v);
            } catch (UniqueConstraintViolationException $e) {
                $form['kod']->addError('Kanál s tímto kódem už existuje.');
                return;
            }
            $this->flashMessage('Kanál uložen.');
            $this->redirect('kanaly', ['kanal' => null]);
        };
        return $form;
    }
}
