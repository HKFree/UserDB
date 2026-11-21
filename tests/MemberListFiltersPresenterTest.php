<?php

declare(strict_types=1);

namespace Test;

use App\Model\StitekUzivatele;
use Nette\Application\LinkGenerator;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class MemberListFiltersPresenterTest extends DatabaseTestCase
{
    private ActiveRow $ap;
    private ActiveRow $viewer;

    protected function setUp(): void {
        parent::setUp();
        $this->ap = $this->fixtures->ap($this->fixtures->area()->id);
        $this->viewer = $this->fixtures->member($this->ap->id);
        $this->fixtures->role($this->viewer->id, 'VV');
    }

    public function testSearchButtonIsInTheFilterBarAboveEveryMemberGrid(): void {
        $pages = [
            'list' => ['frm-grid-form', 'frm-othersGrid-form'],
            'listall' => ['frm-grid-form'],
        ];
        foreach ($pages as $action => $forms) {
            $html = $this->memberList($action);
            foreach ($forms as $formId) {
                $form = $this->form($html, $formId);
                $filterBar = strstr($form, '<table', true);
                Assert::type('string', $filterBar, "$action/$formId has no table");
                Assert::match('~<button[^>]*type="submit"[^>]*name="buttons\[search\]"[^>]*>.*Vyhledat\s*</button>~s', (string) $filterBar, "$action/$formId");
                Assert::notContains('display:none', (string) $filterBar, "$action/$formId hides the button");
                Assert::same(1, substr_count($form, 'name="buttons[search]"'), "$action/$formId repeats the button under the table");
            }
        }
    }

    public function testLabelFilterKeepsMembersWithOrWithoutTheLabel(): void {
        $labelled = $this->fixtures->member($this->ap->id);
        $other = $this->fixtures->member($this->ap->id);
        $label = $this->fixtures->insert('Stitek', ['text' => $this->fixtures->name('Label'), 'barva_pozadi' => '#006600', 'barva_popredi' => '#ffffff']);
        $unrelated = $this->fixtures->insert('Stitek', ['text' => $this->fixtures->name('Label'), 'barva_pozadi' => '#eeeeee', 'barva_popredi' => '#000000']);
        $assignments = $this->container->getByType(StitekUzivatele::class);
        foreach ([[$label, $labelled], [$unrelated, $labelled], [$unrelated, $other]] as [$assigned, $member]) {
            $assignments->createStitekUzivatele(['Stitek_id' => $assigned->id, 'Uzivatel_id' => $member->id]);
        }

        foreach (['list', 'listall'] as $action) {
            Assert::same([$labelled->id], $this->listedMembers($action, (int) $label->id, [$labelled->id, $other->id]), "$action with the label");
            Assert::same([$other->id], $this->listedMembers($action, -(int) $label->id, [$labelled->id, $other->id]), "$action without the label");
            Assert::same([$labelled->id, $other->id], $this->listedMembers($action, 0, [$labelled->id, $other->id]), "$action unfiltered");
        }
    }

    public function testSearchButtonSubmitsTheLabelFilter(): void {
        $label = $this->fixtures->insert('Stitek', ['text' => $this->fixtures->name('Label'), 'barva_pozadi' => '#006600', 'barva_popredi' => '#ffffff']);
        $submitted = [
            'grid' => ['spolek_druzstvo' => 'spolek', 'stitek' => (string) $label->id, 'ulice_cp' => '', 'poznamka' => ''],
            'othersGrid' => ['TypClenstvi_id' => 'planned'],
        ];
        foreach ($submitted as $grid => $filters) {
            // The fields a browser sends with the button; without the page size the form is not valid.
            $result = $this->presenters->ui($this->viewer->id, 'UzivatelList', [
                'action' => 'list',
                'id' => $this->ap->id,
                'do' => $grid . '-form-submit',
                'filters' => $filters,
                'count' => '500',
                'buttons' => ['search' => 'Vyhledat'],
            ]);
            parse_str((string) parse_url((string) $result->redirectUrl, PHP_URL_QUERY), $query);
            Assert::same(array_filter($filters, static fn (string $value): bool => $value !== ''), $query[$grid . '-filter'] ?? null, $grid);
        }
    }

    public function testLabelRequestsGoToAddressesFromTheRouter(): void {
        $links = $this->container->getByType(LinkGenerator::class);
        $html = $this->memberList('list');
        foreach (['save' => 'saveLabel', 'delete' => 'deleteLabel'] as $attribute => $action) {
            $url = $links->link('SpravaStitku:' . $action) ?? throw new \LogicException("No route leads to $action.");
            Assert::same(1, preg_match('~ data-stitky-' . $attribute . '-url="([^"]*)"~', $html, $match), $action);
            $path = $match[1] ?? '';
            Assert::match('~^(/[a-z]+)?/stitky/' . $action . '$~', $path);
            Assert::true(str_ends_with($url, $path), "$url does not end with $path");
        }
        // The application runs under /userdb only on production; a fixed prefix breaks labels everywhere else.
        $script = (string) file_get_contents(__DIR__ . '/../www/js/main.js');
        Assert::notContains('/userdb/stitky/', $script);
        Assert::contains("url: $('body').data('stitky-save-url')", $script);
        Assert::contains("url: $('body').data('stitky-delete-url')", $script);
    }

    /** @param array<string, mixed> $parameters */
    private function memberList(string $action, array $parameters = []): string {
        $parameters += ['action' => $action] + ($action === 'list' ? ['id' => $this->ap->id] : []);
        $result = $this->presenters->ui($this->viewer->id, 'UzivatelList', $parameters);
        Assert::same(200, $result->status);
        return $result->html;
    }

    private function form(string $html, string $id): string {
        if (preg_match('~<form[^>]*id="' . preg_quote($id, '~') . '".*?</form>~s', $html, $match) !== 1) {
            throw new \LogicException("Form $id is missing.");
        }
        return $match[0];
    }

    /**
     * @param list<int> $candidates
     * @return list<int>
     */
    private function listedMembers(string $action, int $labelFilter, array $candidates): array {
        $grid = $this->form($this->memberList($action, ['grid-filter' => ['stitek' => $labelFilter]]), 'frm-grid-form');
        return array_values(array_filter(
            $candidates,
            static fn (int $id): bool => str_contains($grid, 'data-user-id="' . $id . '"'),
        ));
    }
}

(new MemberListFiltersPresenterTest())->run();
