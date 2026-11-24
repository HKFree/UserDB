/*
 * Grafy počtu aktivních členů po měsících (Správa → Graf počtu uživatelů).
 * Měsíce s počty a českými názvy připraví server v atributu data-mesice obalu grafů. Každý graf v atributu
 * data-serie říká, které způsoby aktivace kreslí, v pořadí legendy shora dolů. Barvy a rozměry os určuje screen.css.
 */
$(function () {
    'use strict';

    var obal = document.querySelector('.graf-clenu-grafy');
    if (!obal) return;

    var NAZVY = { automatem: 'Automatem', rucne: 'Ručně', castecne: 'Částečně' };
    /** Kolik měsíců může být mezi popisky osy x, od nejhustšího. */
    var KROKY_OSY_X = [1, 2, 3, 6, 12, 24, 60, 120];
    var NEJMENSI_ROZESTUP_POPISKU_X = 70;
    /** Krok osy y je 1, 2 nebo 5 krát mocnina deseti; stejně ho volí d3, takže čáry mřížky sedí na hodnotách. */
    var NASOBKY_KROKU_Y = [1, 2, 5];
    var NEJMENSI_ROZESTUP_CAR_Y = 55;
    var NEJVICE_CAR_Y = 5;
    var ODSTUP_BUBLINY = 10;

    var mesice = JSON.parse(obal.getAttribute('data-mesice'));
    var podleCasu = {};
    mesice.forEach(function (mesic) { podleCasu[mesic.x] = mesic; });

    function cislo(hodnota) {
        return String(hodnota).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
    }

    /** Popisky osy x: měsíce nebo roky tak daleko od sebe, aby se nepřekrývaly. */
    function osaX(sirkaPlochy) {
        var nejvicePopisku = Math.max(1, Math.floor(sirkaPlochy / NEJMENSI_ROZESTUP_POPISKU_X));
        var krok = KROKY_OSY_X.filter(function (k) { return mesice.length / k <= nejvicePopisku; })[0] || KROKY_OSY_X[KROKY_OSY_X.length - 1];
        return {
            hodnoty: mesice.filter(function (mesic) {
                var datum = new Date(mesic.x * 1000);
                return (datum.getUTCFullYear() * 12 + datum.getUTCMonth()) % krok === 0;
            }).map(function (mesic) { return mesic.x; }),
            popisek: function (x) {
                var datum = new Date(x * 1000);
                return krok >= 12 ? String(datum.getUTCFullYear()) : (datum.getUTCMonth() + 1) + '/' + datum.getUTCFullYear();
            }
        };
    }

    /** Největší součet kreslených způsobů; graf samých nul má aspoň jedničku, aby měl osu. */
    function nejvyssiSoucet(serie) {
        var nejvic = 1;
        mesice.forEach(function (mesic, i) {
            var soucet = 0;
            serie.forEach(function (s) { soucet += s.data[i].y; });
            nejvic = Math.max(nejvic, soucet);
        });
        return nejvic;
    }

    /** Horní mez osy y leží na čáře mřížky: nejmenší násobek kroku 1, 2 nebo 5 × 10^n nad maximem. */
    function meritkoY(nejvic, vyskaPlochy) {
        var nejviceCar = Math.min(NEJVICE_CAR_Y, Math.max(2, Math.floor(vyskaPlochy / NEJMENSI_ROZESTUP_CAR_Y)));
        for (var rad = 1; rad <= 1e9; rad *= 10) {
            for (var i = 0; i < NASOBKY_KROKU_Y.length; i++) {
                var krok = NASOBKY_KROKU_Y[i] * rad;
                var car = Math.ceil(nejvic / krok);
                if (car <= nejviceCar) return { max: car * krok, car: car };
            }
        }
        return { max: nejvic, car: nejviceCar };
    }

    function pridejRadek(rodic, trida, text) {
        var radek = document.createElement('div');
        radek.className = trida;
        radek.appendChild(document.createTextNode(text));
        rodic.appendChild(radek);
        return radek;
    }

    function pridejHodnotu(rodic, trida, klic, nazev, hodnota) {
        var popisek = pridejRadek(rodic, trida, nazev);
        if (klic) {
            var vzorek = document.createElement('span');
            vzorek.className = 'graf-clenu-barva graf-clenu-barva-' + klic;
            popisek.insertBefore(vzorek, popisek.firstChild);
        }
        pridejRadek(rodic, 'graf-clenu-bublina-cislo ' + trida, cislo(hodnota));
    }

    /** Bublinu dá vpravo od svislé čáry měsíce, když se tam vejde, jinak vlevo; nikdy nepřesáhne okraj grafu. */
    function umistiBublinu(bublina, x, sirkaGrafu) {
        var sirka = bublina.offsetWidth;
        var posun = x + ODSTUP_BUBLINY + sirka <= sirkaGrafu ? ODSTUP_BUBLINY : -ODSTUP_BUBLINY - sirka;
        bublina.style.left = Math.max(-x, Math.min(posun, sirkaGrafu - x - sirka)) + 'px';
    }

    /** Bublina se všemi způsoby aktivace a součtem měsíce, nad kterým je myš nebo prst. */
    var Detail = Rickshaw.Class.create(Rickshaw.Graph.HoverDetail, {
        render: function (args) {
            var mesic = podleCasu[args.domainX];
            if (!mesic) return;
            var bublina = document.createElement('div');
            bublina.className = 'graf-clenu-bublina';
            pridejRadek(bublina, 'graf-clenu-bublina-mesic', mesic.nazev);
            var hodnoty = pridejRadek(bublina, 'graf-clenu-bublina-hodnoty', '');
            Object.keys(NAZVY).forEach(function (klic) {
                pridejHodnotu(hodnoty, '', klic, NAZVY[klic], mesic[klic]);
            });
            pridejHodnotu(hodnoty, 'graf-clenu-bublina-celkem', null, 'Celkem', mesic.celkem);
            this.element.appendChild(bublina);
            this.show();
            umistiBublinu(bublina, this.graph.x(args.domainX), this.graph.width);
        }
    });

    function nakresli(element) {
        var plocha = element.querySelector('.graf-clenu-plocha');
        var osaY = element.querySelector('.graf-clenu-osa-y');
        var poradiLegendy = element.getAttribute('data-serie').split(' ');
        var nizky = poradiLegendy.indexOf('automatem') === -1;
        // Rickshaw skládá první sérii dospod: malé vrstvy dole, horní hrana grafu je pak celkový počet.
        var serie = poradiLegendy.slice().reverse().map(function (klic) {
            return {
                name: NAZVY[klic],
                className: 'graf-clenu-barva-' + klic,
                color: 'currentColor',
                stroke: 'currentColor',
                data: mesice.map(function (mesic) { return { x: mesic.x, y: mesic[klic] }; })
            };
        });
        var nejvic = nejvyssiSoucet(serie);

        function sirka() {
            return Math.max(plocha.clientWidth, 100);
        }

        function vyska(sirkaPlochy) {
            return nizky
                ? Math.round(Math.min(200, Math.max(150, sirkaPlochy * 0.22)))
                : Math.round(Math.min(360, Math.max(220, sirkaPlochy * 0.38)));
        }

        var sirkaPlochy = sirka();
        var vyskaPlochy = vyska(sirkaPlochy);
        var popiskyX = osaX(sirkaPlochy);
        var meritko = meritkoY(nejvic, vyskaPlochy);
        var graf = new Rickshaw.Graph({
            element: plocha,
            renderer: 'area',
            stroke: true,
            strokeWidth: 2,
            interpolation: 'linear',
            width: sirkaPlochy,
            height: vyskaPlochy,
            max: meritko.max,
            series: serie
        });
        var osaYGrafu = new Rickshaw.Graph.Axis.Y({
            graph: graf,
            element: osaY,
            orientation: 'left',
            width: osaY.offsetWidth,
            height: vyskaPlochy,
            ticks: meritko.car,
            tickFormat: function (y) { return y === Math.round(y) ? cislo(y) : ''; }
        });
        var detail = new Detail({ graph: graf });

        // Po každém vykreslení: mřížku osy y pod plochy (osa ji kreslí navrch) a popisky osy x. Přibalené
        // d3 2.5 neumí na ose zvolit hodnoty popisků, osu x proto kreslí graf sám.
        function doplnOsy() {
            var mrizkaY = plocha.querySelector('.y_grid');
            if (mrizkaY) mrizkaY.parentNode.insertBefore(mrizkaY, mrizkaY.parentNode.firstChild);
            var osa = element.querySelector('.graf-clenu-osa-x');
            osa.innerHTML = '';
            popiskyX.hodnoty.forEach(function (x) {
                var popisek = document.createElement('span');
                popisek.className = 'graf-clenu-popisek-x';
                popisek.style.left = graf.x(x) + 'px';
                popisek.textContent = popiskyX.popisek(x);
                osa.appendChild(popisek);
            });
        }
        graf.onUpdate(doplnOsy);

        // Na dotykové obrazovce ukáže bublinu klepnutí; myš ji ukazuje už při pohybu, kliknutí ji jen obnoví.
        // Klepnutí prstem prohlížeč často přisoudí sousednímu prvku, proto platí kdekoliv v grafu včetně os.
        // Poloha zůstane uvnitř plochy: za posledním bodem by Rickshaw vybral předposlední měsíc.
        element.addEventListener('click', function (udalost) {
            var svg = plocha.querySelector('svg');
            var okraj = svg.getBoundingClientRect();
            var x = Math.min(Math.max(udalost.clientX - okraj.left, 0), graf.width - 1);
            detail.visible = true;
            detail.update({ target: svg, offsetX: x, offsetY: udalost.clientY - okraj.top });
        });

        function prekresli() {
            var novaSirka = sirka();
            if (novaSirka === graf.width) return;
            var novaVyska = vyska(novaSirka);
            popiskyX = osaX(novaSirka);
            meritko = meritkoY(nejvic, novaVyska);
            osaYGrafu.staticTicks = meritko.car;
            graf.configure({ width: novaSirka, height: novaVyska, max: meritko.max });
            osaYGrafu.setSize({ width: osaY.offsetWidth, height: novaVyska });
            graf.render();
        }

        if (window.ResizeObserver) {
            new ResizeObserver(prekresli).observe(element);
        } else {
            $(window).on('resize', prekresli);
        }
        graf.render();
    }

    Array.prototype.forEach.call(obal.querySelectorAll('.graf-clenu[data-serie]'), nakresli);
});
