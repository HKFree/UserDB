/* Format GPS coordinates according to format ID
 * 0 = 50.12345, 16.98765
 * 1 = N50.12345 E16.98765
 * 2 = N50°12.3456' N50°98.7654'
 * 3 = N50°56'12.345" N50°54'98.765"
 */

function formatGPS(lat, lon, fid) {
  if (fid === 0) {
    return `${lat}, ${lon}`;
  }

  const letterLat = (lat > 0) ? "N" : "S";
  const letterLon = (lon > 0) ? "E" : "W";
  const latDeg = Math.floor(lat);
  const lonDeg = Math.floor(lon);

  if (fid === 1) {
    return `${letterLat}${lat} ${letterLon}${lon}`;
  }
  if (fid === 2) {
    const latRest = Math.round((lat - latDeg) * 60 * 1e4) / 1e4;
    const lonRest = Math.round((lon - lonDeg) * 60 * 1e4) / 1e4;
    return `${letterLat}${latDeg}°${latRest}' ${letterLon}${lonDeg}°${lonRest}'`;
  }
  if (fid === 3) {
    const latMin = Math.floor((lat - latDeg) * 60);
    const lonMin = Math.floor((lon - lonDeg) * 60);
    const latSec = Math.round((lat - latDeg - latMin / 60) * 3600 * 1e3) / 1e3;
    const lonSec = Math.round((lon - lonDeg - lonMin / 60) * 3600 * 1e3) / 1e3;
    return `${letterLat}${latDeg}°${latMin}'${latSec}" ${letterLon}${lonDeg}°${lonMin}'${lonSec}"`;
  }
}

/** Přepne prvek s atributy data-latitude, data-longitude a data-formatid na další formát. */
function rotateGPSFormat(gpsSpan) {
  const lat = Math.round(Number(gpsSpan.dataset.latitude) * 1e6) / 1e6;
  const lon = Math.round(Number(gpsSpan.dataset.longitude) * 1e6) / 1e6;
  let fid = Number(gpsSpan.dataset.formatid) + 1;
  if (fid > 3) {
    fid = 0;
  }

  gpsSpan.textContent = formatGPS(lat, lon, fid);
  gpsSpan.dataset.formatid = String(fid);
}
