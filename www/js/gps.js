/* Format GPS coordinates according to format ID
 * 0 = 50.12345, 16.98765
 * 1 = N50.12345 E16.98765
 * 2 = N50°12.3456' N50°98.7654'
 * 3 = N50°56'12.345" N50°54'98.765"
 */

function formatGPS(lat, lon, fid) {
  if(fid == 0) {
    return(lat + ", " + lon);
  } else {
    letter_lat = (lat > 0) ? "N" : "S";
    letter_lon = (lon > 0) ? "E" : "W";
    if (fid == 1) {
      return(letter_lat + lat + " " + letter_lon + lon);
    } else if (fid == 2) {
      lat_deg = Math.floor(lat);
      lon_deg = Math.floor(lon);
      lat_rest = Math.round((lat - lat_deg)*60*1e4)/1e4;
      lon_rest = Math.round((lon - lon_deg)*60*1e4)/1e4;
      return(letter_lat + lat_deg + "°" + lat_rest + "' " + letter_lon + lon_deg + "°" + lon_rest + "'");
    } else if (fid == 3) {
      lat_deg = Math.floor(lat);
      lon_deg = Math.floor(lon);
      lat_min = Math.floor((lat - lat_deg)*60);
      lon_min = Math.floor((lon - lon_deg)*60);
      lat_sec = Math.round((lat - lat_deg - lat_min / 60)*3600*1e3)/1e3;
      lon_sec = Math.round((lon - lon_deg - lon_min / 60)*3600*1e3)/1e3;
      return(letter_lat + lat_deg + "°" + lat_min + "'" + lat_sec + "\" " + letter_lon + lon_deg + "°" + lon_min + "'" + lon_sec + "\"");
    }
  }
}

function rotateGPSFormat(gpsSpan) {
  lat = Math.round(gpsSpan.data("latitude")*1e6)/1e6;
  lon = Math.round(gpsSpan.data("longitude")*1e6)/1e6;
  fid = gpsSpan.data("formatid");
  fid++;
  if(fid > 3) {
    fid = 0;
  }

  gpsSpan.text(formatGPS(lat, lon, fid));
  gpsSpan.data("formatid", fid);
}
