function pingIPList(sojkaUrl) {
  var ipsList = [];
  $("tr[id^=highlightable-ip] td a[href^='/userdb/subnet']").each(function() {
    ip = $(this).text();

    if(ip) {
      ip = ip.replace(/ /g, "");
      ipsList.push(ip);
    }
  });

  $.ajax({
    type: "POST",
    url: sojkaUrl,
    data: { ips: ipsList },
    success: pingIPListCallback,
    dataType: "json"
  });
}


function pingIPListCallback(resp) {
  if(resp.length <= 0) {
    return(1);
  }

  $("tr[id^=highlightable-ip] td a[href^='/userdb/subnet']").each(function() {
    ip = $(this).text();

    if(ip) {
      ip = ip.replace(/ /g, "");

      if(ip in resp) {
        ip_result = resp[ip];

        if(ip_result.time_lastpong < 0) {
          setIPPingerResult($(this), "glyphicon-question-sign", "label-default", 'not found');
        } else if(!ip_result.alive) { // if last response was more then 10 minutes ago, IP is dead
          setIPPingerResult($(this), "glyphicon-remove", "label-danger", 'DOWN');
        } else if (ip_result.loss >= 0.8) { // if packetloss is > 80 %, IP is dead
          setIPPingerResult($(this), "glyphicon-remove", "label-danger", 'LOSS ' + (Math.floor(ip_result.loss * 100)) + '%');
        } else if (ip_result.loss >= 0.2) { // if packetloss is > 20 %, IP is warning
          setIPPingerResult($(this), "glyphicon-warning-sign", "label-warning", 'LOSS ' + (Math.floor(ip_result.loss * 100)) + '%');
        } else if (ip_result.rtt >= 0.2) { // if ping is > 200 msec, IP is warning
          setIPPingerResult($(this), "glyphicon-warning-sign", "label-warning", 'PING ' + (Math.floor(ip_result.rtt * 1000)) + ' ms%');
        } else { // IP is fine!
          setIPPingerResult($(this), "glyphicon-ok", "label-success", (Math.floor(ip_result.rtt * 10000)/10) + ' ms, ' + (Math.floor(ip_result.loss * 100)) + '%');
        }
      } else {
        setIPPingerResult($(this), "glyphicon-question-sign", "label-default", 'NOT FOUND');
      }
    }
  });
}

function setIPPingerResult(element, glyphicon, label, text) {
  baseSpan = element.parent().find("span.pinger");

  if(baseSpan.length === 0) {
    baseSpan = element.after('&nbsp;&nbsp;<span class="label pinger"></span>').parent().find("span.pinger");
    baseSpan.append('<span class="glyphicon" aria-hidden="true"></span>');
    baseSpan.append('<span class="pingertext">---</span>');
  } else {
    baseSpan.removeClass(removeLabelClass);
    baseSpan.find(".glyphicon").removeClass(removeGlyphiconClass);
  }

  baseSpan.addClass(label);
  baseSpan.find(".glyphicon").addClass(glyphicon);
  baseSpan.find(".pingertext").text(" " + text);
}

function removeGlyphiconClass(index, className) {
  return (className.match (/(^|\s)glyphicon-\S+/g) || []).join(' ');
}

function removeLabelClass(index, className) {
  return (className.match (/(^|\s)label-\S+/g) || []).join(' ');
}

function drawBar(elem, pings) {
  var colors = ["rgb(150, 255, 150)", "rgb(255, 255, 150)", "rgb(255, 150, 150)"];
  
  var pings_total = pings[0] + pings[1] + pings[2];
  
  if (pings_total == 0) {
    return(0);
  }
  
  var can1 = document.createElement("canvas");
  var size_w = parseFloat(elem.css("width")) + parseFloat(elem.css("padding-right")) + parseFloat(elem.css("padding-left"));
  var size_h = parseFloat(elem.css("height")) + parseFloat(elem.css("padding-top")) + parseFloat(elem.css("padding-bottom"));
  
  can1.width = Math.ceil(size_w);
  can1.height = Math.ceil(size_h);
  
  var ctx1 = can1.getContext('2d');
  
  var position = 0;
  var i;
  for (i=0; i < 3; i++) {
    if(pings[i] == 0) {
    	continue;
    }
  	position_new = Math.floor(pings[i] / pings_total * can1.width);
    
    ctx1.fillStyle = colors[i];
  	ctx1.fillRect(position, 0, position + position_new, can1.height);
    position = position + position_new;
  }
  
  elem.css({
    'background-image': "url(" + can1.toDataURL("image/png") + ")",
    'background-size': "100% auto"
  });
}  

function pingUserListCallback(resp) {
  $(".grid-cell-IPAdresa span").each(function() {
    ips = $( this ).attr('title');
  
    if(ips) {
      ips = ips.replace(/ /g, "").split(",");
      var i;
      var row_result = [0, 0, 0];
      var text_result = "";
      for (i = 0; i < ips.length; i++) {
        if(ips[i] in resp) {
          ip_result = resp[ips[i]];
          
          if(ip_result.time_lastpong < 0) {
            continue;
          } else if(!ip_result.alive) { // if last response was more then 10 minutes ago, IP is dead
            row_result[2]++;
          } else if (ip_result.loss >= 0.8) { // if packetloss is > 80 %, IP is dead
            row_result[2]++;
          } else if (ip_result.loss >= 0.2) { // if packetloss is > 20 %, IP is warning
            row_result[1]++;
          } else if (ip_result.rtt >= 0.2) { // if ping is > 200 msec, IP is warning
            row_result[1]++;
          } else { // IP is fine!
            row_result[0]++;
          }
          
          if(ip_result.alive) {
            text_result = text_result + ips[i] + " | " + (Math.floor(ip_result.rtt*10000)/10) + " ms | " + (Math.floor(ip_result.loss*100)) + " %<br>";
          } else {
            text_result = text_result + ips[i] + " | " + " dead\n";
          }
        } else {
          row_result[2]++;
        }
      }
      drawBar($( this ).parent(), row_result);

      $( this ).parent().tooltip({
        title: text_result,
        html: true,
        placement: "top",
        container: 'body'
      });
    }
  });
}

function pingUserList(sojkaUrl) {  
  var ipsList = [];
  $(".grid-cell-IPAdresa span").each(function() {
    ips = $( this ).attr('title');
  
    if(ips) {
      ips = ips.replace(/ /g, "").split(",");
      ipsList = ipsList.concat(ips);
    }
  });
  
  $.ajax({
    type: "POST",
    url: sojkaUrl,
    data: { ips: ipsList },
    success: pingUserListCallback,
    dataType: "json"
  });
}
