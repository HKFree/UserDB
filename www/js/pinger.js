/* Výsledky SojkaPingeru u IP adres: štítky v tabulce IP adres člena nebo AP (pingIPList)
 * a barevný pruh s popiskem v seznamu členů (pingUserList). Funkce volá šablona pinger.latte.
 */

/**
 * Pošle seznam IP adres na sojkaUrl a s odpovědí (JSON podle IP) zavolá callback. Zpracovanou odpověď
 * oznámí atribut data-ping na <body> (answered nebo failed), aby na ni šlo počkat.
 */
function pingerDotaz(sojkaUrl, ipsList, callback) {
  const data = new URLSearchParams();
  ipsList.forEach((ip) => data.append("ips[]", ip));

  fetch(sojkaUrl, {
    method: "POST",
    body: data,
    headers: { "X-Requested-With": "XMLHttpRequest" },
  })
    .then((response) => {
      if (response.ok === false) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.json();
    })
    .then((resp) => {
      callback(resp);
      document.body.dataset.ping = pingFailed(resp) ? "failed" : "answered";
    })
    .catch((error) => {
      document.body.dataset.ping = "failed";
      console.warn("SojkaPinger:", error);
    });
}

/** Služba pingu odpověděla chybou (např. není dostupná): stav adres neznáme, nic se neoznačí. */
function pingFailed(resp) {
  return resp === null || typeof resp !== "object" || resp.error === true;
}

/** Odkazy na IP adresy v tabulce IP adres (detail člena nebo AP) i s adresou bez mezer; s předponou /userdb i bez ní. */
function pingerOdkazyNaIp() {
  return [...document.querySelectorAll("tr[id^=highlightable-ip] td a[href*='/subnet/detail/']")]
    .filter((odkaz) => odkaz.textContent)
    .map((odkaz) => ({ odkaz, ip: odkaz.textContent.replace(/ /g, "") }));
}

/** IP adresy jednoho člena v seznamu členů; seznam nese atribut title buňky, po pingu data-pinger-ips. */
function pingerIpClena(span) {
  const ips = span.dataset.pingerIps ?? span.getAttribute("title");
  return ips ? ips.replace(/ /g, "").split(",") : [];
}

function pingIPList(sojkaUrl) {
  const ipsList = pingerOdkazyNaIp().map(({ ip }) => ip);
  pingerDotaz(sojkaUrl, ipsList, pingIPListCallback);
}

function pingIPListCallback(resp) {
  if (pingFailed(resp) || Object.keys(resp).length === 0) {
    return;
  }

  pingerOdkazyNaIp().forEach(({ odkaz, ip }) => {
    if ((ip in resp) === false) {
      setIPPingerResult(odkaz, "glyphicon-question-sign", "label-default", 'NOT FOUND');
      return;
    }

    const ipResult = resp[ip];
    if (ipResult.time_lastpong < 0) {
      setIPPingerResult(odkaz, "glyphicon-question-sign", "label-default", 'not found');
    } else if (!ipResult.alive) { // if last response was more then 10 minutes ago, IP is dead
      setIPPingerResult(odkaz, "glyphicon-remove", "label-danger", 'DOWN');
    } else if (ipResult.loss >= 0.8) { // if packetloss is > 80 %, IP is dead
      setIPPingerResult(odkaz, "glyphicon-remove", "label-danger", 'LOSS ' + (Math.floor(ipResult.loss * 100)) + '%');
    } else if (ipResult.loss >= 0.2) { // if packetloss is > 20 %, IP is warning
      setIPPingerResult(odkaz, "glyphicon-warning-sign", "label-warning", 'LOSS ' + (Math.floor(ipResult.loss * 100)) + '%');
    } else if (ipResult.rtt >= 0.2) { // if ping is > 200 msec, IP is warning
      setIPPingerResult(odkaz, "glyphicon-warning-sign", "label-warning", 'PING ' + (Math.floor(ipResult.rtt * 1000)) + ' ms%');
    } else { // IP is fine!
      setIPPingerResult(odkaz, "glyphicon-ok", "label-success", (Math.floor(ipResult.rtt * 10000) / 10) + ' ms, ' + (Math.floor(ipResult.loss * 100)) + '%');
    }
  });
}

function setIPPingerResult(odkaz, glyphicon, label, text) {
  // Na detailu člena drží adresa s tlačítkem pro zkopírování pohromadě (.ip-copy), štítek stojí až za nimi
  // a odsazení mu dává screen.css, aby se mohl zalomit pod adresu.
  const adresa = odkaz.closest(".ip-copy") ?? odkaz;
  let stitek = adresa.parentElement.querySelector("span.pinger");

  if (stitek === null) {
    adresa.insertAdjacentHTML(
      "afterend",
      (adresa === odkaz ? "&nbsp;&nbsp;" : "")
        + '<span class="label pinger"><span class="glyphicon" aria-hidden="true"></span><span class="pingertext">---</span></span>',
    );
    stitek = adresa.parentElement.querySelector("span.pinger");
  } else {
    odeberTridySPredponou(stitek, "label-");
    odeberTridySPredponou(stitek.querySelector(".glyphicon"), "glyphicon-");
  }

  stitek.classList.add(label);
  stitek.querySelector(".glyphicon").classList.add(glyphicon);
  stitek.querySelector(".pingertext").textContent = " " + text;
}

function odeberTridySPredponou(prvek, predpona) {
  [...prvek.classList]
    .filter((trida) => trida.startsWith(predpona))
    .forEach((trida) => prvek.classList.remove(trida));
}

// Tlumené barvy (dostupná, ztráty, nedostupná) jsou proměnné ze screen.css, takže se po přepnutí vzhledu
// přebarví samy; proužek u levého okraje podle nejhoršího stavu dává screen.css podle třídy buňky.
function drawBar(elem, pings) {
  const colors = ["var(--ping-ok)", "var(--ping-warn)", "var(--ping-down)"];

  const pingsTotal = pings[0] + pings[1] + pings[2];

  if (pingsTotal === 0) {
    return;
  }

  elem.classList.remove("ping-ok", "ping-warn", "ping-down");
  elem.classList.add(pings[2] > 0 ? "ping-down" : (pings[1] > 0 ? "ping-warn" : "ping-ok"));

  // Stav i slovem, ať nestojí jen na barvě (čtečka, nerozlišování barev).
  const words = [];
  if (pings[0] > 0) words.push(`${pings[0]} dostupná`);
  if (pings[1] > 0) words.push(`${pings[1]} ztráty`);
  if (pings[2] > 0) words.push(`${pings[2]} nedostupná`);
  let status = elem.querySelector(":scope > .ping-status");
  if (status === null) {
    status = document.createElement("span");
    status.className = "sr-only ping-status";
    elem.append(status);
  }
  status.textContent = `Ping: ${words.join(", ")}`;

  // Podíl adres v každém stavu jako pruhy vedle sebe.
  const stops = [];
  let position = 0;
  for (let i = 0; i < 3; i++) {
    if (pings[i] === 0) {
      continue;
    }
    const next = position + pings[i] / pingsTotal * 100;
    stops.push(`${colors[i]} ${position}% ${next}%`);
    position = next;
  }
  elem.style.backgroundImage = `linear-gradient(to right, ${stops.join(", ")})`;
}

function pingUserListCallback(resp) {
  if (pingFailed(resp)) {
    return;
  }
  document.querySelectorAll(".grid-cell-IPAdresa span").forEach((span) => {
    const ips = pingerIpClena(span);

    if (ips.length === 0) {
      return;
    }

    const rowResult = [0, 0, 0];
    const textResult = [];
    ips.forEach((ip) => {
      if ((ip in resp) === false) {
        rowResult[2]++;
        return;
      }

      const ipResult = resp[ip];
      if (ipResult.time_lastpong < 0) {
        return;
      } else if (!ipResult.alive) { // if last response was more then 10 minutes ago, IP is dead
        rowResult[2]++;
      } else if (ipResult.loss >= 0.8) { // if packetloss is > 80 %, IP is dead
        rowResult[2]++;
      } else if (ipResult.loss >= 0.2) { // if packetloss is > 20 %, IP is warning
        rowResult[1]++;
      } else if (ipResult.rtt >= 0.2) { // if ping is > 200 msec, IP is warning
        rowResult[1]++;
      } else { // IP is fine!
        rowResult[0]++;
      }

      if (ipResult.alive) {
        textResult.push(ip + " | " + (Math.floor(ipResult.rtt * 10000) / 10) + " ms | " + (Math.floor(ipResult.loss * 100)) + " %");
      } else {
        textResult.push(ip + " | " + " dead");
      }
    });

    const bunka = span.parentElement;
    drawBar(bunka, rowResult);

    // Výsledek ukáže nápověda buňky (main.js ji kreslí do <body>, posuvný rám seznamu ji neořízne).
    // Seznam IP se z popisku prvku přesune do data-pinger-ips, jinak by nad ním prohlížeč ukázal seznam IP.
    if (textResult.length > 0) {
      span.dataset.pingerIps = ips.join(",");
      span.removeAttribute("title");
      bunka.dataset.toggle = "tooltip";
      bunka.dataset.tip = textResult.join("\n");
    }
  });
}

function pingUserList(sojkaUrl) {
  const ipsList = [...document.querySelectorAll(".grid-cell-IPAdresa span")].flatMap(pingerIpClena);
  pingerDotaz(sojkaUrl, ipsList, pingUserListCallback);
}
