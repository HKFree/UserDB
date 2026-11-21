$(document).ready(function () {
    $(".datepicker").change(function () {
        if ($(this).val().indexOf("00:00:00") > -1) $(this).val($(this).val().replace(" 00:00:00", ""));
    });
    $(".datepicker").change();
    $(".datepicker").off("change");

    $(".datepicker").datepicker({ dateFormat: "dd.mm.yy" });

    // na uzkem displeji je seznam oblasti vysouvaci panel (viz .sidebar-open v screen.css)
    var sidebarToggle = $("#navareas");
    function setSidebarOpen(open) {
        $("html").toggleClass("sidebar-open", open);
        sidebarToggle.attr("aria-expanded", open ? "true" : "false");
        if (open) $("#sidebar-filter-input").focus();
    }
    sidebarToggle.click(function () {
        setSidebarOpen(!$("html").hasClass("sidebar-open"));
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

// INICIALIZATOR pro JSTREE
$(document).ready(function () {
    $('#oblastitree').bonsai();
});

// Zvyrazneni prave otevrene oblasti nebo AP v levem panelu a rozbaleni jeji vetve
$(document).ready(function () {
    var tree = $('#oblastitree').data('bonsai');
    $('.sidebar .nav-sidebar a').each(function () {
        if (this.pathname !== window.location.pathname) return;
        var item = $(this).parent().addClass('active');
        var branch = item.parent().closest('li');
        if (tree && branch.length > 0) tree.expand(branch);
    });
});

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
            var areaMatches = fold(area.children('a').text()).indexOf(needle) !== -1;
            var shown = areaMatches;
            area.find('li').each(function () {
                var matches = areaMatches || fold($(this).text()).indexOf(needle) !== -1;
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
    function markWideGrids() {
        $('form.grido').each(function () {
            var table = $(this).children('table')[0];
            $(this).toggleClass('grid-wide', !!table && table.offsetWidth > this.clientWidth + 1);
        });
    }
    markWideGrids();
    $(window).on('resize', markWideGrids);
    $(document).ajaxComplete(function () {
        setTimeout(markWideGrids, 0);
    });

    // nabidka v gridu se kresli pres okraj ramu, jinak by ji posuvnik orizl
    $(document).on('shown.bs.dropdown', '.grido .dropdown', function () {
        var button = $(this).find('.dropdown-toggle')[0].getBoundingClientRect();
        var menu = $(this).find('.dropdown-menu');
        var left = Math.max(8, Math.min(button.left, window.innerWidth - menu.outerWidth() - 8));
        var fitsBelow = button.bottom + menu.outerHeight() <= window.innerHeight;
        menu.css({ position: 'fixed', left: left, right: 'auto', top: fitsBelow ? button.bottom : Math.max(8, button.top - menu.outerHeight()) });
    });
    $(window).on('scroll', function () {
        $('.grido .dropdown.open .dropdown-toggle').dropdown('toggle');
    });
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


$(document).ready(function () {
    $('.user-labels').on('click', '.badge-remove', function (e) {
        e.preventDefault();

        const stitekId = $(this).data('stitek-id');
        const userId = $(this).data('user-id');
        const badge = $(this).closest('.badge');

        // AJAX DELETE požadavek
        $.ajax({
            url: $('body').data('stitky-delete-url'),
            type: 'DELETE',
            contentType: 'application/json',
            data: JSON.stringify({
                stitek_id: stitekId,
                user_id: userId
            }),
            success: function (response) {
                if (response.success) {
                    badge.fadeOut(300, function () {
                        $(this).remove(); // Odstranění badge
                    });
                } else {
                    console.error('Nepodařilo se odstranit štítek: ' + response.message);
                }
            },
            error: function () {
                console.error('Chyba při komunikaci se serverem.');
            }
        });
    });
    $('.stitky-item').on('click', function (e) {
        e.preventDefault(); // Zabráníme reloadu stránky

        const stitekId = $(this).data('id'); // Získáme ID štítku
        const userId = $(this).closest('.dropdown').parent().data('user-id'); // Získáme user_id z dropdown boxu

        const existing = $(`.user-labels-component[data-user-id=${userId}] .user-labels .badge .badge-remove[data-stitek-id=${stitekId}]`);
        if (existing.length > 0) return;

        // Odeslání AJAX požadavku
        $.ajax({
            url: $('body').data('stitky-save-url'),
            type: 'POST',
            data: {
                stitek_id: stitekId,
                user_id: userId
            },
            success: function (response) {
                if (response.success) {
                    // uloženo -> zobrazit novej štítek (stejně jako v UserLabelComponents.latte)
                    const newLabel = `<span class="badge" style="background-color: ${response.barva_pozadi}; color: ${response.barva_popredi};">
                            ${response.text}
                            <span class="badge-remove" data-stitek-id="${stitekId}" data-user-id="${userId}">&times;</span>
                        </span>`;
                    $(newLabel).hide().appendTo(`.user-labels-component[data-user-id=${userId}] .user-labels`).fadeIn(300);
                    console.log('Štítek byl úspěšně uložen!', 'success');
                } else {
                    alert('Došlo k chybě: ' + response.message, 'danger');
                    console.error('Došlo k chybě: ' + response.message, 'danger');
                }
            },
            error: function () {
                alert('Nepodařilo se připojit k serveru.', 'danger');
                console.error('Nepodařilo se připojit k serveru.', 'danger');
            }
        });
    });
});



/** STITKY **/
