<?php

declare(strict_types=1);

final class UserdbDemoPersonaPanel implements Tracy\IBarPanel
{
    public function __construct(private readonly UserdbDemoPersonas $persona) {
    }

    public function getTab(): string {
        return '<span title="Demo osoba: přepnout na /demo-osoba">'
            . '<svg viewBox="0 0 16 16" width="16" height="16"><path fill="#337ab7" d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm0 1.5c-2.7 0-5.5 1.3-5.5 3.2V14h11v-1.3c0-1.9-2.8-3.2-5.5-3.2z"/></svg>'
            . '<span class="tracy-label">' . $this->label() . '</span></span>';
    }

    public function getPanel(): string {
        return '<h1>Demo osoba</h1><div class="tracy-inner"><p>' . $this->label() . '</p><p><a href="'
            . htmlspecialchars(UserdbDemoPersonas::PATH, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">Přepnout demo osobu</a></p></div>';
    }

    private function label(): string {
        return htmlspecialchars($this->persona->label(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
