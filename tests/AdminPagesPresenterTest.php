<?php

declare(strict_types=1);

namespace Test;

use Nette\Application\Responses\TextResponse;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Administration pages built on a Grido grid render for a member of the board.
 */
final class AdminPagesPresenterTest extends DatabaseTestCase
{
    private ActiveRow $actor;

    protected function setUp(): void {
        parent::setUp();
        $area = $this->fixtures->area();
        $this->actor = $this->fixtures->member($this->fixtures->ap($area->id)->id);
        $this->fixtures->role($this->actor->id, 'VV');
    }

    /** @return list<array{string, string, string}> */
    public function gridCases(): array {
        return [
            ['SpravaCc', 'prehledcc', 'grid'],
            ['SpravaPlateb', 'odchoziplatby', 'odchplatby'],
            ['SpravaPlateb', 'platbycu', 'paymentgrid'],
            ['SpravaUctu', 'ucty', 'stavyuctu'],
        ];
    }

    /** @dataProvider gridCases */
    public function testGridPage(string $presenter, string $action, string $grid): void {
        $result = $this->presenters->ui($this->actor->id, $presenter, ['action' => $action]);

        Assert::same(TextResponse::class, $result->response::class);
        Assert::same(200, $result->status);
        Assert::contains('id="frm-' . $grid . '-form"', $result->html);
        // The Czech text proves the grid language was set; the tables are empty in the test database.
        Assert::contains('Žádné výsledky.', $result->html);
    }
}

(new AdminPagesPresenterTest())->run();
