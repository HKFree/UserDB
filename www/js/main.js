$(document).ready(function () {
    $(".datepicker").change(function () {
        if ($(this).val().indexOf("00:00:00") > -1) $(this).val($(this).val().replace(" 00:00:00", ""));
    });
    $(".datepicker").change();
    $(".datepicker").off("change");

    $(".datepicker").datepicker({ dateFormat: "dd.mm.yy" });

    // na uzkem displeji je seznam oblasti vysouvaci panel (viz .sidebar-open v screen.css)
    var sidebarToggle = $("#navareas");
    function setSidebarOpen(open, focusFilter) {
        $("html").toggleClass("sidebar-open", open);
        sidebarToggle.attr("aria-expanded", open ? "true" : "false");
        if (open) {
            // otevrene menu by zustalo pod panelem
            $(".navbar-collapse.in").collapse("hide");
            if (focusFilter) {
                $("#sidebar-filter-input").focus();
            }
        }
    }
    sidebarToggle.click(function (event) {
        // po tuknuti prstem by kurzor v poli vysunul klavesnici pres seznam oblasti;
        // event.detail je 0, kdyz tlacitko stiskla klavesnice
        var focusFilter = event.detail === 0 || !window.matchMedia("(pointer: coarse)").matches;
        setSidebarOpen(!$("html").hasClass("sidebar-open"), focusFilter);
    });
    $(".sidebar-backdrop").click(function () {
        setSidebarOpen(false);
    });
    $(document).keydown(function (event) {
        if (event.key === "Escape" && $("html").hasClass("sidebar-open")) {
            setSidebarOpen(false);
            sidebarToggle.focus();
        }
    });

    $('[data-toggle="tooltip"]').tooltip();

    $('[data-toggle="popover"]').popover();
});

// EXTREMNI prasarna pro "nastaveni" defaultniho inputu ve formulari
$(document).ready(function () {
    $('form').each(function () {
        var thisform = $(this);
        var clonebutton = thisform.find('input.default');
        var newID = clonebutton.attr('id') + "x";
        thisform.prepend(clonebutton.clone().css({
            position: 'absolute',
            left: '-2000px',
            top: '-2000px',
            height: 0,
            width: 0
        }).attr('id', newID));
    });
});

// Strom oblasti v levem panelu: sbaleni vetvi, zvyrazneni prave otevrene oblasti nebo AP a rozbaleni jeji vetve.
// Vola se hned za levym panelem (viz @layout.latte), aby velka stranka neukazovala nezvyrazneny rozbaleny
// strom, dokud se nenacte cela. Detail clena, uprava clena a detail AP patri k AP, na jehoz seznam clenu
// odkazuji v hlavicce stranky; jejich sablony proto volaji znovu hned za hlavickou. Volani po nacteni je pojistka.
window.userdbSidebarTree = function () {
    // opakovane volani bonsai() strom znovu nezaklada
    var tree = $('#oblastitree').bonsai().data('bonsai');
    var current = window.location.pathname;
    var related = $('.page-crumbs a, .page-actions a').filter(function () {
        return /\/uzivatel-list\/list\/\d+$/.test(this.pathname);
    }).map(function () {
        return this.pathname;
    }).get();
    $('.sidebar .nav-sidebar a').each(function () {
        if (this.pathname !== current && related.indexOf(this.pathname) === -1) return;
        if (this.pathname === current) $(this).attr('aria-current', 'page');
        var item = $(this).parent();
        if (item.hasClass('active')) return;
        item.addClass('active');
        // vetve v "Moje oblasti" jsou rozbalene porad a ke stromu nepatri
        var branch = item.parent().closest('#oblastitree li');
        if (tree && branch.length > 0) tree.expand(branch);
    });
};
$(document).ready(window.userdbSidebarTree);

// Filtr oblasti a AP v levem panelu, bez ohledu na diakritiku a velikost pismen
$(document).ready(function () {
    var input = $('#sidebar-filter-input');
    var sidebar = $('.sidebar');
    var areas = sidebar.find('ul.nav-sidebar > li');

    function fold(text) {
        return $.trim(text).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    input.on('input', function () {
        var needle = fold(input.val());
        var found = false;
        areas.each(function () {
            var area = $(this);
            var areaText = fold(area.children('a').text());
            var areaMatches = areaText.indexOf(needle) !== -1;
            var shown = areaMatches;
            area.find('li').each(function () {
                // AP se najde i pod zapisem "Oblast - AP", jak se dosud v seznamu vypisovalo
                var apText = fold($(this).text());
                var matches = areaMatches || apText.indexOf(needle) !== -1 || (areaText + ' - ' + apText).indexOf(needle) !== -1;
                $(this).toggleClass('sidebar-hidden', !matches);
                shown = shown || matches;
            });
            area.toggleClass('sidebar-hidden', !shown);
            found = found || shown;
        });
        sidebar.toggleClass('sidebar-filtering', needle !== '');
        $('#sidebar-nomatch').prop('hidden', found);
    });
});

// Siroka tabulka se posouva ve vlastnim ramu, aby se do strany neposouvala cela stranka.
// Presun nativnim DOM, protoze jQuery by pri presunu znovu spustilo skripty uvnitr tabulky.
$(document).ready(function () {
    $('.main table.table').each(function () {
        if ($(this).closest('.grido, table.form, .table-scroll').length > 0 || $(this).parent().closest('table').length > 0) return;
        var frame = document.createElement('div');
        frame.className = 'table-scroll';
        this.parentNode.insertBefore(frame, this);
        frame.appendChild(this);
    });
});

// Grid sirsi nez stranka dostane vlastni posuvnik; grid, ktery se vejde, zustava bez nej
$(document).ready(function () {
    // siroky grid se posouva ve vlastnim ramu; seznamy clenu maji ram uz v sablone (.user-list-scroll),
    // ostatnim gridum je ramem sam formular a jde na nej klavesnici
    function markWideGrids() {
        $('form.grido').each(function () {
            var table = $(this).children('table')[0];
            var wide = !!table && table.offsetWidth > this.clientWidth + 1;
            var ownFrame = $(this).closest('.user-list-scroll').length === 0;
            $(this).toggleClass('grid-wide', wide);
            if (wide && ownFrame) $(this).attr('tabindex', '0');
            else $(this).removeAttr('tabindex');
        });
    }
    markWideGrids();
    $(window).on('resize', markWideGrids);
    $(document).ajaxComplete(function () {
        setTimeout(markWideGrids, 0);
    });

    // nabidka v gridu se kresli pres okraj ramu, jinak by ji posuvnik orizl
    function placeGridMenu(dropdown) {
        var button = $(dropdown).find('.dropdown-toggle')[0].getBoundingClientRect();
        var menu = $(dropdown).find('.dropdown-menu');
        var left = Math.max(8, Math.min(button.left, window.innerWidth - menu.outerWidth() - 8));
        var fitsBelow = button.bottom + menu.outerHeight() <= window.innerHeight;
        var minTop = $('.navbar-fixed-top').outerHeight() + 8;
        menu.css({ position: 'fixed', left: left, right: 'auto', top: fitsBelow ? button.bottom : Math.max(minTop, button.top - menu.outerHeight()) });
    }
    // tlacitko je videt, kdyz jeho stred lezi pod horni listou, v okne a ve viditelne casti ramu gridu
    // (stred, ne cely obdelnik: po posunu k tlacitku muze o zlomek pixelu presahovat okraj ramu)
    function gridToggleVisible(dropdown) {
        var button = $(dropdown).find('.dropdown-toggle')[0].getBoundingClientRect();
        // ram je obal seznamu clenu, jinde formular gridu (closest() by vratil blizsi formular)
        var frameElement = $(dropdown).closest('.user-list-scroll')[0] || $(dropdown).closest('form.grido')[0];
        var frame = frameElement ? frameElement.getBoundingClientRect() : { left: 0, top: 0, right: window.innerWidth, bottom: window.innerHeight };
        var bar = $('.navbar-fixed-top')[0];
        var top = Math.max(bar ? bar.getBoundingClientRect().bottom : 0, frame.top);
        var x = button.left + button.width / 2;
        var y = button.top + button.height / 2;
        return x >= Math.max(0, frame.left) && x <= Math.min(window.innerWidth, frame.right)
            && y >= top && y <= Math.min(window.innerHeight, frame.bottom);
    }
    $(document).on('shown.bs.dropdown', '.grido .dropdown', function () {
        placeGridMenu(this);
    });
    // posun stranky nebo ramu gridu nabidku presune s tlacitkem (i posun, kterym prohlizec
    // ukaze prave zamerene tlacitko); zavre ji, az tlacitko zmizi z ramu. Posun uvnitr nabidky ji nechava.
    document.addEventListener('scroll', function (event) {
        if ($(event.target).closest('.dropdown-menu').length > 0) return;
        $('.grido .dropdown.open').each(function () {
            if (gridToggleVisible(this)) placeGridMenu(this);
            else $(this).find('.dropdown-toggle').dropdown('toggle');
        });
    }, true);
});

// Scroll na objekt odkazovany ve fragmentu (napr. "#ip10.107.12.1")
// a zvyrazneni objektu (id musi byt napr. 'highlightable-ip10.107.12.1')
$(document).ready(function () {
    var elem = document.getElementById('highlightable-' + window.location.hash.replace('#', ''));
    if (elem) {
        console.log(elem);
        // nastavit vyrazne zvyrazneni
        $(elem).addClass('highlighted');
        // po chvili nastavit (v CSS animovane) slabsi zvyrazneni
        setTimeout(function () {
            $(elem).addClass('highlighted-fadeout');
        }, 1000);
        // scrollovat k elementu (musime odscrollovat trochu min, protoze nahore je fixni navbar-header)
        // scrollovat tak, aby element byl v prvni tretine obrazovky
        $('html, body').animate({
            scrollTop: $(elem).offset().top - $('.navbar-header').height() - ($(window).height() - $('.navbar-header').height()) / 3
        }, 1000);
    }
});

$(document).ready(function () {
    $(document).on('click', '.wboxbutton', function (event) {
        event.preventDefault();
        var txt = $(this).attr('tag');
        if (!txt || txt == '') {
            return;
        }

        copyTextToClipboard(txt);
    });
});

function copyTextToClipboard(text) {
    var textArea = document.createElement("textarea");

    textArea.style.position = 'fixed';
    textArea.style.top = 0;
    textArea.style.left = 0;
    textArea.style.width = '2em';
    textArea.style.height = '2em';
    textArea.style.padding = 0;
    textArea.style.border = 'none';
    textArea.style.outline = 'none';
    textArea.style.boxShadow = 'none';
    textArea.style.background = 'transparent';
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    var successful = false;
    try {
        successful = document.execCommand('copy');
        var msg = successful ? 'successful' : 'unsuccessful';
        console.log('Copying text command was ' + msg);
    } catch (err) {
        console.log('Oops, unable to copy');
    }
    document.body.removeChild(textArea);
    return successful;
}

/** KOPÍROVÁNÍ LOGINU A HESLA **/
// Buňky Login a Heslo skládá App\Model\IPAdresa: hodnota je v prvku .copy-value, za ní tlačítko .copy-value-button.
$(document).ready(function () {
    $(document).on('click', '.copy-value-button', function () {
        var button = $(this);
        writeTextToClipboard(button.siblings('.copy-value').text()).then(function (copied) {
            showCopyResult(button, copied);
        });
    });

    // Výběr tažený myší, který skončí nad tlačítkem, ve Firefoxu nedosáhne na konec hodnoty.
    // Po dobu tažení proto tlačítka myš nevnímají (pointer-events v screen.css) a výběr se chová jako nad prázdným místem.
    $(document).on('mousedown', function (event) {
        if (event.which !== 1 || $(event.target).closest('.copy-value-button').length > 0) {
            return;
        }
        $('body').addClass('copy-value-selecting');
        // Puštění tlačítka myši se může ztratit (kontextové menu, jiné okno); skončí to i první pohyb bez stisku.
        $(document).off('.copyValueDrag').on('mousemove.copyValueDrag mouseup.copyValueDrag', function (move) {
            if (move.type === 'mouseup' || move.buttons !== 1) {
                $('body').removeClass('copy-value-selecting');
                $(document).off('.copyValueDrag');
            }
        });
    });

    // Kliknutí nebo dvojklik označí celou hodnotu; dvojklik by jinak vybral jen část hesla po nejbližší oddělovač.
    // Výběr tažením zůstává na prohlížeči (user-select: all v CSS ho v Chromiu i Firefoxu kazí).
    // Označuje se až po obsluze kliknutí: Chromium a WebKit při ní výběr teprve ruší.
    $(document).on('click dblclick', '.copy-value', function (event) {
        var value = this;
        if (!event.shiftKey) {
            setTimeout(function () {
                window.getSelection().selectAllChildren(value);
            }, 0);
        }
    });

    // Výběr tažením myší často začne nebo skončí mimo hodnotu: prohlížeč pak před ni vloží tabulátor
    // (oddělovač buněk tabulky), za ni někdy konec řádku, nebo z ní pár znaků chybí. Zasahuje-li výběr
    // do jediné hodnoty a nic dalšího v něm není, jde do schránky celá a přesná a celá se i označí.
    // Kopírování z textového pole (i z pomocného pole v copyTextToClipboard) se netýká výběru na stránce.
    $(document).on('copy', function (event) {
        var selection = window.getSelection();
        if (selection.rangeCount !== 1 || $(event.target).is('input, textarea')) {
            return;
        }
        var range = selection.getRangeAt(0);
        var values = $('.copy-value').filter(function () {
            return selectedPartOf(this, range) !== '';
        });
        if (values.length !== 1 || withoutWhitespace(selection.toString()) !== withoutWhitespace(selectedPartOf(values[0], range))) {
            return;
        }
        event.originalEvent.clipboardData.setData('text/plain', values.text());
        event.preventDefault();
        selection.selectAllChildren(values[0]);
    });

    // Výsledek kopírování pro čtečky obrazovky.
    if ($('.copy-value-button').length > 0) {
        $('<div id="copy-value-status" class="sr-only" role="status"></div>').appendTo('body');
    }
});

// Text prvku, který leží uvnitř výběru. Prvek, kterého se výběr jen dotýká na okraji, dá prázdný řetězec.
function selectedPartOf(element, range) {
    if (!range.intersectsNode(element)) {
        return '';
    }
    var part = document.createRange();
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

// Vrací Promise s true, když se text dostal do schránky. navigator.clipboard je jen na HTTPS a na localhostu.
function writeTextToClipboard(text) {
    if (!navigator.clipboard) {
        return Promise.resolve(copyTextToClipboard(text));
    }
    return navigator.clipboard.writeText(text).then(function () {
        return true;
    }, function () {
        return copyTextToClipboard(text);
    });
}

// Na chvíli ukáže u tlačítka výsledek kopírování, pak vrátí původní ikonu a nápovědu.
function showCopyResult(button, copied) {
    var icon = button.children('.fa');
    var resultIcon = copied ? 'fa-check' : 'fa-exclamation-triangle';
    var resultText = copied ? 'Zkopírováno' : 'Kopírování se nezdařilo. Klikněte na hodnotu a zkopírujte ji ručně.';

    icon.removeClass('fa-copy fa-check fa-exclamation-triangle').addClass(resultIcon);
    $('#copy-value-status').text(resultText);
    // copyTextToClipboard přesouvá fokus do pomocného pole; vrátit ho, ať jde pokračovat klávesnicí.
    button.trigger('focus').attr('data-original-title', resultText).tooltip('show');
    clearTimeout(button.data('copyResultTimer'));
    button.data('copyResultTimer', setTimeout(function () {
        icon.removeClass(resultIcon).addClass('fa-copy');
        $('#copy-value-status').text('');
        button.attr('data-original-title', button.attr('aria-label')).tooltip('hide');
    }, 2000));
}
/** KOPÍROVÁNÍ LOGINU A HESLA **/

// Záložky Bootstrapu 3.2 nepřepínají aria-selected ani tabindex; čtečka obrazovky by hlásila záložku,
// která už vybraná není, a Tab by vedl na každou záložku zvlášť.
$(document).on('shown.bs.tab', '[role="tab"][data-toggle="tab"]', function (event) {
    $(event.relatedTarget).attr({ 'aria-selected': 'false', tabindex: '-1' });
    $(event.target).attr({ 'aria-selected': 'true', tabindex: '0' });
});

// Mezi záložkami se chodí šipkami, Home a End, Mezerník záložku otevře (vzor záložek WAI-ARIA).
$(document).on('keydown', '[role="tab"][data-toggle="tab"]', function (event) {
    var tabs = $(this).closest('[role="tablist"]').find('[role="tab"]');
    var index = tabs.index(this);
    var targets = { 35: tabs.length - 1, 36: 0, 37: index - 1, 39: index + 1 };
    if (event.which === 32) {
        event.preventDefault();
        $(this).tab('show');
        return;
    }
    if (!(event.which in targets)) {
        return;
    }
    event.preventDefault();
    tabs.eq((targets[event.which] + tabs.length) % tabs.length).tab('show').trigger('focus');
});

function openMikrotikWebfig(ip, username, password) {
    var theHTML = "<!doctype html><html>" +
        "<head><script>window.name='login=" + username + "|" + password + "';window.location.replace('http://" + ip + "/webfig/#Wireless');</script></head><body></body></html>";
    var webfigWindow = window.open("", "");
    webfigWindow.document.write(theHTML);
    return false;
}

/** STITKY **/
function showMessage(message, type = 'success') {
    const messageContainer = $('#message-container');
    const messageContent = $('#message-content');

    messageContent.text(message); // Nastav text zprávy
    messageContainer.removeClass('alert-success alert-danger').addClass(`alert-${type}`);
    messageContainer.show(); // Zobraz zprávu

    setTimeout(() => {
        messageContainer.fadeOut(); // Automaticky skryj zprávu po 5 sekundách
    }, 5000);
}


// Delegovane udalosti funguji i po obnoveni gridu pres AJAX.
$(document).ready(function () {
    var pendingLabels = {};

    // Bootstrap 3.2 stav aria-expanded sam neaktualizuje.
    $('.navbar-collapse').on('shown.bs.collapse hidden.bs.collapse', function (event) {
        $('.navbar-toggle').attr('aria-expanded', event.type === 'shown' ? 'true' : 'false');
    });
    $(document).on('shown.bs.dropdown hidden.bs.dropdown', '.dropdown, .btn-group', function (event) {
        $(this).find('.dropdown-toggle').attr('aria-expanded', event.type === 'shown' ? 'true' : 'false');
    });

    function components(userId) {
        return $('.user-labels-component').filter(function () {
            return $(this).attr('data-user-id') === userId;
        });
    }

    function refreshLabels() {
        $('.user-labels-component').each(function () {
            var component = $(this);
            var labels = component.find('.user-labels');
            labels.find('.labels-empty').remove();
            if (!labels.find('.badge').length) {
                labels.append($('<span class="labels-empty"></span>').text('žádné'));
            }
            component.find('.stitky-item').each(function () {
                var item = $(this);
                var assigned = labels.find('.badge-remove').filter(function () {
                    return $(this).attr('data-stitek-id') === item.attr('data-id');
                }).length > 0;
                item.toggleClass('label-assigned', assigned)
                    .attr('aria-disabled', assigned ? 'true' : 'false')
                    .attr('title', assigned ? 'Člen tento štítek už má' : 'Přidat štítek');
            });
        });
    }

    function labelError(userId, message) {
        components(userId).each(function () {
            $(this).find('.label-error').remove();
            $('<span class="label-error" role="alert"></span>').text(message).appendTo(this);
        });
    }

    $(document).on('click', '.user-labels-component .badge-remove, .user-labels-component .stitky-item', function (event) {
        event.preventDefault();
        var control = $(this);
        var component = control.closest('.user-labels-component');
        var userId = component.attr('data-user-id');
        var removing = control.hasClass('badge-remove');
        var labelId = control.attr(removing ? 'data-stitek-id' : 'data-id');
        var key = userId + ':' + labelId;
        if (pendingLabels[key] || (!removing && control.attr('aria-disabled') === 'true')) return;

        var url = $('body').data(removing ? 'stitky-delete-url' : 'stitky-save-url');
        if (!url) {
            labelError(userId, 'Chybí adresa pro úpravu štítků. Obnovte stránku.');
            return;
        }
        pendingLabels[key] = true;
        components(userId).find('.label-error').remove();
        var data = { stitek_id: labelId, user_id: userId };
        $.ajax({
            url: url,
            type: removing ? 'DELETE' : 'POST',
            contentType: removing ? 'application/json' : 'application/x-www-form-urlencoded; charset=UTF-8',
            data: removing ? JSON.stringify(data) : data,
            success: function (response) {
                if (!response.success) {
                    labelError(userId, 'Štítek se nepodařilo změnit. Zkuste to znovu.');
                    return;
                }
                components(userId).each(function () {
                    var labels = $(this).find('.user-labels');
                    labels.find('.badge-remove').filter(function () {
                        return $(this).attr('data-stitek-id') === labelId;
                    }).closest('.badge').remove();
                    if (!removing) {
                        // barvy ve vzhledu aplikace spocital server (BarvaStitku), atribut je jen z nich
                        var badge = $('<span class="badge"></span>').attr('style', response.styl)
                            .append($('<span></span>').text(response.text));
                        $('<button type="button" class="badge-remove">&times;</button>')
                            .attr({ 'data-stitek-id': labelId, 'data-user-id': userId,
                                'aria-label': 'Odebrat štítek ' + response.text, title: 'Odebrat štítek' })
                            .appendTo(badge);
                        labels.append(badge);
                    }
                });
                refreshLabels();
                if (document.activeElement === document.body || control.is(':focus')) {
                    component.find('.dropdown-toggle').focus();
                }
            },
            error: function () {
                labelError(userId, 'Nepodařilo se připojit k serveru. Zkuste to znovu.');
            },
            complete: function () {
                delete pendingLabels[key];
            }
        });
    });
    refreshLabels();
    $(document).ajaxComplete(refreshLabels);
});

// Filtruje jen nactene radky hlavniho gridu. Ovladani zustava mimo AJAX snippet.
$(document).ready(function () {
    var list = $('.member-list');
    var input = $('#member-quick-filter');
    if (!list.length) return;
    var compact = false;
    try {
        compact = localStorage.getItem('userdb_rows_compact') === 'true';
    } catch (error) {
        // Nastaveni je volitelne, napr. v soukromem rezimu muze byt uloziste nedostupne.
    }

    function applyFilter() {
        var needle = $.trim(input.val()).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        var rows = list.find('#grid tbody tr[class*="grid-row-"]');
        var shown = 0;
        rows.each(function () {
            // Nabidka obsahuje vsechny stitky, do filtru patri jen hodnoty radku a prirazene stitky.
            var searchable = this.cloneNode(true);
            $(searchable).find('.dropdown, .labels-empty, button, script').remove();
            var text = $(searchable).text().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            var matches = !needle || text.indexOf(needle) !== -1;
            $(this).toggleClass('quick-filter-hidden', !matches);
            if (matches) shown++;
        });
        $('.quick-filter-count').text(shown + ' z ' + rows.length + ' načtených řádků');
        $('.quick-filter').toggleClass('quick-filter-no-match', shown === 0 && needle !== '');
    }

    function applyDensity() {
        list.toggleClass('rows-compact', compact);
        list.find('.row-density button').each(function () {
            var active = ($(this).attr('data-compact') === '1') === compact;
            $(this).toggleClass('active', active).attr('aria-pressed', active ? 'true' : 'false');
        });
        $(window).trigger('resize');
    }

    input.on('input', applyFilter);
    list.on('click', '.row-density button', function () {
        compact = $(this).attr('data-compact') === '1';
        try {
            localStorage.setItem('userdb_rows_compact', compact ? 'true' : 'false');
        } catch (error) {
            // Zmena funguje i bez zapamatovani.
        }
        applyDensity();
    });
    applyFilter();
    applyDensity();
    $(document).ajaxComplete(function () {
        setTimeout(function () {
            applyFilter();
            applyDensity();
        }, 0);
    });
});

// Kopirovani IP doplnuje pouze autorizovanou tabulku, bez presouvani obsahu nebo skriptu.
$(document).ready(function () {
    $('#member-ip[data-copy-ips="true"] tr[id^="highlightable-ip"] > td:first-child').each(function () {
        var value = $.trim($(this).text());
        $('<button type="button" class="copy-button"><i class="fa fa-clone" aria-hidden="true"></i></button>')
            .attr({ 'data-copy-value': value, title: 'Zkopírovat IP adresu', 'aria-label': 'Zkopírovat IP adresu' })
            .appendTo(this);
    });

    var feedback = $('<span class="sr-only" role="status" aria-live="polite"></span>');
    if ($('.copy-button').length) feedback.appendTo('.main');
    $(document).on('click', '.copy-button', function () {
        var button = $(this);
        var value = button.attr('data-copy-value');
        function done(success) {
            feedback.text(success ? 'Zkopírováno.' : 'Kopírování se nezdařilo. Označte a zkopírujte hodnotu ručně.');
            button.toggleClass('copied', success);
            button.find('i').attr('class', success ? 'fa fa-check' : 'fa fa-exclamation-circle');
            setTimeout(function () {
                button.removeClass('copied').find('i').attr('class', 'fa fa-clone');
            }, 1300);
        }
        function fallback() {
            done(copyTextToClipboard(value));
            button.focus();
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(function () { done(true); }, fallback);
        } else {
            fallback();
        }
    });
});

// Validace vsech formularu: zpravy u poli, bez zmeny serverovych chyb.
$(document).ready(function () {
    var errorSequence = 0;

    function updateStatus(form) {
        var count = $(form).find('.field-error-message').length;
        var message = $(form).hasClass('form-dirty') ? 'Neuložené změny' : '';
        if (count > 0) {
            message = 'Formulář obsahuje chyby (' + count + '). Před uložením je opravte.';
        }
        $(form).find('.form-status').text(message).toggleClass('form-status-error', count > 0);
    }

    function clearError(record) {
        record.fields.each(function () {
            var field = $(this);
            var description = (field.attr('aria-describedby') || '').split(/\s+/).filter(function (id) {
                return id && id !== record.id;
            });
            if (description.length) field.attr('aria-describedby', description.join(' '));
            else field.removeAttr('aria-describedby');
            var previous = field.data('client-error-previous-invalid');
            if (previous === undefined) field.removeAttr('aria-invalid');
            else field.attr('aria-invalid', previous);
            field.removeClass('field-invalid').removeData('client-error').removeData('client-error-previous-invalid');
        });
        record.note.remove();
    }

    if (window.Nette) {
        // netteForms.js se nacita az za main.js, proto az po DOM ready.
        Nette.showFormErrors = function (form, errors) {
            $(form).find('.field-invalid').each(function () {
                var record = $(this).data('client-error');
                if (record) clearError(record);
            });
            $(form).children('.client-form-error').remove();
            var first = null;
            errors.forEach(function (error) {
                var field = error.element;
                if (!field || field === form) {
                    $('<div class="client-form-error field-error-message" role="alert"></div>').text(error.message).prependTo(form);
                    return;
                }
                var record = $(field).data('client-error');
                if (!record) {
                    var fields = $(field);
                    if (field.type === 'radio') {
                        fields = $(form.elements).filter(function () { return this.type === 'radio' && this.name === field.name; });
                    }
                    var id = 'form-field-error-' + (++errorSequence);
                    var note = $('<div class="field-error" role="alert"></div>').attr('id', id);
                    var anchor = fields.last();
                    if (anchor.parent().is('label')) anchor = anchor.parent();
                    note.insertAfter(anchor);
                    record = { fields: fields, note: note, id: id };
                    fields.each(function () {
                        var control = $(this);
                        control.data('client-error-previous-invalid', control.attr('aria-invalid'));
                        control.data('client-error', record).addClass('field-invalid').attr({
                            'aria-invalid': 'true',
                            'aria-describedby': $.trim((control.attr('aria-describedby') || '') + ' ' + id)
                        });
                    });
                    if (!first) first = fields.filter(':visible:not(:disabled)')[0];
                }
                $('<div class="field-error-message"></div>').text(error.message).appendTo(record.note);
            });
            updateStatus(form);
            if (first) {
                first.focus();
                first.scrollIntoView({ block: 'center' });
            }
        };
    }

    $(document).on('input change', 'input, select, textarea', function () {
        var record = $(this).data('client-error');
        if (record) {
            clearError(record);
            updateStatus(this.form);
        }
    });

    // Seskupeny formular clena: neulozene zmeny a navigace mezi oddily.
    var form = document.querySelector('form.member-form');
    if (!form) return;
    var leaving = false;
    $(form).on('input change', function () {
        $(form).addClass('form-dirty');
        updateStatus(form);
    });
    updateStatus(form);
    // Na window uz probehla validace Nette i obsluhy formulare.
    window.addEventListener('submit', function (event) {
        if (event.target === form && !event.defaultPrevented) leaving = true;
    });
    window.addEventListener('pageshow', function () { leaving = false; });
    window.addEventListener('beforeunload', function (event) {
        if ($(form).hasClass('form-dirty') && !leaving) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    var sections = $(form).find('.form-section');
    var links = $('.form-nav a');
    var waiting = false;
    function highlightSection() {
        var current = 0;
        var top = Math.max(150, $('.navbar-fixed-top').outerHeight() + 40);
        sections.each(function (index) {
            if (this.getBoundingClientRect().top <= top) current = index;
        });
        if (window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 2) {
            current = sections.length - 1;
        }
        links.each(function (index) {
            $(this).toggleClass('active', index === current);
            if (index === current) $(this).attr('aria-current', 'location');
            else $(this).removeAttr('aria-current');
        });
        waiting = false;
    }
    $(window).on('scroll resize', function () {
        if (!waiting) {
            waiting = true;
            window.requestAnimationFrame(highlightSection);
        }
    });
    highlightSection();
});

// Hledani nabizi pouze odkazy, ktere uz prihlaseny uzivatel vidi v navigaci.
$(document).ready(function () {
    var form = $('.navbar-search');
    var input = form.find('input[name="search"]');
    var panel = form.find('.search-suggestions');
    var options = $('#search-options');
    if (!input.length || !panel.length) return;
    var index = [];
    var seen = {};
    var active = -1;

    function fold(value) {
        return $.trim(value).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    // area: jmeno oblasti, pod kterou AP v levem panelu stoji; AP se najde i pod zapisem "Oblast - AP"
    function add(link, description, area) {
        var href = link.getAttribute('href');
        var text = $.trim(link.textContent);
        if (!href) return;
        var foldedWithArea = area ? fold(area + ' - ' + text) : '';
        var known = seen[href];
        if (known) {
            // Jedine aktivni AP oblasti stoji v Mych oblastech samo, ve Vsech oblastech pod svou oblasti.
            if (foldedWithArea && !known.foldedWithArea) {
                known.foldedWithArea = foldedWithArea;
                known.description = description;
            }
            return;
        }
        seen[href] = { href: href, text: text, description: description, folded: fold(text), foldedWithArea: foldedWithArea };
        index.push(seen[href]);
    }

    $('.sidebar ul.nav-sidebar > li').each(function () {
        var link = $(this).children('a')[0];
        if (!link) return;
        var children = $(this).find('ul a');
        add(link, children.length ? 'oblast' : 'AP');
        children.each(function () { add(this, 'AP · ' + $.trim(link.textContent), $.trim(link.textContent)); });
    });
    $('#navbar-tools a').each(function () { add(this, 'nástroj'); });
    input.attr({ role: 'combobox', 'aria-autocomplete': 'list', 'aria-controls': 'search-options', 'aria-expanded': 'false' });

    function close() {
        panel.prop('hidden', true);
        input.attr('aria-expanded', 'false').removeAttr('aria-activedescendant');
        active = -1;
    }

    function render() {
        var needle = fold(input.val());
        options.empty();
        active = -1;
        input.removeAttr('aria-activedescendant');
        form.find('.search-hint').text(needle ? 'Enter vyhledá člena nebo IP adresu.' : 'Zadejte člena nebo IP, oblast, AP či nástroj.');
        if (needle) {
            var starts = [];
            var contains = [];
            index.forEach(function (item) {
                var at = item.folded.indexOf(needle);
                if (at === 0) starts.push(item);
                else if (at > 0 || item.foldedWithArea.indexOf(needle) !== -1) contains.push(item);
            });
            starts.concat(contains).slice(0, 9).forEach(function (item, i) {
                $('<a class="search-option" role="option" tabindex="-1" aria-selected="false"></a>')
                    .attr({ href: item.href, id: 'search-option-' + i })
                    .append($('<span class="search-option-text"></span>').text(item.text))
                    .append($('<span class="search-option-description"></span>').text(item.description))
                    .appendTo(options);
            });
        }
        panel.prop('hidden', false);
        var hasOptions = options.children().length > 0;
        input.attr('aria-expanded', hasOptions ? 'true' : 'false');
        form.find('.search-help').prop('hidden', !hasOptions);
        if (needle && !hasOptions) {
            form.find('.search-hint').text('Oblast, AP ani nástroj nenalezen. Enter vyhledá člena nebo IP adresu.');
        }
    }

    input.on('focus input', render).on('blur', function () {
        // Kliknuti na navrh musi dobehnout pred skrytim nabidky.
        setTimeout(function () { if (!form[0].contains(document.activeElement)) close(); }, 150);
    }).on('keydown', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (panel.prop('hidden')) render();
            var rows = options.children();
            if (!rows.length) return;
            active = active < 0 ? (event.key === 'ArrowDown' ? 0 : rows.length - 1)
                : (active + (event.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length;
            rows.removeClass('active').attr('aria-selected', 'false');
            rows.eq(active).addClass('active').attr('aria-selected', 'true');
            input.attr('aria-activedescendant', rows[active].id);
            rows[active].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && active >= 0 && !panel.prop('hidden')) {
            event.preventDefault();
            window.location.href = options.children().eq(active).attr('href');
        }
    });
    panel.on('mousedown', 'a', function (event) { event.preventDefault(); });
    $(document).on('click', function (event) {
        if (!form[0].contains(event.target)) close();
    }).on('keydown', function (event) {
        if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey
            && !$(event.target).is('input, textarea, select') && !event.target.isContentEditable) {
            event.preventDefault();
            input.focus().select();
        }
    });
});
