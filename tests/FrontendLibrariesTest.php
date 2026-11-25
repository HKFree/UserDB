<?php

declare(strict_types=1);

namespace Test;

use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';

/**
 * The frontend runs on its own stylesheet (www/css/screen.css, the design of shadcn/ui in plain CSS) and plain
 * browser JavaScript (www/js/main.js). Bootstrap, jQuery, jQuery UI, their plugins and Font Awesome were removed;
 * nothing may load them or call them again, because a page relying on them would break without an error on the server.
 * Icons are Lucide masks in screen.css; an icon class without one would render as an empty square.
 */
final class FrontendLibrariesTest extends TestCase
{
    private const ROOT_DIR = __DIR__ . '/..';

    /** Names of the removed libraries as they appear in file names and script or link tags. */
    private const REMOVED = ['bootstrap', 'jquery', 'grido.js', 'grido.css', 'nette.ajax', 'glyphicons-halflings', 'font-awesome', 'fontawesome'];

    /** A class of an icon: icon-NAME (Lucide), and fa-NAME and glyphicon-NAME of older templates, PHP code and Grido. */
    private const ICON_CLASS = '~(?<![\\w-])(?:icon|fa|glyphicon)-[a-z0-9]+(?:-[a-z0-9]+)*~';

    /** A call of jQuery: jQuery(...), $('...'), $(document), $(function ...), $.ajax and the like. */
    private const JQUERY_CALL = '~\bjQuery\b|(?<![\w$])\$\s*\(\s*(?:[\'"]|document|window|this|function|\))|(?<![\w$])\$\.(?:ajax|fn|each|nette|get|post)\b~';

    /** Third-party scripts that stay; none of them needs jQuery. */
    private const BUNDLED_SCRIPTS = ['d3.layout.min.js', 'd3.min.js', 'markerclusterer.js', 'netteForms.js', 'rickshaw.js', 'rickshaw.min.js'];

    public function testRemovedLibrariesAreNotInTheWebRoot(): void {
        $files = [];
        foreach (Finder::findFiles('*')->from(self::ROOT_DIR . '/www/css', self::ROOT_DIR . '/www/js', self::ROOT_DIR . '/www/fonts') as $file) {
            $files[] = strtolower($file->getFilename());
        }
        Assert::contains('screen.css', $files);
        Assert::contains('main.js', $files);
        foreach ($files as $file) {
            foreach (self::REMOVED as $library) {
                Assert::false(str_contains($file, $library), "www/ still contains $file.");
            }
        }
    }

    public function testNoTemplateLoadsARemovedLibrary(): void {
        $templates = $this->templates();
        Assert::contains('app/templates/@layout.latte', array_keys($templates));
        foreach ($templates as $path => $source) {
            preg_match_all('~<(?:script|link)\b[^>]*>~i', $source, $tags);
            foreach ($tags[0] as $tag) {
                foreach (self::REMOVED as $library) {
                    Assert::false(str_contains(strtolower($tag), $library), "$path loads $library: $tag");
                }
            }
        }
    }

    public function testNoCodeCallsJquery(): void {
        $sources = $this->templates() + $this->ownScripts() + $this->phpSources();
        Assert::contains('www/js/main.js', array_keys($sources));
        foreach ($sources as $path => $source) {
            Assert::same(0, preg_match(self::JQUERY_CALL, $source, $match), "$path calls jQuery: " . ($match[0] ?? ''));
        }
    }

    public function testEveryIconClassHasAnIcon(): void {
        $stylesheet = FileSystem::read(self::ROOT_DIR . '/www/css/screen.css');
        $sources = $this->templates() + $this->ownScripts() + $this->phpSources();
        $classes = [];
        foreach ($sources as $path => $source) {
            preg_match_all(self::ICON_CLASS, $source, $found);
            foreach ($found[0] as $class) {
                $classes[$class] = $path;
            }
        }
        Assert::contains('icon-search', array_keys($classes));
        Assert::contains('glyphicon-remove', array_keys($classes));
        foreach ($classes as $class => $path) {
            Assert::same(1, preg_match('~\\.' . preg_quote($class, '~') . '[\\s,{:]~', $stylesheet), "$path uses $class, screen.css has no icon for it.");
        }
    }

    public function testDetectsIconClasses(): void {
        preg_match_all(self::ICON_CLASS, '<i class="icon icon-chevrons-up-down"></i> <i class="fa fa-fw fa-map-o"></i> "glyphicon-ok"', $found);
        Assert::same(['icon-chevrons-up-down', 'fa-fw', 'fa-map-o', 'glyphicon-ok'], $found[0]);
        Assert::same(0, preg_match(self::ICON_CLASS, 'favicon.ico app-icon-x data-icon-name'));
    }

    public function testDetectsJqueryCalls(): void {
        foreach (["\$('a').tooltip();", '$(document).ready(f);', '$.ajax({});', 'jQuery(x)', '$(function () {});', '$.nette.init();'] as $call) {
            Assert::same(1, preg_match(self::JQUERY_CALL, $call), $call);
        }
        foreach (['{$item->id}', '`${x}`', '$this->link()', 'n:href="Ap:show id => $ap->id"'] as $other) {
            Assert::same(0, preg_match(self::JQUERY_CALL, $other), $other);
        }
    }

    /** @return array<string, string> */
    private function templates(): array {
        $sources = [];
        foreach (Finder::findFiles('*.latte')->from(self::ROOT_DIR . '/app') as $file) {
            $sources['app/' . $file->getRelativePathname()] = $file->read();
        }
        return $sources;
    }

    /** @return array<string, string> */
    private function ownScripts(): array {
        $sources = [];
        foreach (Finder::findFiles('*.js')->exclude(...self::BUNDLED_SCRIPTS)->in(self::ROOT_DIR . '/www/js') as $file) {
            $sources['www/js/' . $file->getFilename()] = $file->read();
        }
        return $sources;
    }

    /** @return array<string, string> PHP code that builds HTML with scripts (components, models, presenters) */
    private function phpSources(): array {
        $sources = [];
        foreach (Finder::findFiles('*.php')->from(self::ROOT_DIR . '/app') as $file) {
            $sources['app/' . $file->getRelativePathname()] = $file->read();
        }
        return $sources;
    }
}

(new FrontendLibrariesTest())->run();
