<?php

declare(strict_types=1);

namespace Test;

use App\Router\RouterFactory;
use Grido\Components\Export;
use Nette\Http;
use Tester\Assert;

/**
 * The member lists the way one member browses them: every request follows a link or a form of the page
 * rendered before, so a parameter the links and forms drop is lost here as well.
 */
final class MemberListPages
{
    public function __construct(private PresenterRunner $presenters, private int $viewerId) {
    }

    /**
     * @param array<string, mixed> $parameters the action and its parameters
     * @param array<string, mixed>|null $post the fields of a form sent by POST
     */
    public function page(array $parameters, ?array $post = null): string {
        $result = $post === null
            ? $this->presenters->ui($this->viewerId, 'UzivatelList', $parameters)
            : $this->presenters->ui($this->viewerId, 'UzivatelList', $parameters, 'POST', $post);
        if ($result->redirectUrl !== null) {
            return $this->follow($result->redirectUrl);
        }
        Assert::same(200, $result->status);
        Assert::contains('</html>', $result->html);
        return $result->html;
    }

    /** The page a browser ends on after requesting the address from the page, redirects included. */
    public function follow(string $url): string {
        return $this->page($this->memberListRoute($url));
    }

    /**
     * Submits the form of the table the way a browser does: to its address, with the values of all its fields.
     * @param array<string, mixed> $fields the fields the user changed
     */
    public function submit(string $html, string $grid, array $fields, string $button = 'search'): string {
        $form = self::form($grid);
        $query = http_build_query(array_replace_recursive($this->fields($html, $form), $fields, ['buttons' => [$button => '1']]));
        return $this->follow(explode('?', $this->link($html, $form . '/@action'))[0] . '?' . $query);
    }

    /** Sends the search field of the page header by Enter, as the field has no button. */
    public function search(string $html, string $text): string {
        $form = '//form[@id="frm-searchForm"]';
        return $this->page($this->memberListRoute($this->link($html, $form . '/@action')), ['search' => $text] + $this->fields($html, $form));
    }

    /** @return list<array<string, string>> the rows of the export the link leads to, keyed by the column headers */
    public function export(string $link): array {
        $export = $this->presenters->ui($this->viewerId, 'UzivatelList', $this->memberListRoute($link))->response;
        Assert::type(Export::class, $export);
        ob_start();
        try {
            $export->send(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response());
            $csv = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        // A value may hold a line break, so the rows are read as CSV, not line by line.
        $stream = fopen('php://memory', 'r+') ?: throw new \LogicException('No memory stream.');
        fwrite($stream, (string) preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            if ($row !== [null]) {
                $rows[] = array_map(strval(...), $row);
            }
        }
        fclose($stream);
        $header = array_shift($rows) ?? [];
        return array_map(static fn (array $row): array => array_combine($header, $row), $rows);
    }

    public function exportLink(string $html, string $grid): string {
        return $this->link($html, sprintf('%s//span[%s]/a/@href', self::form($grid), self::hasClass('export')));
    }

    public function sortLink(string $html, string $grid, string $column): string {
        return $this->link($html, sprintf('%s//th[%s]//a/@href', self::form($grid), self::hasClass("grid-header-$column")));
    }

    public function link(string $html, string $query): string {
        $links = $this->texts($html, $query);
        Assert::count(1, $links, $query);
        return $links[0];
    }

    /**
     * @param list<int> $candidates
     * @return list<int> the candidates in the order of their rows in the table
     */
    public function listed(string $html, string $grid, array $candidates): array {
        $listed = [];
        foreach ($this->texts($html, self::form($grid) . '//tbody/tr/@class') as $classes) {
            foreach ($candidates as $id) {
                if (in_array("grid-row-$id", explode(' ', $classes), true)) {
                    $listed[] = $id;
                }
            }
        }
        return $listed;
    }

    /** @return list<string> */
    public function texts(string $html, string $query): array {
        return array_map(
            static fn (\DOMNode $node): string => trim((string) preg_replace('/\s+/u', ' ', $node->textContent)),
            $this->nodes($html, $query),
        );
    }

    /** @return list<\DOMNode> */
    public function nodes(string $html, string $query): array {
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $nodes = (new \DOMXPath($document))->query($query);
        if ($nodes === false) {
            throw new \LogicException('Invalid test XPath: ' . $query);
        }
        return array_values(array_filter(iterator_to_array($nodes), static fn (object $node): bool => $node instanceof \DOMNode));
    }

    public static function form(string $grid): string {
        return sprintf('//form[@id="frm-%s-form"]', $grid);
    }

    public static function hasClass(string $class): string {
        return sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $class);
    }

    /** @return array<string, mixed> the action and the parameters the address of a member list leads to */
    private function memberListRoute(string $url): array {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);
        $parameters = (new RouterFactory())->createRouter('/userdb')->match(
            new Http\Request(new Http\UrlScript('http://localhost' . $path . ($query !== '' ? '?' . $query : ''))),
        );
        if ($parameters === null) {
            throw new \LogicException("No route leads to $url.");
        }
        Assert::same('UzivatelList', $parameters['presenter']);
        unset($parameters['presenter']);
        return $parameters;
    }

    /** @return array<array-key, mixed> the names and values of the fields a browser sends with the form */
    private function fields(string $html, string $form): array {
        $pairs = [];
        // Fields outside the form belong to it by their form attribute, as the filter row above the member list does.
        $id = (string) preg_replace('~^//form\[@id="([^"]+)"\]$~', '$1', $form);
        $outside = sprintf(' | //input[@form="%1$s"][not(@type="submit")] | //select[@form="%1$s"]', $id);
        foreach ($this->nodes($html, $form . '//input[not(@type="submit")] | ' . $form . '//select' . $outside) as $field) {
            if (($field instanceof \DOMElement) === false) {
                throw new \LogicException('A form field is not an element.');
            }
            // Without a selected option a browser sends the first one.
            $options = $this->texts($html, $field->getNodePath() . '/option[@selected]/@value | ' . $field->getNodePath() . '/option[1]/@value');
            $value = $field->tagName === 'select' ? (string) end($options) : $field->getAttribute('value');
            $pairs[] = urlencode($field->getAttribute('name')) . '=' . urlencode($value);
        }
        parse_str(implode('&', $pairs), $fields);
        return $fields;
    }
}
