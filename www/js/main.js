/*
 * UserDB: chování společné všem stránkám. Bez knihoven, jen standardní API prohlížeče.
 * Načítá se s atributem defer: běží po načtení HTML, ještě před událostí DOMContentLoaded.
 */

/** Text bez diakritiky a velikosti písmen, pro hledání v názvech. */
function fold(text) {
    return text.trim().toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
}

/** Cesta adresy bez lomítka na konci, pro porovnání odkazů. */
function pathOf(href) {
    return new URL(href, window.location.href).pathname.replace(/\/$/, '');
}

/** Prvek je vidět (má rozměr a nestojí ve skrytém předkovi). */
function isShown(element) {
    return element.getClientRects().length > 0;
}

/* ------------------------------------------------------------ postranní panel */

const narrowLayout = window.matchMedia('(max-width: 989px)');

function sidebarIsVisible() {
    const state = document.documentElement.dataset.sidebar;
    return narrowLayout.matches ? state === 'open' : state !== 'hidden';
}

function setSidebar(visible, { focusFilter = false } = {}) {
    const root = document.documentElement;
    if (narrowLayout.matches) {
        if (visible) {
            root.dataset.sidebar = 'open';
        } else {
            delete root.dataset.sidebar;
        }
    } else {
        if (visible) {
            delete root.dataset.sidebar;
        } else {
            root.dataset.sidebar = 'hidden';
        }
        localStorage.setItem('userdb-sidebar', visible ? 'shown' : 'hidden');
    }
    document.querySelectorAll('.sidebar-toggle').forEach(button => button.setAttribute('aria-expanded', String(visible)));
    syncDrawer(focusFilter);
}

// Vysouvací panel na telefonu stojí ve stránce před obsahem. Dokud je otevřený, zbytek stránky je inert:
// klávesnice zůstane v panelu a obsah pod překryvem nejde ovládat. Závoj vedle panelu inert není, ťuknutím
// na něj se panel zavře. Po zavření se fokus vrátí na tlačítko.
const drawerInert = new Set();

function syncDrawer(focusFilter = false) {
    const sidebar = document.querySelector('.app-sidebar');
    if (sidebar === null) {
        return;
    }
    const open = narrowLayout.matches && sidebarIsVisible();
    if (open && drawerInert.size === 0) {
        for (const element of document.body.children) {
            if (element !== sidebar && element.matches('.sidebar-backdrop') === false && element.inert === false) {
                element.inert = true;
                drawerInert.add(element);
            }
        }
        // Kurzor do filtru jen po otevření klávesnicí nebo myší: po ťuknutí prstem by vysunutá klávesnice
        // zakryla seznam oblastí.
        const filter = sidebar.querySelector('[data-sidebar-filter]');
        if (focusFilter && filter !== null) {
            filter.focus({ preventScroll: true });
        } else {
            sidebar.tabIndex = -1;
            sidebar.focus({ preventScroll: true });
        }
    } else if (open === false && drawerInert.size > 0) {
        drawerInert.forEach(element => { element.inert = false; });
        drawerInert.clear();
        if (sidebar.contains(document.activeElement) || document.activeElement === document.body) {
            document.querySelector('.sidebar-toggle')?.focus({ preventScroll: true });
        }
    }
}

function initSidebar() {
    const sidebar = document.querySelector('.app-sidebar');
    if (sidebar === null) {
        return;
    }

    // Ve vysouvacím panelu na telefonu nezůstává skrytí z počítače; po změně šířky okna se stav srovná.
    const syncToLayout = () => {
        const root = document.documentElement;
        if (narrowLayout.matches && root.dataset.sidebar === 'hidden') {
            delete root.dataset.sidebar;
        }
        if (narrowLayout.matches === false) {
            // Skrytí uložené na počítači; skript v hlavičce @layout.latte ho nastavuje už před vykreslením.
            if (localStorage.getItem('userdb-sidebar') === 'hidden') {
                root.dataset.sidebar = 'hidden';
            } else if (root.dataset.sidebar === 'open') {
                delete root.dataset.sidebar;
            }
        }
        document.querySelectorAll('.sidebar-toggle').forEach(button => button.setAttribute('aria-expanded', String(sidebarIsVisible())));
        syncDrawer();
    };
    syncToLayout();
    narrowLayout.addEventListener('change', syncToLayout);

    document.querySelectorAll('.sidebar-toggle').forEach(button => {
        button.addEventListener('click', event => {
            // event.detail je 0, když tlačítko stiskla klávesnice
            const focusFilter = event.detail === 0 || window.matchMedia('(pointer: coarse)').matches === false;
            setSidebar(sidebarIsVisible() === false, { focusFilter });
        });
    });
    document.querySelector('.sidebar-backdrop')?.addEventListener('click', () => setSidebar(false));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && narrowLayout.matches && sidebarIsVisible() && document.querySelector('.menu:popover-open') === null) {
            setSidebar(false);
            document.querySelector('.sidebar-toggle')?.focus();
        }
        // Ctrl+B (na Macu Cmd+B) skryje a zobrazí postranní panel, stejně jako v aplikacích na shadcn/ui.
        // V textovém poli patří zkratka poli (na Macu Ctrl+B posune kurzor o znak zpět).
        if (event.key.toLowerCase() === 'b' && (event.ctrlKey || event.metaKey) && event.altKey === false && event.shiftKey === false
            && event.target.closest?.('input, textarea, select, [contenteditable="true"]') === null) {
            event.preventDefault();
            setSidebar(sidebarIsVisible() === false);
        }
    });

    markCurrentArea(sidebar);
    initAreaFilter(sidebar);
}

// Odkaz na právě otevřenou stránku se zvýrazní a jeho oblast se rozbalí. Detail člena, úprava člena a detail AP
// patří k AP, na jehož seznam členů odkazují v drobečkové navigaci nebo v akcích stránky; zvýrazní se to AP.
function markCurrentArea(sidebar) {
    const here = pathOf(window.location.href);
    const related = [...document.querySelectorAll('.page-crumbs a[href], .page-actions a[href]')]
        .map(link => pathOf(link.href))
        .filter(path => /\/uzivatel-list\/list\/\d+$/.test(path));
    const marked = [];
    for (const link of sidebar.querySelectorAll('.nav-sidebar a[href]')) {
        const path = pathOf(link.href);
        if (path !== here && related.includes(path) === false) {
            continue;
        }
        if (path === here) {
            link.setAttribute('aria-current', 'page');
        }
        link.closest('li').classList.add('active');
        for (let details = link.closest('details'); details !== null; details = details.parentElement.closest('details')) {
            details.open = true;
        }
        marked.push(link);
    }
    // Rozbalená větev stromu všech oblastí (nebo jinak první zvýrazněný odkaz) se ukáže uprostřed seznamu,
    // když není vidět; posouvá se jen seznam, ne stránka.
    const content = sidebar.querySelector('.sidebar-content');
    const target = marked.find(link => link.closest('#oblastitree') !== null) ?? marked[0];
    if (target !== undefined && content !== null) {
        const frame = content.getBoundingClientRect();
        const branch = (target.closest('details') ?? target).getBoundingClientRect();
        if (branch.top < frame.top || branch.bottom > frame.bottom) {
            content.scrollTop += branch.top - frame.top - Math.max(0, (frame.height - branch.height) / 2);
        }
    }
}

// Filtr nad seznamem oblastí: ukáže jen oblasti a AP, jejichž název obsahuje hledaný text (bez ohledu na diakritiku).
// AP se najde i pod zápisem „Oblast - AP“.
function initAreaFilter(sidebar) {
    const input = sidebar.querySelector('[data-sidebar-filter]');
    if (input === null) {
        return;
    }
    const empty = sidebar.querySelector('.sidebar-empty');
    const items = [...sidebar.querySelectorAll('.nav-sidebar > li')];
    const openBefore = new Map();

    input.addEventListener('input', () => {
        const query = fold(input.value);
        let anyVisible = false;
        for (const item of items) {
            const details = item.querySelector(':scope > details');
            if (details !== null && openBefore.has(details) === false) {
                openBefore.set(details, details.open);
            }
            if (query === '') {
                item.hidden = false;
                item.querySelectorAll('li').forEach(child => { child.hidden = false; });
                if (details !== null) {
                    details.open = openBefore.get(details);
                }
                anyVisible = true;
                continue;
            }
            const areaText = fold(item.querySelector('a').textContent);
            const ownMatch = areaText.includes(query);
            let childMatch = false;
            item.querySelectorAll(':scope ul li').forEach(child => {
                const apText = fold(child.textContent);
                const match = ownMatch || apText.includes(query) || `${areaText} - ${apText}`.includes(query);
                child.hidden = match === false;
                childMatch ||= match;
            });
            item.hidden = (ownMatch || childMatch) === false;
            // Při filtrování je odpovídající oblast rozbalená, i když odpovídá jen její název.
            if (details !== null) {
                details.open = childMatch;
            }
            anyVisible ||= item.hidden === false;
        }
        sidebar.classList.toggle('sidebar-filtering', query !== '');
        if (empty !== null) {
            empty.hidden = anyVisible;
        }
    });
}

/* ------------------------------------------------------- nabídky a nápovědy */

// Nabídka (atribut popover) se otevře pod tlačítkem, které ji ovládá, a nevyjede z okna.
// Zarovná se k levému okraji tlačítka, v pravé polovině okna k pravému; nevejde-li se dolů, otevře se nahoru.
// Nikdy nezajede pod horní lištu obsahu.
function placeBelow(floating, anchor, gap = 6) {
    const box = anchor.getBoundingClientRect();
    const viewportWidth = document.documentElement.clientWidth;
    const viewportHeight = document.documentElement.clientHeight;
    const topbar = document.querySelector('.app-topbar');
    const minTop = topbar !== null && topbar.contains(anchor) === false ? Math.max(8, topbar.getBoundingClientRect().bottom + 4) : 8;
    const width = floating.offsetWidth;
    const height = floating.offsetHeight;
    const preferred = box.left + box.width / 2 < viewportWidth / 2 ? box.left : box.right - width;
    const left = Math.max(8, Math.min(preferred, viewportWidth - width - 8));
    const below = box.bottom + gap;
    const fitsBelow = below + height <= viewportHeight - 8;
    const above = box.top - gap - height;
    // Vysoká nabídka, která se nevejde ani nahoru, zůstane v okně (výšku jí omezuje max-height ve screen.css).
    const top = fitsBelow || above < minTop ? Math.max(minTop, Math.min(below, viewportHeight - height - 8)) : above;
    floating.style.left = `${Math.round(left)}px`;
    floating.style.top = `${Math.round(top)}px`;
}

function menuInvoker(menu) {
    return document.querySelector(`[popovertarget="${CSS.escape(menu.id)}"]`);
}

// Tlačítko je vidět, když jeho střed leží v okně, pod horní lištou a ve viditelné části každého rámu, který
// obsah ořezává (posuvný seznam, tabulka). Střed, ne celý obdélník: po posunu k tlačítku může o zlomek pixelu
// přesahovat okraj rámu.
function invokerVisible(invoker) {
    if (isShown(invoker) === false) {
        return false;
    }
    const box = invoker.getBoundingClientRect();
    const x = box.left + box.width / 2;
    const y = box.top + box.height / 2;
    const topbar = document.querySelector('.app-topbar');
    let top = topbar !== null && topbar.contains(invoker) === false ? topbar.getBoundingClientRect().bottom : 0;
    let left = 0;
    let right = window.innerWidth;
    let bottom = window.innerHeight;
    for (let frame = invoker.parentElement; frame !== null && frame !== document.body; frame = frame.parentElement) {
        const style = getComputedStyle(frame);
        if (style.overflowX === 'visible' && style.overflowY === 'visible') {
            continue;
        }
        const edges = frame.getBoundingClientRect();
        top = Math.max(top, edges.top);
        left = Math.max(left, edges.left);
        right = Math.min(right, edges.right);
        bottom = Math.min(bottom, edges.bottom);
    }
    return x >= left && x <= right && y >= top && y <= bottom;
}

function initMenus() {
    document.addEventListener('toggle', event => {
        const menu = event.target;
        if ((menu instanceof HTMLElement) === false || menu.matches('.menu') === false) {
            return;
        }
        const invoker = event.source ?? menuInvoker(menu);
        if (invoker !== null) {
            invoker.setAttribute('aria-expanded', String(event.newState === 'open'));
        }
        if (event.newState === 'open' && invoker !== null) {
            placeBelow(menu, invoker);
            menu.dataset.placed = '';
            menuItems(menu)[0]?.focus({ preventScroll: true });
        } else {
            delete menu.dataset.placed;
        }
    }, true);

    // Šipky nahoru a dolů procházejí položky otevřené nabídky. Volby vzhledu jsou skupina přepínačů
    // a šipky mění vybranou volbu (initTheme); do nabídky patří jen ta vybraná.
    document.addEventListener('keydown', event => {
        const menu = event.target.closest?.('.menu:popover-open');
        if (menu === null || menu === undefined || (event.key !== 'ArrowDown' && event.key !== 'ArrowUp')
            || event.target.closest('.theme-option') !== null) {
            return;
        }
        event.preventDefault();
        const items = menuItems(menu);
        const index = items.indexOf(document.activeElement);
        const next = event.key === 'ArrowDown' ? index + 1 : index - 1;
        items[(next + items.length) % items.length]?.focus();
    });

    // Posun stránky nebo rámu tabulky nabídku přesune s tlačítkem, i posun, kterým prohlížeč ukáže právě
    // zaměřené tlačítko. Zavře ji, až tlačítko zmizí z rámu nebo zajede pod lištu. Posun uvnitř nabídky ji nechá.
    document.addEventListener('scroll', event => {
        if (event.target instanceof Element && event.target.closest('.menu') !== null) {
            return;
        }
        document.querySelectorAll('.menu:popover-open').forEach(menu => {
            const invoker = menuInvoker(menu);
            if (invoker === null) {
                return;
            }
            if (invokerVisible(invoker)) {
                placeBelow(menu, invoker);
            } else {
                menu.hidePopover();
            }
        });
    }, { capture: true, passive: true });

    window.addEventListener('resize', () => {
        document.querySelectorAll('.menu:popover-open').forEach(menu => menu.hidePopover());
    });
}

function menuItems(menu) {
    return [...menu.querySelectorAll('a[href], button:not(.theme-option), .theme-option[tabindex="0"]')]
        .filter(item => item.getAttribute('aria-disabled') !== 'true' || item.matches('.stitky-item'));
}

// Nápověda z atributu title: místo systémové bubliny vlastní, která se ukáže i při ovládání klávesnicí.
// Kreslí se do <body>, takže ji posuvný rám tabulky neořízne.
const tip = {
    element: null,
    owner: null,

    show(owner, text) {
        if (this.element === null) {
            this.element = document.createElement('div');
            this.element.className = 'tip';
            this.element.setAttribute('popover', 'manual');
            this.element.setAttribute('role', 'tooltip');
            this.element.id = 'userdb-tip';
            document.body.append(this.element);
        }
        if (this.owner !== null && this.owner !== owner) {
            this.owner.removeAttribute('aria-describedby');
        }
        this.owner = owner;
        // Text nápovědy zůstane popisem prvku i pro čtečky obrazovky.
        owner.setAttribute('aria-describedby', this.element.id);
        this.element.textContent = text;
        if (this.element.matches(':popover-open') === false) {
            this.element.showPopover();
        }
        const box = owner.getBoundingClientRect();
        const width = this.element.offsetWidth;
        const height = this.element.offsetHeight;
        const left = Math.min(Math.max(8, box.left + box.width / 2 - width / 2), window.innerWidth - width - 8);
        const top = box.top - height - 6 >= 8 ? box.top - height - 6 : box.bottom + 6;
        this.element.style.left = `${Math.round(left)}px`;
        this.element.style.top = `${Math.round(top)}px`;
    },

    hide(owner) {
        // Nápovědu jiného prvku nechá být (např. doběhne časovač kopírování, zatímco myš je nad jiným tlačítkem).
        if (owner !== undefined && owner !== this.owner) {
            return;
        }
        if (this.element !== null && this.element.matches(':popover-open')) {
            this.element.hidePopover();
        }
        this.owner?.removeAttribute('aria-describedby');
        this.owner = null;
    },
};

// Text nápovědy prvku; atribut title se přesune do data-tip, aby se nezobrazovala i systémová bublina.
function tipText(element) {
    if (element.hasAttribute('title')) {
        // Ikona bez textu by po odebrání title ztratila přístupný název; ten zůstane v aria-label.
        if (element.textContent.trim() === '' && element.hasAttribute('aria-label') === false) {
            element.setAttribute('aria-label', element.getAttribute('title'));
        }
        element.dataset.tip = element.getAttribute('title');
        element.removeAttribute('title');
    }
    return element.dataset.tip ?? '';
}

function initTips() {
    const selector = '[data-toggle="tooltip"]';
    const showFor = event => {
        const owner = event.target.closest?.(selector);
        if (owner === null || owner === undefined || owner.dataset.copyResult !== undefined) {
            return;
        }
        const text = tipText(owner);
        if (text !== '') {
            tip.show(owner, text);
        }
    };
    const hideFor = event => {
        const owner = event.target.closest?.(selector);
        if (owner !== null && owner !== undefined && owner.dataset.copyResult === undefined && owner.contains(event.relatedTarget) === false) {
            tip.hide(owner);
        }
    };
    document.addEventListener('pointerover', showFor);
    document.addEventListener('pointerout', hideFor);
    document.addEventListener('focusin', showFor);
    document.addEventListener('focusout', hideFor);
    document.addEventListener('scroll', () => tip.hide(), { passive: true, capture: true });
}

/* ------------------------------------------------------------------- vzhled */

// Vzhled (světlý, tmavý, podle systému): volba v cookie userdb_theme, přepnutí hned bez načtení stránky.
// Server ji čte v BasePresenter (App\Services\Vzhled), vzhled podle systému rozhoduje skript v hlavičce @layout.latte.
function initTheme() {
    const root = document.documentElement;
    const darkScheme = window.matchMedia('(prefers-color-scheme: dark)');
    const meta = document.querySelector('meta[name="color-scheme"]');
    const apply = () => {
        const choice = root.dataset.themeChoice ?? 'auto';
        const dark = choice === 'dark' || (choice === 'auto' && darkScheme.matches);
        root.dataset.theme = dark ? 'dark' : 'light';
        meta?.setAttribute('content', choice === 'auto' ? 'light dark' : root.dataset.theme);
    };
    darkScheme.addEventListener('change', apply);

    const choose = option => {
        const choice = option.dataset.themeChoice;
        const path = `${document.body.dataset.basePath ?? ''}/`;
        document.cookie = `userdb_theme=${encodeURIComponent(choice)}; path=${path}; max-age=31536000; SameSite=Lax`;
        root.dataset.themeChoice = choice;
        apply();
        // Skupina přepínačů: v pořadí Tab je jen vybraná volba.
        document.querySelectorAll('.theme-option').forEach(other => {
            other.setAttribute('aria-checked', String(other === option));
            other.tabIndex = other === option ? 0 : -1;
        });
    };
    document.addEventListener('click', event => {
        const option = event.target.closest('.theme-option');
        if (option !== null) {
            choose(option);
            // Volba myší nabídku zavře jako výběr položky; šipkami se dá vybírat dál.
            option.closest('[popover]')?.hidePopover();
        }
    });
    document.addEventListener('keydown', event => {
        const option = event.target.closest?.('.theme-option');
        const step = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[event.key];
        if (option === null || option === undefined || step === undefined) {
            return;
        }
        event.preventDefault();
        const options = [...option.closest('[role="radiogroup"]').querySelectorAll('.theme-option')];
        const next = options[(options.indexOf(option) + step + options.length) % options.length];
        choose(next);
        next.focus();
    });
}

/* ------------------------------------------------------------------- hledání */

// Napovídání v hledání nabízí jen odkazy, které přihlášený uživatel už vidí v levém panelu a v nástrojích.
// Enter bez vybraného návrhu hledá člena na serveru; klávesa / do hledání skočí odkudkoli.
function initSearch() {
    const form = document.querySelector('.app-search');
    const input = form?.querySelector('input[name="search"]');
    const panel = form?.querySelector('.search-suggestions');
    const options = document.getElementById('search-options');
    if (input === null || input === undefined) {
        return;
    }

    document.addEventListener('keydown', event => {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }
        if (event.target.closest?.('input, textarea, select, [contenteditable="true"]')) {
            return;
        }
        event.preventDefault();
        input.focus();
        input.select();
    });

    if (panel === null || options === null) {
        return;
    }
    const hint = panel.querySelector('.search-hint');
    const help = panel.querySelector('.search-help');
    const emptyHint = hint.textContent;
    const submitHint = 'Enter vyhledá účastníka i podle čísla smlouvy nebo IP adresy.';
    const index = [];
    const seen = new Map();
    let active = -1;

    // area: jméno oblasti, pod kterou AP v levém panelu stojí; AP se najde i pod zápisem „Oblast - AP“.
    const add = (link, description, area = '') => {
        const href = link.getAttribute('href');
        if (href === null) {
            return;
        }
        const text = link.textContent.trim();
        const foldedWithArea = area === '' ? '' : fold(`${area} - ${text}`);
        const known = seen.get(href);
        if (known !== undefined) {
            // Jediné aktivní AP oblasti stojí v Mých oblastech samo, ve Všech oblastech pod svou oblastí.
            if (foldedWithArea !== '' && known.foldedWithArea === '') {
                known.foldedWithArea = foldedWithArea;
                known.description = description;
            }
            return;
        }
        const item = { href, text, description, folded: fold(text), foldedWithArea };
        seen.set(href, item);
        index.push(item);
    };
    document.querySelectorAll('.app-sidebar .nav-sidebar > li').forEach(item => {
        const link = item.querySelector(':scope > a, :scope > details > summary > a');
        if (link === null) {
            return;
        }
        // :scope: bez něj by 'ul a' našlo i odkaz položky samé, leží v seznamu panelu.
        const children = item.querySelectorAll(':scope ul a');
        add(link, children.length > 0 ? 'oblast' : 'AP');
        const area = link.textContent.trim();
        children.forEach(child => add(child, `AP · ${area}`, area));
    });
    document.querySelectorAll('#menu-nastroje a').forEach(link => add(link, 'nástroj'));
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', 'search-options');
    input.setAttribute('aria-expanded', 'false');

    const rows = () => [...options.children];
    const close = () => {
        panel.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };
    const render = () => {
        const needle = fold(input.value);
        options.replaceChildren();
        active = -1;
        input.removeAttribute('aria-activedescendant');
        if (needle !== '') {
            const starts = [];
            const contains = [];
            index.forEach(item => {
                const at = item.folded.indexOf(needle);
                if (at === 0) {
                    starts.push(item);
                } else if (at > 0 || item.foldedWithArea.includes(needle)) {
                    contains.push(item);
                }
            });
            starts.concat(contains).slice(0, 9).forEach((item, i) => {
                const option = document.createElement('a');
                option.className = 'search-option';
                option.href = item.href;
                option.id = `search-option-${i}`;
                option.tabIndex = -1;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                const text = document.createElement('span');
                text.className = 'search-option-text';
                text.textContent = item.text;
                const description = document.createElement('span');
                description.className = 'search-option-description';
                description.textContent = item.description;
                option.append(text, description);
                options.append(option);
            });
        }
        panel.hidden = false;
        const hasOptions = options.children.length > 0;
        input.setAttribute('aria-expanded', String(hasOptions));
        if (help !== null) {
            help.hidden = hasOptions === false;
        }
        let message = needle === '' ? emptyHint : submitHint;
        if (needle !== '' && hasOptions === false) {
            message = `Oblast, AP ani nástroj nenalezen. ${submitHint}`;
        }
        hint.textContent = message;
    };

    input.addEventListener('focus', render);
    input.addEventListener('input', render);
    input.addEventListener('blur', () => {
        // Kliknutí na návrh musí doběhnout před skrytím nabídky.
        setTimeout(() => {
            if (form.contains(document.activeElement) === false) {
                close();
            }
        }, 150);
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (panel.hidden) {
                render();
            }
            const list = rows();
            if (list.length === 0) {
                return;
            }
            const down = event.key === 'ArrowDown';
            active = active < 0 ? (down ? 0 : list.length - 1) : (active + (down ? 1 : -1) + list.length) % list.length;
            list.forEach((row, i) => {
                row.classList.toggle('active', i === active);
                row.setAttribute('aria-selected', String(i === active));
            });
            input.setAttribute('aria-activedescendant', list[active].id);
            list[active].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && active >= 0 && panel.hidden === false) {
            event.preventDefault();
            window.location.href = rows()[active].href;
        }
    });
    panel.addEventListener('mousedown', event => {
        if (event.target.closest('a') !== null) {
            event.preventDefault();
        }
    });
    document.addEventListener('click', event => {
        if (form.contains(event.target) === false) {
            close();
        }
    });
}

/* ---------------------------------------------------------------- formuláře */

// Enter v poli formuláře spustí tlačítko s třídou default (Uložit), ne první tlačítko ve formuláři (Odstranit).
// Prohlížeč bere první odesílací tlačítko, proto se na začátek formuláře vloží neviditelná kopie výchozího.
function initDefaultSubmit() {
    document.querySelectorAll('form').forEach(form => {
        const button = form.querySelector('input.default');
        if (button === null) {
            return;
        }
        const clone = button.cloneNode();
        clone.id = `${button.id}x`;
        clone.tabIndex = -1;
        clone.setAttribute('aria-hidden', 'true');
        Object.assign(clone.style, { position: 'absolute', left: '-2000px', top: '-2000px', height: '0', width: '0' });
        form.prepend(clone);
    });
}

// Chyby ověření ve všech formulářích stojí pod příslušným polem místo jednoho vyskakovacího okna.
// Chyby, které vrátí server, zůstávají, jak je šablona vykreslila.
function initFormErrors() {
    let sequence = 0;
    const records = new WeakMap();

    const updateStatus = form => {
        const count = form.querySelectorAll('.field-error-message').length;
        let message = form.classList.contains('form-dirty') ? 'Neuložené změny' : '';
        if (count > 0) {
            message = `Formulář obsahuje chyby (${count}). Před uložením je opravte.`;
        }
        form.querySelectorAll('.form-status').forEach(status => {
            status.textContent = message;
            status.classList.toggle('form-status-error', count > 0);
        });
    };

    const clearError = record => {
        record.fields.forEach(field => {
            const description = (field.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(id => id !== '' && id !== record.id);
            if (description.length > 0) {
                field.setAttribute('aria-describedby', description.join(' '));
            } else {
                field.removeAttribute('aria-describedby');
            }
            const previous = record.previousInvalid.get(field);
            if (previous === null) {
                field.removeAttribute('aria-invalid');
            } else {
                field.setAttribute('aria-invalid', previous);
            }
            field.classList.remove('field-invalid');
            records.delete(field);
        });
        record.note.remove();
    };

    if (window.Nette !== undefined) {
        window.Nette.showFormErrors = (form, errors) => {
            form.querySelectorAll('.field-invalid').forEach(field => {
                const record = records.get(field);
                if (record !== undefined) {
                    clearError(record);
                }
            });
            form.querySelectorAll(':scope > .client-form-error').forEach(error => error.remove());
            let first = null;
            errors.forEach(error => {
                const field = error.element;
                if (field === undefined || field === null || field === form) {
                    const note = document.createElement('div');
                    note.className = 'client-form-error field-error-message';
                    note.setAttribute('role', 'alert');
                    note.textContent = error.message;
                    form.prepend(note);
                    return;
                }
                let record = records.get(field);
                if (record === undefined) {
                    const fields = field.type === 'radio'
                        ? [...form.elements].filter(other => other.type === 'radio' && other.name === field.name)
                        : [field];
                    const id = `form-field-error-${++sequence}`;
                    const note = document.createElement('div');
                    note.className = 'field-error';
                    note.id = id;
                    note.setAttribute('role', 'alert');
                    let anchor = fields[fields.length - 1];
                    if (anchor.parentElement.matches('label')) {
                        anchor = anchor.parentElement;
                    }
                    anchor.after(note);
                    record = { fields, note, id, previousInvalid: new Map() };
                    fields.forEach(control => {
                        record.previousInvalid.set(control, control.getAttribute('aria-invalid'));
                        records.set(control, record);
                        control.classList.add('field-invalid');
                        control.setAttribute('aria-invalid', 'true');
                        control.setAttribute('aria-describedby', `${control.getAttribute('aria-describedby') ?? ''} ${id}`.trim());
                    });
                    first ??= fields.find(control => isShown(control) && control.disabled === false) ?? null;
                }
                const message = document.createElement('div');
                message.className = 'field-error-message';
                message.textContent = error.message;
                record.note.append(message);
            });
            updateStatus(form);
            if (first !== null) {
                first.focus();
                first.scrollIntoView({ block: 'center' });
            }
        };
    }

    const onEdit = event => {
        const record = records.get(event.target);
        if (record !== undefined) {
            clearError(record);
            updateStatus(event.target.form);
        }
    };
    document.addEventListener('input', onEdit);
    document.addEventListener('change', onEdit);

    // Formulář člena po částech: neuložené změny, dotaz při odchodu a zvýraznění části v rozcestníku.
    const form = document.querySelector('form.member-form');
    if (form === null) {
        return;
    }
    let leaving = false;
    const markDirty = () => {
        form.classList.add('form-dirty');
        updateStatus(form);
    };
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    updateStatus(form);
    // Na window už proběhlo ověření Nette i obsluhy formuláře.
    window.addEventListener('submit', event => {
        if (event.target === form && event.defaultPrevented === false) {
            leaving = true;
        }
    });
    window.addEventListener('pageshow', () => { leaving = false; });
    window.addEventListener('beforeunload', event => {
        if (form.classList.contains('form-dirty') && leaving === false) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    const sections = [...form.querySelectorAll('.form-section')];
    const links = [...document.querySelectorAll('.form-nav a')];
    let waiting = false;
    const highlightSection = () => {
        const topbar = document.querySelector('.app-topbar');
        const top = Math.max(150, (topbar?.getBoundingClientRect().bottom ?? 0) + 40);
        let current = 0;
        sections.forEach((section, index) => {
            if (section.getBoundingClientRect().top <= top) {
                current = index;
            }
        });
        if (window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 2) {
            current = sections.length - 1;
        }
        links.forEach((link, index) => {
            link.classList.toggle('active', index === current);
            if (index === current) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        });
        waiting = false;
    };
    const schedule = () => {
        if (waiting === false) {
            waiting = true;
            window.requestAnimationFrame(highlightSection);
        }
    };
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    highlightSection();
}

/* ------------------------------------------------------- tabulky a ovládání */

// Široká tabulka se posouvá ve vlastním rámu, aby se do strany neposouvala celá stránka.
function initTableFrames() {
    document.querySelectorAll('.main table.table').forEach(table => {
        if (table.closest('.grido, table.form, .table-scroll') !== null || table.parentElement.closest('table') !== null) {
            return;
        }
        const frame = document.createElement('div');
        frame.className = 'table-scroll';
        table.before(frame);
        frame.append(table);
    });
}

// Tabulky Grido: grid širší než stránka se posouvá ve vlastním rámu; seznamy členů mají rám už v šabloně
// (.user-list-scroll), ostatním gridům je rámem sám formulář a jde na něj klávesnicí.
// Změna počtu položek na stránce se rovnou projeví; "..." ve stránkování se zeptá na číslo stránky.
function initGrids() {
    const markWideGrids = () => {
        document.querySelectorAll('form.grido').forEach(grid => {
            const table = grid.querySelector(':scope > table');
            const wide = table !== null && table.offsetWidth > grid.clientWidth + 1;
            const ownFrame = grid.closest('.user-list-scroll') === null;
            grid.classList.toggle('grid-wide', wide);
            if (wide && ownFrame) {
                grid.tabIndex = 0;
            } else {
                grid.removeAttribute('tabindex');
            }
        });
    };
    markWideGrids();
    window.addEventListener('resize', markWideGrids);

    document.addEventListener('change', event => {
        const select = event.target.closest('.grido .count select');
        if (select !== null) {
            const form = select.form;
            const button = form?.querySelector('[name="buttons[perPage]"]');
            if (button) {
                form.requestSubmit(button);
            } else {
                form?.requestSubmit();
            }
        }
    });
    document.addEventListener('click', event => {
        const prompt = event.target.closest('.grido [data-grido-prompt]');
        if (prompt === null) {
            return;
        }
        event.preventDefault();
        const page = Number.parseInt(window.prompt(prompt.dataset.gridoPrompt) ?? '', 10);
        if (Number.isInteger(page) && page > 0) {
            window.location.href = prompt.dataset.gridoLink.replace(/page=0/, `page=${page}`);
        }
    });
}

// Záložky (vzor záložek WAI-ARIA): kliknutí nebo šipky, Home a End přepnou panel; v pořadí Tab je jen vybraná záložka.
function initTabs() {
    const show = tab => {
        tab.closest('[role="tablist"]').querySelectorAll('[role="tab"]').forEach(other => {
            const selected = other === tab;
            other.setAttribute('aria-selected', String(selected));
            other.tabIndex = selected ? 0 : -1;
            other.closest('li')?.classList.toggle('active', selected);
            const panel = document.getElementById(other.getAttribute('aria-controls'));
            panel?.classList.toggle('active', selected);
        });
    };
    document.addEventListener('click', event => {
        const tab = event.target.closest('[role="tab"][data-toggle="tab"]');
        if (tab !== null) {
            event.preventDefault();
            show(tab);
        }
    });
    document.addEventListener('keydown', event => {
        const tab = event.target.closest?.('[role="tab"][data-toggle="tab"]');
        if (tab === null || tab === undefined) {
            return;
        }
        if (event.key === ' ') {
            event.preventDefault();
            show(tab);
            return;
        }
        const tabs = [...tab.closest('[role="tablist"]').querySelectorAll('[role="tab"]')];
        const index = tabs.indexOf(tab);
        const target = { End: tabs.length - 1, Home: 0, ArrowLeft: index - 1, ArrowRight: index + 1 }[event.key];
        if (target === undefined) {
            return;
        }
        event.preventDefault();
        const next = tabs[(target + tabs.length) % tabs.length];
        show(next);
        next.focus();
    });
}

// Tlačítko s data-toggle="collapse" rozbalí a sbalí prvek, který jmenuje v aria-controls.
function initCollapse() {
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-toggle="collapse"][aria-controls]');
        if (button === null) {
            return;
        }
        const target = document.getElementById(button.getAttribute('aria-controls'));
        if (target !== null) {
            target.hidden = target.hidden === false;
            button.setAttribute('aria-expanded', String(target.hidden === false));
        }
    });
}

/* --------------------------------------------- zvýraznění prvku z adresy (#...) */

// Odkaz jako "#ip10.107.12.1" posune stránku k prvku s id "highlightable-ip10.107.12.1" a krátce ho zvýrazní.
function initHighlight() {
    const element = document.getElementById(`highlightable-${window.location.hash.replace('#', '')}`);
    if (element === null) {
        return;
    }
    element.classList.add('highlighted');
    setTimeout(() => element.classList.add('highlighted-fadeout'), 1000);
    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/* --------------------------------------------------------- kopírování hodnot */

function copyTextToClipboard(text) {
    const textArea = document.createElement('textarea');
    Object.assign(textArea.style, {
        position: 'fixed', top: '0', left: '0', width: '2em', height: '2em', padding: '0',
        border: 'none', outline: 'none', boxShadow: 'none', background: 'transparent',
    });
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    let successful = false;
    try {
        successful = document.execCommand('copy');
    } catch {
        successful = false;
    }
    document.body.removeChild(textArea);
    return successful;
}

// Vrací Promise s true, když se text dostal do schránky. navigator.clipboard je jen na HTTPS a na localhostu.
function writeTextToClipboard(text) {
    if (navigator.clipboard === undefined) {
        return Promise.resolve(copyTextToClipboard(text));
    }
    return navigator.clipboard.writeText(text).then(() => true, () => copyTextToClipboard(text));
}

// Text prvku, který leží uvnitř výběru. Prvek, kterého se výběr jen dotýká na okraji, dá prázdný řetězec.
function selectedPartOf(element, range) {
    if (range.intersectsNode(element) === false) {
        return '';
    }
    const part = document.createRange();
    part.selectNodeContents(element);
    if (part.compareBoundaryPoints(Range.START_TO_START, range) < 0) {
        part.setStart(range.startContainer, range.startOffset);
    }
    if (part.compareBoundaryPoints(Range.END_TO_END, range) > 0) {
        part.setEnd(range.endContainer, range.endOffset);
    }
    return part.toString();
}

// Bílé znaky se při porovnání výběru s hodnotou nepočítají: oddělovače buněk a řádků k hodnotě nepatří
// a pevnou mezeru vrací Firefox z výběru jako obyčejnou.
function withoutWhitespace(text) {
    return text.replace(/\s/g, '');
}

// Na chvíli ukáže u tlačítka výsledek kopírování, pak vrátí původní ikonu a nápovědu.
function showCopyResult(button, copied) {
    const icon = button.querySelector('.fa');
    const resultIcon = copied ? 'fa-check' : 'fa-exclamation-triangle';
    const resultText = copied ? 'Zkopírováno' : 'Kopírování se nezdařilo. Klikněte na hodnotu a zkopírujte ji ručně.';
    const status = document.getElementById('copy-value-status');

    tipText(button);
    icon?.classList.remove('fa-copy', 'fa-check', 'fa-exclamation-triangle');
    icon?.classList.add(resultIcon);
    button.dataset.copyResult = copied ? 'ok' : 'error';
    if (status !== null) {
        status.textContent = resultText;
    }
    // copyTextToClipboard přesouvá fokus do pomocného pole; vrátit ho, ať jde pokračovat klávesnicí.
    button.focus();
    tip.show(button, resultText);
    clearTimeout(button.copyResultTimer);
    button.copyResultTimer = setTimeout(() => {
        icon?.classList.remove(resultIcon);
        icon?.classList.add('fa-copy');
        delete button.dataset.copyResult;
        if (status !== null) {
            status.textContent = '';
        }
        button.dataset.tip = button.getAttribute('aria-label') ?? button.dataset.tip;
        tip.hide(button);
    }, 2000);
}

// Login a heslo zařízení (buňky skládá App\Model\IPAdresa): hodnota je v prvku .copy-value, za ní tlačítko .copy-value-button.
function initCopyValues() {
    document.addEventListener('click', event => {
        const button = event.target.closest('.copy-value-button');
        if (button === null) {
            return;
        }
        const value = button.parentElement.querySelector('.copy-value');
        writeTextToClipboard(value?.textContent ?? '').then(copied => showCopyResult(button, copied));
    });

    // Výběr tažený myší, který skončí nad tlačítkem, ve Firefoxu nedosáhne na konec hodnoty.
    // Po dobu tažení proto tlačítka myš nevnímají (pointer-events v screen.css) a výběr se chová jako nad prázdným místem.
    document.addEventListener('mousedown', event => {
        if (event.button !== 0 || event.target.closest('.copy-value-button') !== null) {
            return;
        }
        document.body.classList.add('copy-value-selecting');
        // Puštění tlačítka myši se může ztratit (kontextové menu, jiné okno); skončí to i první pohyb bez stisku.
        const controller = new AbortController();
        const stop = move => {
            if (move.type === 'mouseup' || move.buttons !== 1) {
                document.body.classList.remove('copy-value-selecting');
                controller.abort();
            }
        };
        document.addEventListener('mousemove', stop, { signal: controller.signal });
        document.addEventListener('mouseup', stop, { signal: controller.signal });
    });

    // Kliknutí nebo dvojklik označí celou hodnotu; dvojklik by jinak vybral jen část hesla po nejbližší oddělovač.
    // Výběr tažením zůstává na prohlížeči (user-select: all v CSS ho v Chromiu i Firefoxu kazí).
    // Označuje se až po obsluze kliknutí: Chromium a WebKit při ní výběr teprve ruší.
    const selectValue = event => {
        const value = event.target.closest('.copy-value');
        if (value !== null && event.shiftKey === false) {
            setTimeout(() => window.getSelection().selectAllChildren(value), 0);
        }
    };
    document.addEventListener('click', selectValue);
    document.addEventListener('dblclick', selectValue);

    // Výběr tažením myší často začne nebo skončí mimo hodnotu: prohlížeč pak před ni vloží tabulátor
    // (oddělovač buněk tabulky), za ni někdy konec řádku, nebo z ní pár znaků chybí. Zasahuje-li výběr
    // do jediné hodnoty a nic dalšího v něm není, jde do schránky celá a přesná a celá se i označí.
    // Kopírování z textového pole (i z pomocného pole v copyTextToClipboard) se netýká výběru na stránce.
    document.addEventListener('copy', event => {
        const selection = window.getSelection();
        if (selection.rangeCount !== 1 || event.target.closest?.('input, textarea')) {
            return;
        }
        const range = selection.getRangeAt(0);
        const values = [...document.querySelectorAll('.copy-value')].filter(value => selectedPartOf(value, range) !== '');
        if (values.length !== 1 || withoutWhitespace(selection.toString()) !== withoutWhitespace(selectedPartOf(values[0], range))) {
            return;
        }
        event.clipboardData.setData('text/plain', values[0].textContent);
        event.preventDefault();
        selection.selectAllChildren(values[0]);
    });

    // Výsledek kopírování pro čtečky obrazovky.
    if (document.querySelector('.copy-value-button') !== null) {
        const status = document.createElement('div');
        status.id = 'copy-value-status';
        status.className = 'sr-only';
        status.setAttribute('role', 'status');
        document.body.append(status);
    }

    // Tlačítko SSH u IP adresy zkopíruje příkaz do schránky.
    document.addEventListener('click', event => {
        const button = event.target.closest('.wboxbutton');
        if (button === null) {
            return;
        }
        event.preventDefault();
        const text = button.getAttribute('tag');
        if (text !== null && text !== '') {
            copyTextToClipboard(text);
        }
    });
}

// Tlačítka pro zkopírování na detailu člena (e-mail, telefon, číslo karty, IP adresy). IP adresám je doplní
// skript, jen v tabulce, kterou šablona označí data-copy-ips="true" (člen, kterého uživatel smí vidět celého).
function initCopyButtons() {
    document.querySelectorAll('#member-ip[data-copy-ips="true"] tr[id^="highlightable-ip"] .ip-address').forEach(cell => {
        const link = cell.querySelector(':scope > a');
        const value = (link ?? cell).textContent.trim();
        // Adresa a tlačítko drží pohromadě (.ip-copy); štítek pingu z pinger.js stojí až za nimi.
        const copy = document.createElement('span');
        copy.className = 'ip-copy';
        copy.append(...cell.childNodes);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'copy-button';
        button.dataset.copyValue = value;
        button.title = 'Zkopírovat IP adresu';
        button.setAttribute('aria-label', 'Zkopírovat IP adresu');
        const icon = document.createElement('i');
        icon.className = 'icon icon-copy';
        icon.setAttribute('aria-hidden', 'true');
        button.append(icon);
        copy.append(button);
        cell.append(copy);
    });

    if (document.querySelector('.copy-button') === null) {
        return;
    }
    const feedback = document.createElement('span');
    feedback.className = 'sr-only';
    feedback.setAttribute('role', 'status');
    feedback.setAttribute('aria-live', 'polite');
    document.querySelector('.main')?.append(feedback);

    document.addEventListener('click', event => {
        const button = event.target.closest('.copy-button');
        if (button === null) {
            return;
        }
        const icon = button.querySelector('i');
        icon.dataset.original ??= icon.className;
        const done = success => {
            feedback.textContent = success ? 'Zkopírováno.' : 'Kopírování se nezdařilo. Označte a zkopírujte hodnotu ručně.';
            button.classList.toggle('copied', success);
            icon.className = success ? 'icon icon-check' : 'icon icon-circle-alert';
            clearTimeout(button.copyTimer);
            button.copyTimer = setTimeout(() => {
                button.classList.remove('copied');
                icon.className = icon.dataset.original;
            }, 1300);
        };
        writeTextToClipboard(button.dataset.copyValue ?? '').then(success => {
            done(success);
            button.focus();
        });
    });
}

function openMikrotikWebfig(ip, username, password) {
    const html = '<!doctype html><html>'
        + "<head><script>window.name='login=" + username + '|' + password + "';window.location.replace('http://" + ip + "/webfig/#Wireless');</script></head><body></body></html>";
    const webfigWindow = window.open('', '');
    webfigWindow.document.write(html);
    return false;
}

/* ------------------------------------------------------------------- štítky */

async function sendLabelRequest(url, init) {
    const response = await fetch(url, { ...init, headers: { 'X-Requested-With': 'XMLHttpRequest', ...init.headers } });
    if (response.ok === false) {
        throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
}

// Štítek, jak ho vykreslí UserLabelsComponent.latte; barvy ve vzhledu aplikace spočítal server (BarvaStitku).
function labelBadge(response, stitekId, userId) {
    const badge = document.createElement('span');
    badge.className = 'badge';
    badge.setAttribute('style', response.styl);
    const text = document.createElement('span');
    text.textContent = response.text;
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'badge-remove';
    remove.dataset.stitekId = stitekId;
    remove.dataset.userId = userId;
    remove.title = 'Odebrat štítek';
    remove.setAttribute('aria-label', `Odebrat štítek ${response.text}`);
    remove.textContent = '×';
    badge.append(text, remove);
    return badge;
}

// Přidání a odebrání štítku bez načtení stránky. Člen může být na stránce víckrát, změní se všude.
function initLabels() {
    const urls = document.body.dataset;
    const pending = new Set();
    const components = userId => [...document.querySelectorAll('.user-labels-component')].filter(component => component.dataset.userId === userId);

    // Prázdný seznam má napsáno „žádné“; štítky, které člen už má, jsou v nabídce označené a nejdou přidat znovu.
    const refresh = () => {
        document.querySelectorAll('.user-labels-component').forEach(component => {
            const labels = component.querySelector('.user-labels');
            if (labels === null) {
                return;
            }
            labels.querySelector('.labels-empty')?.remove();
            if (labels.querySelector('.badge') === null) {
                const empty = document.createElement('span');
                empty.className = 'labels-empty';
                empty.textContent = 'žádné';
                labels.append(empty);
            }
            component.querySelectorAll('.stitky-item').forEach(item => {
                const assigned = labels.querySelector(`.badge-remove[data-stitek-id="${CSS.escape(item.dataset.id)}"]`) !== null;
                item.classList.toggle('label-assigned', assigned);
                item.setAttribute('aria-disabled', String(assigned));
                item.title = assigned ? 'Účastník tento štítek už má' : 'Přidat štítek';
            });
        });
    };

    const showError = (userId, message) => {
        components(userId).forEach(component => {
            component.querySelector('.label-error')?.remove();
            const error = document.createElement('span');
            error.className = 'label-error';
            error.setAttribute('role', 'alert');
            error.textContent = message;
            component.append(error);
        });
    };

    document.addEventListener('click', async event => {
        const control = event.target.closest('.user-labels-component .badge-remove, .user-labels-component .stitky-item');
        if (control === null) {
            return;
        }
        event.preventDefault();
        const component = control.closest('.user-labels-component');
        const userId = component.dataset.userId;
        const removing = control.classList.contains('badge-remove');
        const labelId = removing ? control.dataset.stitekId : control.dataset.id;
        const key = `${userId}:${labelId}`;
        if (pending.has(key) || (removing === false && control.getAttribute('aria-disabled') === 'true')) {
            return;
        }
        control.closest('[popover]')?.hidePopover();
        const url = removing ? urls.stitkyDeleteUrl : urls.stitkySaveUrl;
        if (url === undefined || url === '') {
            showError(userId, 'Chybí adresa pro úpravu štítků. Obnovte stránku.');
            return;
        }
        pending.add(key);
        components(userId).forEach(other => other.querySelector('.label-error')?.remove());
        try {
            const response = removing
                ? await sendLabelRequest(urls.stitkyDeleteUrl, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ stitek_id: labelId, user_id: userId }),
                })
                : await sendLabelRequest(urls.stitkySaveUrl, {
                    method: 'POST',
                    body: new URLSearchParams({ stitek_id: labelId, user_id: userId }),
                });
            if (response.success !== true) {
                showError(userId, 'Štítek se nepodařilo změnit. Zkuste to znovu.');
                return;
            }
            components(userId).forEach(other => {
                const labels = other.querySelector('.user-labels');
                labels.querySelectorAll(`.badge-remove[data-stitek-id="${CSS.escape(labelId)}"]`).forEach(button => button.closest('.badge').remove());
                if (removing === false) {
                    labels.append(labelBadge(response, labelId, userId));
                }
            });
            refresh();
            if (document.activeElement === document.body || document.activeElement === control || control.isConnected === false) {
                component.querySelector('.stitky-toggle')?.focus();
            }
        } catch {
            showError(userId, 'Nepodařilo se připojit k serveru. Zkuste to znovu.');
        } finally {
            pending.delete(key);
        }
    });
    refresh();
}

/* --------------------------------------------- posuvník seznamu členů AP */

// Pomocný vodorovný posuvník u dolního okraje okna, dokud je spodní okraj dlouhého seznamu členů AP mimo okno:
// posuvník rámu by jinak byl až pod posledním řádkem. Jen pro myš; na dotykovém displeji se rám posouvá prstem.
function initListScrollbar() {
    const frame = document.querySelector('.user-list-scroll-with-bar');
    const bar = document.querySelector('.user-list-scrollbar');
    if (frame === null || bar === null) {
        return;
    }
    const mouse = window.matchMedia('(any-hover: hover) and (any-pointer: fine)');

    const updateVisibility = () => {
        const bounds = frame.getBoundingClientRect();
        const viewportBottom = document.documentElement.clientHeight;
        bar.hidden = mouse.matches === false || Number(bar.max) === 0
            || bounds.top >= viewportBottom || bounds.bottom <= viewportBottom;
    };
    const syncFromFrame = () => {
        if (bar.valueAsNumber !== frame.scrollLeft) {
            bar.valueAsNumber = frame.scrollLeft;
        }
    };
    const resizeBar = () => {
        const width = frame.clientWidth;
        const contentWidth = frame.scrollWidth;
        bar.max = String(contentWidth - width);
        if (contentWidth > width) {
            const bounds = frame.getBoundingClientRect();
            const nativeScrollbarHeight = frame.offsetHeight - frame.clientHeight;
            const thumbWidth = Math.max(24, Math.round(width * width / contentWidth));
            bar.style.left = `${bounds.left}px`;
            bar.style.width = `${width}px`;
            bar.style.setProperty('--user-list-scroll-thumb-width', `${thumbWidth}px`);
            bar.style.setProperty('--user-list-scrollbar-height', `${Math.max(14, nativeScrollbarHeight)}px`);
            syncFromFrame();
        }
        updateVisibility();
    };

    frame.addEventListener('scroll', syncFromFrame);
    bar.addEventListener('input', () => {
        frame.scrollLeft = bar.valueAsNumber;
    });
    // Po tažení mají šipky a Page Down zase posouvat stránku.
    window.addEventListener('pointerup', () => {
        if (document.activeElement === bar) {
            bar.blur();
        }
    });
    window.addEventListener('scroll', updateVisibility, { passive: true });
    window.addEventListener('resize', resizeBar);
    mouse.addEventListener('change', updateVisibility);

    // Hledání při psaní a údaje doplněné do buněk (ping) mění velikost tabulky bez změny velikosti okna;
    // skrytí a zobrazení postranního panelu mění šířku rámu.
    const observer = new ResizeObserver(resizeBar);
    observer.observe(frame);
    const table = frame.querySelector('table');
    if (table !== null) {
        observer.observe(table);
    }
}

/* ------------------------------------------------------- filtr seznamu členů */

// Hledání a štítky jdou na server (Enter, tlačítko Hledat, výběr v nabídce), takže platí pro všechny stránky
// tabulky. Když jsou všechny řádky na jedné stránce, filtruje se navíc hned při psaní.
function initMemberFilter() {
    const filter = document.querySelector('.member-filter');
    const input = filter?.querySelector('input[type="search"]');
    if (input === null || input === undefined) {
        return;
    }
    const form = document.getElementById(input.getAttribute('form'));
    const count = filter.querySelector('.quick-filter-count');
    const live = filter.dataset.live === 'true';
    const applied = (filter.dataset.applied ?? '').trim();

    const submit = () => {
        const button = filter.querySelector('button[name="buttons[search]"]');
        form.requestSubmit(button);
    };

    const applyFilter = () => {
        const rows = [...form.querySelectorAll('tbody tr[class*="grid-row-"]')];
        const value = input.value.trim();
        // Řádky už vybral server; jeho hledání je přesnější než text buněk (zkrácené poznámky, skryté sloupce).
        if (live === false || value === applied) {
            rows.forEach(row => row.classList.remove('quick-filter-hidden'));
            count.textContent = value === applied ? count.dataset.total : 'Enter hledá ve všech účastnících';
            filter.classList.remove('quick-filter-no-match');
            return;
        }
        const needle = fold(value);
        let shown = 0;
        rows.forEach(row => {
            // Nabídka obsahuje všechny štítky, do filtru patří jen hodnoty řádku a přiřazené štítky.
            const searchable = row.cloneNode(true);
            searchable.querySelectorAll('.stitky-menu, .labels-empty, button, script').forEach(element => element.remove());
            const matches = fold(searchable.textContent).includes(needle);
            row.classList.toggle('quick-filter-hidden', matches === false);
            if (matches) {
                shown++;
            }
        });
        count.textContent = `${shown} z ${rows.length} řádků, Enter hledá na serveru`;
        filter.classList.toggle('quick-filter-no-match', shown === 0);
    };

    input.addEventListener('input', applyFilter);
    filter.addEventListener('change', event => {
        if (event.target.matches('select[data-submit-on-change]')) {
            submit();
        }
        if (event.target.matches('.label-chip-add')) {
            const labels = filter.querySelector('input[name="filters[stitky]"]');
            const chosen = event.target.value;
            if (chosen === '') {
                return;
            }
            const values = labels.value === '' ? [] : labels.value.split(',');
            if (values.includes(chosen) === false) {
                values.push(chosen);
            }
            labels.value = values.join(',');
            event.target.value = '';
            submit();
        }
    });
    applyFilter();
}

/* ------------------------------------------------------------------- start */

function start() {
    initSidebar();
    initMenus();
    initTips();
    initTheme();
    initSearch();
    initDefaultSubmit();
    initFormErrors();
    initTableFrames();
    initGrids();
    initTabs();
    initCollapse();
    initHighlight();
    initCopyValues();
    initCopyButtons();
    initLabels();
    initMemberFilter();
    initListScrollbar();
}

// S atributem defer je HTML už načtené; bez něj (např. v hlavičce stránky) se počká na jeho konec.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}
