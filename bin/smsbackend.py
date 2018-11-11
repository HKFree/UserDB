#!/usr/bin/env python
# -*- coding: utf-8 -*-
#
# AWEG stands for AnnyWay Enterprise SMS Gateway, the main end-user oriented SMS connectivity solution by MATERNA Communication a.s.
# Smsbackend is used as common back-end executable program for easy integration of AWEG OpenInterface to the   b-SMS, e-SMS, or other 3rd party applications.
#
# Authors:
# 	Emanuel Petr <epetr@fincom.cz>			2005-2006
# 	Lucie Leistnerova <lucie.leistnerova@fincom.cz>	2007
#	Vojtech Pithart <vojtech.pithart@maternacz.com>	2008+
# (c) MATERNA Communications 2005-2008
#
# $Id: smsbackend.py 4630 2013-09-12 10:31:36Z vojta $
#

# Version strings: * Please increment version string after major updates
#                  * Revision number is incremented by SVN on each commit
#                  * Date is updated by SVN on each commit
version_num= "3.11"
revision= "$Revision: 4630 $"[11:][:-2]					# [11:][:-2] removes all except the number (NNNNN)
date= "$Date: 2013-09-12 12:31:36 +0200 (Čt, 12 zář 2013) $"[7:][:10]		# [7:][:10] removes all except date (YYYY-MM-DD)

# Displayed 1) after calling "smsbackend -v"
#           2) in windows front-end in help/about dialog
version= "AWEG-smsbackend version " + version_num + " revision " + revision + " (" + date + ")"

# Transferred in HTTP and logged on server:
version_short= "v" + version_num + "/r" + revision + "/" + date

import time
import sys
import re
import urllib
import urllib2
from urllib2 import Request, urlopen, URLError
import signal, os
import socket
from optparse import OptionParser
#import DNS      	# Used in dns_resolve()
import base64
import httplib
import encodings, encodings.idna, encodings.ascii, encodings.punycode
#import pprint				# pro Dumpovani promennych
#pp = pprint.PrettyPrinter(indent=4)	# pro Dumpovani promennych
import random

sent_sms = 0
rest_sms = 0
we_flag = 0 # warning, error status
returned_status_text = "" #status_text at STATUS line
body_parts = 0
#bulk_id = 0
# for progress bar, sms counting
sms_id = 0
# stop handler
sig_stop_generic_handler_flag = 0
# proxy
proxy_user = ""
proxy_password = ""
proxy_ip = ""
proxy_port = ""
proxy_default_port = "3128"
proxy_use = 0
# delivery reports
messages_count = 0
# rsa encryption
#public_key = "mykey.pub"

# For -M parameter: 4kb spaces + \n + 4 kb spaces
big_space = ((4*1024) * " " ) + "\n" + ((4*1024) * " " ) + "\n"


# Timeout for HTTP request (sending AO-MT messages)
http_timeout = 15
# maximum retry count (on HTTP error)
max_retry_count = 25
# retry pause (seconds) incremented by:
retry_pause = 2


retry_counter = 0
# ports
default_ports = { 'http' : 80, 'https' : 443}
default_host_port = 443
host_port = default_host_port
http_conn = None
version_sent = 0
config = ""


class ConnectionError(Exception):
   def __init__(self, value):
      self.value = value
   def __str__(self):
      return repr(self.value)

def read_lines(ser):
   lines = ser.readlines()
   ser.flushInput
   ser.flushOutput
   if options.verbose:   print "read_lines: ", lines
   return lines

def read_line(ser):
   line = ser.readline()
   if options.verbose:   print "read_line (%d): %s" % ( len(line), line )
   lines = [line, ""] #make list
   if len(line): status_parser(lines)
   return len(line)
   #vojta#if len(line): status_parser(line)
   #vojta# if len(line): status_parser(line)
   #vojta#return line   
   
def status_action(status,status_text):
   global rest_sms
   global we_flag
   global returned_status_text
   global config
   #status codes are print without -q
   if options.verbose: print "status action", (status, status_text)
   # 3xx code?
   if status[0] == "3" and status != "315":
      if not (config == ""): 
         local_text = "CONFIG:%s\n" % (config)
      else:
         local_text = ""
      local_text = local_text + "STATUS:%d:%d:2: %s" % (sent_sms, rest_sms, status_text)
      leave(local_text)
   #if status == "315":
   #   we_flag = 1
   #   returned_status_text = status_text
   if status == "200":
      we_flag = 0
      returned_status_text = "OK "
   if status == "201" or status == "202":
      we_flag = 1
      returned_status_text = "OK "
   if status == "315":
      we_flag = 1
      returned_status_text = status_text
   if status == "102":
      pr = re.compile('^\s*:(\d+)')
      mr = pr.match(status_text)
      if mr:
         if options.verbose:   print "rest:", mr.group(1)
         rest_sms = int( mr.group(1) )
   if status == "103":
      pr = re.compile('^\s*:(.*)')
      mr = pr.match(status_text)
      if mr:
         if options.verbose:   print "config:", mr.group(1)
         config += mr.group(1) + ";"

def status_parser(lines,checkfor=""):
   ret_status = 0 #return value for status_parser ... is modified later ... 1 if checkfor was found at status lines
   global body_parts 
   global sent_sms
   global msg_ids
   #global bulk_id

   # null values
   msg_ids = []
   #bulk_id = 0
   
   p = re.compile('^\s*(\d{3})\s*(.*)', re.IGNORECASE)
   # parsing for how many sms was sent, their msg_id and bulk_id
   # 200 [2] bodypart, accepted as [0002433e,0002433x] bulk_id [12345]
   # p_bp = re.compile('^.*\[(\d+)\]\s*bodypart.*\[(.*)\].*\[(.*)\]', re.IGNORECASE) # s bulk_id
   p_bp = re.compile('^.*\[(\d+)\]\s*bodypart.*\[(.*)\].*', re.IGNORECASE)

   if options.verbose:   print "status_parser (%d): \"%s\"" % ( len(lines), lines )
   for i in range( len(lines) ):
      #match for NO CARRIER
      if not (config == ""): 
         local_text = "CONFIG:%s\n" % (config)
      else:
         local_text = ""
      if lines[i].find("NO CARRIER") != -1 :
         local_text = local_text + "STATUS:%d:%d:2: NO CARRIER" % (sent_sms, rest_sms)
         leave(local_text)
      #match for DELAYED 
      if lines[i].find("DELAYED") != -1 :
         local_text = local_text + "STATUS:%d:%d:2: DELAYED" % (sent_sms, rest_sms)
         leave(local_text)
      #match for NO DIALTONE
      if lines[i].find("NO DIALTONE") != -1 :
         local_text = local_text + "STATUS:%d:%d:2: NO DIALTONE" % (sent_sms, rest_sms)
         leave(local_text)
      #match for status numbers
      m = p.match( lines[i] )
      if m:
         status = m.group(1)
         status_text = m.group(2)
         status_action(status,status_text)
         if status.find(checkfor) != -1 :   ret_status = 1
      #match for bodyparts   #200 [2] bodyparts
      #lines_test = "200 [3] bodypart, accepted as [0002433e 0002433x] bulk_id [12345]" # s bulk_id   
      #lines_test = "200 [2] bodypart, accepted as [0002433e 0002433x]"   
      #m_bp = p_bp.match( lines_test )
      m_bp = p_bp.match( lines[i] )
      if m_bp:
         body_parts = int( m_bp.group(1) )
         msg_ids = m_bp.group(2)
         #bulk_id = m_bp.group(3) # s bulk_id
         if options.verbose:   print "body parts:|%d| sent_sms{%d}" % (body_parts,sent_sms)
         if body_parts >= 2:   sent_sms += body_parts - 1 #increase sent_sms count
   return ret_status


def parse_destination():
   #destination_list = options.destination.split(",")
   temp_list = options.destination.split(",")
   pr = re.compile('^(.*\d.*\d.*\d.*)') #3 digits anyware
   destination_list = []
   
   for destination in temp_list:
      if options.verbose:
         print "parse_destination: ", destination
      mr = pr.match(destination) #pr na cely radek
      if mr:
         if options.verbose:   print 'Match found: ', mr.group()
         destination_list.append( mr.group(1) )
   return destination_list


def generate_random_number():
   #from random import randint
   random_number = time.strftime( "%d%H%M%S", time.localtime() )
   random_number = "%s%02d" %  (random_number, random.randint(0,99) )
   if options.verbose:   print "random number ", random_number
   return random_number

def get_protocol(url):
   # get protocol of the url - HTTP, HTTPS
   pr = re.compile('^([^\/]*):\/\/(.*)') 
   mr = pr.match(url)
   if mr:
	   return mr.group(1)
   else:
		 return 'https'

def get_domain(url):
   # get domain of the url - www.something.com
   pr = re.compile('^(.*):\/\/([^\/]*)\/(.*)') 
   mr = pr.match(url)
   if mr:
	   return mr.group(2)
   else:
		 return url

def get_rest(url):
   # get rest of the url after http://domain/
   pr = re.compile('^[^\/]*:\/\/[^\/]*\/(.*)')
   mr = pr.match(url)
   if mr:
      return mr.group(1)
   else:
      return ''

def dns_resolve(url):
   #http://server:port/uri 
   #http://aweg.fincom.cz
   
   # automatically load nameserver(s) from /etc/resolv.conf
   # (works on unix - on others, YMMV)
   global retry_counter
   try:
      DNS.ParseResolvConf()
   except:
      if options.verbose:   print "We are probably on windows system, no /etc/resolv.conf found"
      #return unchanged or https://* (have not --allow-http)
      pr = re.compile('.*:\/\/(.*)') #rip the name only
      mr = pr.match(url)
      if get_protocol(url) != 'https' and not options.allow_non_ssl:
         return "https://" + mr.group(1)
      else:
         return url

   while (1):
      try:
         pr = re.compile('(.*):\/\/([\d+\.]+\/?i.*)') #is name or IP, if match it is iP
         mr = pr.match(url)
         if mr:
            # it is ip, return unchanged
            # set https://* in case --allow-non-ssl is not used
            if get_protocol(url) != 'https' and not options.allow_non_ssl:
   	          return "https://" + mr.group(2)
            else:
   	          return url
      
         # it is name, we need IP, so do DNS querry
         pr = re.compile('(.*)(:\/\/)([^\/]+)(\/?.*)') #rip the name only
         mr = pr.match(url)
         if mr:
            query_name = mr.group(3)
            if options.verbose:   print "DNS request for: ", query_name
              # it is name, so translate to IP
            r = DNS.DnsRequest(name=query_name, qtype='A', timeout=30)
            # do the request
            a=r.req()
            answer = a.answers
            if options.verbose:   print answer
            # set https://* in case --allow-non-ssl is not used
            if get_protocol(url) != 'https' and not options.allow_non_ssl:
               return "https" + mr.group(2) + answer[0]['data'] + mr.group(4)
            else:
   	          return mr.group(1) + mr.group(2) + answer[0]['data'] + mr.group(4)
         else:
            #return unchanged or https://* (have not --allow-non-ssl)
            pr = re.compile('.*:\/\/(.*)') #rip the name only
            mr = pr.match(url)
            if get_protocol(url) != 'https' and not options.allow_non_ssl:
               return "https://" + mr.group(1)
            else:
               return url
      except:
         if options.verbose:   print "dns_resolve failed!"
         if retry_counter < max_retry_count:
            retry_counter+=1
            new_retry_pause = retry_counter * retry_pause
            if options.spaces:
               spaces = big_space
            else:
               spaces = ""
            print "INFO: Can't do dns resolve. Retry after %d seconds (%d/%d)%s" % ( new_retry_pause, retry_counter, max_retry_count, spaces )
            time.sleep( new_retry_pause )
            continue
         else:
            # after max_retry_count, we can't do dns resolve, so stop processing and quit program
            leave("STATUS:0::2: Can't send URL request. Timeout reached.")
   retry_counter = 0


def process_http(csv_lines = ""):
   #only for http, bulk identification
   #if only one sms, send bulk=random_number_end else bulk=random_number, and last sms with bulk=random_number_end
   global bulk_number
   global messages_count
   global config
   global http_conn
   global last_http_error
   
   bulk_number = generate_random_number()
   sms_iter = 0
   t_last_send = 0
   
   # HTTP address of AWEG server
   ip_name = options.addr
         
   # Create HTTP connection object - Proxy mode   
   if options.proxy_string:	# http://[<username>[:<password>]@]<hostname>[:port]   
      p_proxy = re.compile('^\w*://(.*)@(.*)$', re.IGNORECASE)
      m_proxy = p_proxy.match( options.proxy_string )
      if m_proxy:
         parse_proxy_user_password( m_proxy.group(1) )
         parse_proxy_ip_port( m_proxy.group(2) )
      else:
         p_proxy = re.compile('^\w*://(.*)$', re.IGNORECASE)
         m_proxy = p_proxy.match( options.proxy_string )
         if m_proxy:
            parse_proxy_ip_port( m_proxy.group(1) )
         else:
            if not (config == ""): 
               local_text = "CONFIG:%s\n" % (config)
            else:
               local_text = ""
            leave(local_text + "STATUS:0::2: Proxy mismatch parametr.")
      
      if options.verbose:   print "Using proxy: proxy_ip=%s, proxy_port=%s, proxy_user=%s, proxy_password=%s" % (proxy_ip, proxy_port, proxy_user, proxy_password)
      
      # set parameters for proxy_connect
      pr = re.compile('^(\w*)://(.*)')
      mr = pr.match(options.addr)
      if mr:
         if options.allow_non_ssl:
            host_port = default_ports[mr.group(1)]
         else:
            host_port = default_host_port
         proxy_host = get_domain(options.addr)
      else:
         host_port = default_host_port
         proxy_host = options.addr
      # set proxy connection
      while (not http_conn):
         try:
            http_conn = proxy_connect(proxy_ip, proxy_user, proxy_password, proxy_host, host_port, int(proxy_port))
         except ConnectionError, c_err:
            # retry routine, max_retry_count , retry_pause
            if retry_counter < max_retry_count:
               retry_counter+=1
               new_retry_pause = retry_counter * retry_pause
               if options.spaces:
                  spaces = big_space
               else:
                  spaces = ""
               print "INFO: %s. Retry after %d seconds (%d/%d)%s" % ( c_err.value, new_retry_pause, retry_counter, max_retry_count, spaces )
               time.sleep( new_retry_pause )
            else:
               # after max_retry_count, we can't connect, so stop processing and quit program
               if not (config == ""): 
                  local_text = "CONFIG:%s\n" % (config)
               else:
                  local_text = ""
               local_text = local_text + "STATUS:0:0:2: error - %s" % c_err.value
               leave(local_text)
      retry_counter = 0
   # Create HTTP connection object - direct non-proxy mode
   else:   	
      if ( options.addr_is_https ):
         http_conn = httplib.HTTPSConnection(get_domain(options.addr))
      else:
         http_conn = httplib.HTTPConnection(get_domain(options.addr))

   # Sending messages from CSV file
   if csv_lines:
      if options.verbose: print "process_http: csv_lines mode"
      # get count of lines/sms (it means last sms no)
      last_sms_no = len(csv_lines) #bulk
      last_speedfile_read= 0
      
      #for destination,text in csv_lines.items():
      destination_list = csv_lines.keys()
      destination_list.sort()
      # for each destination number from csv
      for destination in destination_list:
         text = csv_lines[destination]
         config = ""										# the only last "103:" is converted to "CONFIG:" output
         destination = destination[8:]	# remove id from destination number
         sms_iter+=1 #bulk
         if last_sms_no == sms_iter:   bulk_number = str(bulk_number) + "end" #bulk

         if options.verbose:   print "Sending text \"%s\" to destination %s" % (text, destination)
         
         # Speed adjustment (--speed, --speedfile)
         if t_last_send > 0 :
            t_one_request = time.time() - t_last_send

            if options.send_speed_file:
               if time.time() > last_speedfile_read + 2.000 :
                  last_speedfile_read= time.time()
                  new_speed= 0
                  try:
                    sf=open(options.send_speed_file, 'r')
                    sf_content = sf.readline()
                    sf.close()
                  except:
                    sf_content= ""
                  if options.verbose: print "SpeedFile read: %s" % (sf_content)
                  
                  try:
                     options.send_speed= float(sf_content);
                  except:
                     options.send_speed= 0
                     
            if options.send_speed > 0 :
               now_delay= 1/options.send_speed
               adjusted_delay= now_delay-t_one_request
               if adjusted_delay<0 : adjusted_delay= 0
               if options.verbose: print "Target speed %.1f sms/sec, Delay %.3fs" % (options.send_speed, adjusted_delay)
               time.sleep( adjusted_delay )
         t_last_send = time.time()
         
         send_result= send_http(destination, text, bulk_number, ip_name)
         
         if send_result:
            if not (config == ""): 
	           local_text = "CONFIG:%s\n" % (config)
            else:
	           local_text = ""
            local_text = local_text + "STATUS:%d:%d:%d: %s" % (sent_sms, rest_sms, we_flag, returned_status_text)
         else:
            #some problem,we dont receive 20x code   
            if not (config == ""): 
               local_text = "CONFIG:%s\n" % (config)
            else:
               local_text = ""
            local_text = local_text + "STATUS:%d:%d:2: %s " % (sent_sms, rest_sms, last_http_error)
            leave(local_text)

      # exit with local_text message set above
      leave(local_text)
   # Sending messages from command line (one text, multiple destination from -d)
   else:
      if options.verbose: print "process_http: destination_list mode"
      # multidestination receivers are parsed and created destination_list structure
      destination_list = parse_destination() #parse options.destination parameter
      # get sms count for frontedns progress bar
      messages_count = messages_count + ( 1 * len(destination_list) ) # how many receivers
      print "\nMESSAGES:%d\n" % (messages_count)

      if options.verbose:   print "Parse destination list: %s\nMessages count: %s\n" % (destination_list, messages_count)
      
      # get count of receivers from destination_list (it means last sms no)
      last_sms_no = len(destination_list)

      for destination in destination_list:
         sms_iter+=1 #bulk 
         if last_sms_no == sms_iter:   bulk_number = str(bulk_number) + "end" #bulk 

         if options.verbose:
            print "Sending \"%s\" to %s" % ( smstext, destination )

         last_http_error= "--placeholder--"
         if send_http(destination, smstext, bulk_number, ip_name):
            #send ok + print status line
            if not (config == ""): 
               local_text = "CONFIG:%s\n" % (config)
            else:
               local_text = ""
            local_text = local_text + "STATUS:%d:%d:%d: %s" % (sent_sms, rest_sms, we_flag, returned_status_text)
         else:
            #some problem,we dont receive 20x code   
            if not (config == ""): 
               local_text = "CONFIG:%s\n" % (config)
            else:
               local_text = ""
            local_text = local_text + "STATUS:%d:%d:2: %s " % (sent_sms, rest_sms, last_http_error)
            leave(local_text)
      # exit with local_text message set above
      leave(local_text)

   
def send_http(destination, message, bulk_number, ip_name):
   """ send HTTP request to specified URL """

   global big_space
   global sent_sms
   global sms_id
   global retry_counter
   global proxy_ip
   global proxy_port
   global proxy_host
   global proxy_user
   global proxy_password
   global host_port
   global version_sent
   global config
   global http_conn
   global last_http_error
   
   #encode message
   login = options.login
   message = urllib.quote(message)
   login = urllib.quote_plus(login)
   destination_unqoted = destination
   destination = urllib.quote_plus(destination)

   #create url_string
   while 1: # infinite loop
      try:
         url_params = "auth=" + login + "&receiver=" + destination + "&bulk=" + bulk_number + "&smstext=" + message
      except:
         leave("STATUS:0::2: Missing URL parameters")
      if options.report_request:
         url_params += "&report=1"
      if options.use_anumber:
         url_params += "&use_anumber=1"
      if options.use_alphanum:
         url_params += "&use_alphanum=1"
      if options.use_cstoascii:
         url_params += "&cstoascii=1"
      if options.template_id:
         url_params += "&tid="+options.template_id
      if not version_sent:
        version_sent = 1
        be_version_escaped = urllib.quote_plus(version_short)
        fe_version_escaped = urllib.quote_plus(options.frontend_name)
        url_params = url_params + "&be=" + be_version_escaped + "&fe=" + fe_version_escaped
      
      #make url_string
      url_string = ip_name + "?" + url_params
      url_string_proxy = "/" + get_rest(options.addr) + "?" + url_params
   
      if options.verbose:
         print "url_string (exact): ", url_string
         print "url_string_proxy (exact): ", url_string_proxy
   
      # send it and read ouput
      try:
         if options.proxy_string: # is proxy connection
            # make http request
            if options.verbose: 
               http_conn.set_debuglevel(3)
               print "------ sending request"
            http_conn.putrequest('GET', url_string_proxy)
            http_conn.endheaders()
            if options.verbose: print "------ getting response"
            response = http_conn.getresponse()
            data = response.read()
            if options.verbose: print data
         else:
#         elif (get_protocol(url_string) == 'https') or (not options.allow_non_ssl): # is https connection
            # make https connection
            if options.verbose:
            	print " http_conn.putrequest: ", url_string_proxy
            http_conn.putrequest('GET', url_string_proxy)
            http_conn.endheaders()
            response = http_conn.getresponse()
            data = response.read()
# VojtaP 15.5.2008: Nechapu proc je zde ruzny postup pro HTTP a HTTPS. Kdo se pak ma vyznat v exceptions?
#         else: 
#	    # make http connection
#            if options.verbose:
#            	print " url_GET http: ", url_string
#            http_request = urllib2.Request( url_string )
#            socket.setdefaulttimeout( http_timeout ) # in seconds
#            opener = urllib2.build_opener()
#            urllib2.install_opener(opener)
#            # make http request
#            pagehandle = urllib2.urlopen(http_request)
#            data = pagehandle.read()
      except Exception, inst:			 
         #print("%s: %s"%(repr(inst), str(dir(inst))))		 
	 last_http_error= "Ex[%s]" % ( inst )
         if hasattr(inst, 'reason'):
		#print 'We failed to reach a server.'
        	#print 'Reason: ', inst.reason
		last_http_error= "Failed to reach server: %s" % ( inst.reason )
    	 elif hasattr(inst, 'code'):
        	#print 'The server couldn\'t fulfill the request.'
        	#print 'Error code: ', inst.code
		last_http_error= "Server couldn\'t fulfill the request: HTTP %d" % ( inst.code )
    	 elif hasattr(inst, 'args'):
        	#print 'Message: ', inst.args
		last_http_error= "Exception message: %s" % ( str(inst.args) )
	 last_http_error= str(inst)
	 if options.verbose: 
            print "Exception:",
            print inst
         if sig_stop_generic_handler_flag:
            leave("") # we return nothing because it is handled with sig_stop_generic_handler
         else:
            # retry routine, max_retry_count , retry_pause
            if retry_counter < max_retry_count:
               retry_counter+=1
               new_retry_pause = retry_counter * retry_pause
               if options.spaces:
                  spaces = big_space
               else:
                  spaces = ""
               print  "INFO: HTTP request failed. Retry after %d seconds (%d/%d)%s" % ( new_retry_pause, retry_counter, max_retry_count, spaces ) +":",
	       print inst
	       if options.proxy_string:
	          print "INFO: Try to connect again to proxy %s:%s." % (proxy_ip, proxy_port)
		  http_conn.close()
		  try:
                     http_conn = proxy_connect(proxy_ip, proxy_user, proxy_password, proxy_host, host_port, int(proxy_port))
		  except:
		     pass
	       if get_protocol(ip_name) == 'https' or (not options.allow_non_ssl):
	          http_conn.close()
                  if ( options.addr_is_https ):
                     http_conn = httplib.HTTPSConnection(get_domain(options.addr))
                  else:
                     http_conn = httplib.HTTPConnection(get_domain(options.addr))
               
	       time.sleep( new_retry_pause )
               version_sent = 0
               continue
            else:
               # after max_retry_count, we can't send request, so stop processing and quit program
               if not (config == ""): 
                  local_text = "CONFIG:%s\n" % (config)
               else:
                  local_text = ""
               local_text = local_text + "STATUS:%d:%d:2: HTTP request failed [%s]" % (sent_sms, rest_sms, last_http_error)
               leave(local_text)
      break # no exception, so we can continue to process response
   # end while
   # null retry_counter
   retry_counter = 0

   if options.verbose:   print "Response from server: ", data
   data = data.split("\n")
   
   # check HTTP status (200 OK or 404 not found etc.)
   if ( response.status != 200 ):
      last_http_error= "Server HTTP response: " + str(response.status) + " " + response.reason + " (" + ip_name + ")"
      
      # print to stdout (only id if sent failed)
      print "ID:%s %s" % (sms_id, last_http_error)
      return 0
      
   # check for 200-209 code (it not present => msg was not sent)
   if status_parser(data,"20"):
      if options.verbose:   print "message sent"
      sent_sms+=1
      # print to stdout for delivery report processing by frontends
      # if ok ... idzpravy,bulkid,timestamp,receiver
      # if no ... idzpravy
      timestamp = time.strftime( "%Y%m%d%H%M%S", time.localtime() )
      # msg_ids is content of [], msg_ids was set from status_parser procedure, so we split them
      if not (len(msg_ids) == 0):
         if options.spaces:
            spaces = big_space
         else:
            spaces = ""
         # incrementsms_id
         sms_id+=1
         # message_id;submit_timestamp;receiver\n
         # prepare string for print to stdout
         delivery_line_stdout = "ID:%s,%s,%s,%s,%s,%s%s" % (sms_id, msg_ids.replace(' ','/'), bulk_number.replace('end',''), timestamp, destination_unqoted, message, spaces)
         # print to stdout
         print delivery_line_stdout

      return 1
   # sms not sent (No matching route found), but continue in processing
   elif status_parser(data,"315"):
      sms_id+=1
      # print to stdout (only id if sent failed)
      print "ID:%s %s" % (sms_id, last_http_error)
      return 1
   else:
      # In case of receiving 200 OK, but unparseable HTML document
      # if no ... idzpravy
      
      # incrementsms_id
      sms_id+=1
      
      if ( len(data[0]) ):
         last_http_error= "Invalid response from server (" + ip_name + "): " + data[0][:-1]
      if ( len(data[1]) ):
	 last_http_error= last_http_error + "..."
      else:
         last_http_error= "Empty response from server (" + ip_name + ")"

      # print to stdout (only id if sent failed)
      print "ID:%s %s" % (sms_id, last_http_error)
      return 0


# when lines[1] is \n or \r or \r\n, we substite for lines[2]
def drop_unuse(lines): 
   pr = re.compile('^\\r\\n|\\r|\\n$',re.IGNORECASE)
   mr = pr.match(lines[1])
   if mr: 
      lines[1] = lines[2]
   # remove \r\n or \r \n ( min_line_size is then exact )
   lines[1] = chomp( lines[1] )
   if options.verbose:   print "lines: ", lines
   return lines


def delivery_reports():
   """
   -R <last_timestamp>[,bulk_id]
   $url is HTTP submit URL (-a)
   $auth is login information (-l)
   $lastts is last timestamp (-R before ,)
   $bulkid is bulk_id (-R after ,)
   Construct URL this way: "$url/report?auth=$auth&since=$lastts&bulkid"

   Normally, smsbackend returns the http body as-is, with no modifications.
   REPORT:<message_id>,<bulk_id>,<status>,<timestamp_end>
   REPORT:<message_id>,<bulk_id>,<status>,<timestamp_end>
   REPORT:<message_id>,<bulk_id>,<status>,<timestamp_end>
   ...
   <status> 0 - not-delivered
            1 - delivered
   """
   global config
   global options
   
   # Create HTTP connection object
   if ( options.addr_is_https ):
      http_conn = httplib.HTTPSConnection(get_domain(options.addr))
   else:
      http_conn = httplib.HTTPConnection(get_domain(options.addr))


   #parse delivery_reports string
   temp_array = options.delivery_reports.split(",")
   try:
      last_timestamp = temp_array[0]
      bulk_id = temp_array[1]
   except:
      bulk_id = ''
   print bulk_id

   login = options.login
   #encode message
   last_timestamp = urllib.quote_plus(last_timestamp)
   bulk_id = urllib.quote_plus(bulk_id)
   login = urllib.quote_plus(login)
      
   # translate name to ip with timeout, we use for this special DNS module
   #ip_name = dns_resolve(options.addr)
   ip_name= options.addr
   
   #create url_string
   if options.verbose: print "\ndelivery reports url_string (pre): %s, auth: %s, last_timestamp: %s, bulk_id: %s" % (ip_name, login, last_timestamp, bulk_id)

   try:
      #$url/report?auth=$auth&since=$lastts&bulkid
      url_string = ip_name + "/report?auth=" + login + "&since=" + last_timestamp + "&bulkid=" + bulk_id
      url_string_proxy = "/" + (get_rest(options.addr)) + "/report?auth=" + login + "&since=" + last_timestamp + "&bulkid=" + bulk_id
   except:
      leave("STATUS:0::2: Missing URL parameters")

   if options.verbose: 
      print "url_string (exact): ", url_string
      print "url_string_proxy (exact): ", url_string_proxy

   # send it and read ouput
   try:
      #use proxy setting if defined as parametr
      if options.proxy_string:
         # make http request
         print "http_conn.request " + url_string_proxy
         http_conn.request('GET', url_string_proxy)
         data = http_conn.getresponse().read()
      else:
#      elif get_protocol(url_string) == 'https':
         # set timeout
         if ( options.verbose ):
	    print "http_conn.putrequest " + url_string_proxy
         http_conn.putrequest('GET', url_string_proxy)
         http_conn.endheaders()
         response = http_conn.getresponse()
         data = response.read()

#      else: # is http
#         print "urllib2 " + url_string
#         http_request = urllib2.Request( url_string )
#         socket.setdefaulttimeout( http_timeout ) # in seconds
#         opener = urllib2.build_opener()
#         urllib2.install_opener(opener)
#         # make http request
#         pagehandle = urllib2.urlopen(http_request)
#         #read the output from previous http request
#         data = pagehandle.read()
   except:
      if sig_stop_generic_handler_flag:
         leave("") # we return nothing because it is handled with sig_stop_generic_handler
      else:
         if not (config == ""): 
            local_text = "CONFIG:%s\n" % (config)
         else:
            local_text = ""
         leave(local_text + "STATUS:0::2: Can't send URL request")

   # print output from server
   if options.verbose:   print "Response from server: ", data
   if options.spaces:
      spaces = big_space
   else:
      spaces = ""   
   
   # check HTTP status (200 OK or 404 not found etc.)
   if ( response.status != 200 ):
      print "STATUS:0:0:2: Server HTTP response: " + str(response.status) + " " + response.reason + " (" + ip_name + ")"
      return 0
   
   data = data.split("\n")
   for data_line in data:
      print data_line + spaces
#      print "\n" vpithart: data_line already contains \n from server
   print "END"


def parse_csvfile():
   """
   open file, parse lines and valid lines are saved to list "csv_lines"
   computes number of bodyparts (from length(s) of text
   save final count of sms to "messages_count" variable, which is printed at startup for frontends progress bar
   """
   global csv_lines
   global messages_count
   global config
   csv_lines = dict()
   try:
      f=open(options.csvfilename, 'r')
      csv_lines_mass = f.readlines()
      f.close()
   except:
      if options.verbose:   print "Cant open file %s for reading" % options.csvfilename
      if not (config == ""): 
         local_text = "CONFIG:%s\n" % (config)
      else:
         local_text = ""
      local_text = local_text + "STATUS:0::2: Can't open file \"%s\" for reading" % options.csvfilename
      leave(local_text)

   local_iter = 500000 # start at 500 000, because we need fix length of number
   #check for valid rows, 3 digits ? reason is PSMS
   pr = re.compile('^([^;]*\d[^;]*\d[^;]*\d[^;]*);(.+)') #3 digits anywhere ; text
   for line in csv_lines_mass:
      if options.verbose:   print "l:", line
      mr = pr.match(line) #pr na cely radek

      #get number and text form line
      if mr:
         if options.verbose:   print 'Match found: ', mr.groups()
         key = "id" + str(local_iter) + str( mr.group(1) ) # id5xxxxxx it is used for sorting by keys (we need to process cvs at the same order)
         csv_lines[ key ] = mr.group(2)
         local_iter+=1
         # get bodyparts
         messages_count = messages_count + 1
      if local_iter == 1000000: # 5000 000 + 5000 000 :)
         if not (config == ""): 
            local_text = "CONFIG:%s\n" % (config)
         else:
            local_text = ""
         local_text = local_text + "STATUS:0::2: CSV file \"%s\"too big (it contatins more than 500 000 lines). Please split it." % (options.csvfilename)
         leave(local_text)
         #return csv_lines #500 000 sms via csv file is limit
   return csv_lines


def parse_proxy_ip_port(proxy_string):
   global proxy_ip, proxy_port
   p_proxy = re.compile('^(.*):(.*)$', re.IGNORECASE)
   m_proxy = p_proxy.match( proxy_string )
   if m_proxy:
      proxy_ip = m_proxy.group(1)
      proxy_port = m_proxy.group(2)
   else:
      p_proxy = re.compile('^(.*)$', re.IGNORECASE)
      m_proxy = p_proxy.match( proxy_string )
      if m_proxy:
         proxy_ip = m_proxy.group(1)
         proxy_port = proxy_default_port


def parse_proxy_user_password(user_password):
   global proxy_user, proxy_password
   p_proxy = re.compile('^(.*):(.*)$', re.IGNORECASE)
   m_proxy = p_proxy.match( user_password )
   if m_proxy:
      proxy_user = m_proxy.group(1)
      proxy_password = m_proxy.group(2)

def proxy_connect(p_ip, p_user, p_pass, p_host, p_host_port, p_port=proxy_default_port):
   '''
   connect to proxy with or without authorization
   p_ip - proxy ip
   p_port - proxy port, default proxy_default_port
   p_user - proxy login (can be empty string)
   p_pass - proxy password (can be empty string)
   p_host - host ip or name to what should the proxy connect to
   p_host_port - host port to what should the proxy connect to
   '''
   global proxy_use

   base64string = base64.encodestring('%s:%s' % (p_user, p_pass))[:-1]
   authheader =  "Basic %s" % base64string
   #setup basic authentication
   if p_user and p_pass:
      proxy_authorization = 'Proxy-authorization: ' + authheader + '\r\n'
   else:
      proxy_authorization = ''
   proxy_conn = 'CONNECT %s:%s HTTP/1.0\r\n' % (p_host, p_host_port)
   user_agent = 'User-Agent: python\r\n'
   proxy_pieces = proxy_conn + proxy_authorization + user_agent + '\r\n'
  
   #now connect, very simple recv and error checking
   proxy = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
#   proxy.settimeout(http_timeout)
#   proxy.setdefaulttimeout( http_timeout ) # in seconds
   try:
      proxy.connect((p_ip, p_port))
      proxy.sendall(proxy_pieces)
      response = proxy.recv(8192)
      if options.verbose:
         print "Response from proxy:"
         print response
      status = response.split()[1]
   except:
      status = 0
   if status != str(200):  
      raise ConnectionError('Could not connect to proxy, status: ' + str(status)) 
   else:
      if p_host_port == 443:
         #trivial setup for ssl socket
         ssl = socket.ssl(proxy, None, None)
         sock = httplib.FakeSocket(proxy, ssl)
         #initalize httplib and replace with your socket
         connection = httplib.HTTPConnection('localhost')
         connection.sock = sock
      else:
         connection = httplib.HTTPConnection('localhost')
         connection.sock = proxy
      proxy_use = 1
      if options.verbose:   print "connected to proxy: %s(%s)" % (p_ip, p_port) 
      return connection
   
def process_http_longtime( ack ):
   global config
   
   # HTTP address of AWEG server
   ip_name = options.addr
   
   # Unless disabled by --allow-non-ssl, force HTTPS even if HTTP is given in '-a'
   if not options.allow_non_ssl:
	pr = re.compile('.*:\/\/(.*)') # strip 'proto://' out
	mr = pr.match(ip_name)
	if mr:
		ip_name= "https://" + mr.group(1)   

    # This is for --spaces option
   if options.spaces:
      spaces = big_space
   else:
      spaces = ""
   
   # Create HTTP connection object - Proxy mode   
   if options.proxy_string:	# http://[<username>[:<password>]@]<hostname>[:port]   
      p_proxy = re.compile('^\w*://(.*)@(.*)$', re.IGNORECASE)
      m_proxy = p_proxy.match( options.proxy_string )
      if m_proxy:
         parse_proxy_user_password( m_proxy.group(1) )
         parse_proxy_ip_port( m_proxy.group(2) )
      else:
         p_proxy = re.compile('^\w*://(.*)$', re.IGNORECASE)
         m_proxy = p_proxy.match( options.proxy_string )
         if m_proxy:
            parse_proxy_ip_port( m_proxy.group(1) )
         else:
            leave( time.strftime("%H:%M:%S ") + "STATUS:0::2: Proxy mismatch parametr.")
      
      if options.verbose:   print "Debug: Using proxy: proxy_ip=%s, proxy_port=%s, proxy_user=%s, proxy_password=%s" % (proxy_ip, proxy_port, proxy_user, proxy_password)
      
      # set parameters for proxy_connect
      pr = re.compile('^(\w*)://(.*)')
      mr = pr.match(options.addr)
      if mr:
         if options.allow_non_ssl:
            host_port = default_ports[mr.group(1)]
         else:
            host_port = default_host_port
         proxy_host = get_domain(options.addr)
      else:
         host_port = default_host_port
         proxy_host = options.addr
      # set proxy connection
      while (not http_conn):
         try:
            http_conn = proxy_connect(proxy_ip, proxy_user, proxy_password, proxy_host, host_port, int(proxy_port))
         except ConnectionError, c_err:
            # retry routine, max_retry_count , retry_pause
            if retry_counter < max_retry_count:
               retry_counter+=1
               new_retry_pause = retry_counter * retry_pause
               if options.spaces:
                  spaces = big_space
               else:
                  spaces = ""
               print "Debug: %s. Retry after %d seconds (%d/%d)%s" % ( c_err.value, new_retry_pause, retry_counter, max_retry_count, spaces )
               time.sleep( new_retry_pause )
            else:
               # after max_retry_count, we can't connect, so stop processing and quit program
               local_text = time.strftime("%H:%M:%S ") + "STATUS:0:0:2: error - %s" % c_err.value
               leave(local_text)
      retry_counter = 0
   # Create HTTP connection object - direct non-proxy mode
   else:   	
      http_conn = httplib.HTTPSConnection(get_domain(options.addr))

   request_sleep_first= 0;	# The first request should return CONF: and set intervals
   
   request_sleep= 290;          # Default for request sleep time (interval A) - it is overriden by server by "CONF:" push at runtime
   sleep_interval= 5;		# Default for Sleep interval B - it is overriden by server by "CONF:" push at runtime
   sleep_interval_error= 30;	# Default for sleep interval C - it is overriden by server by "CONF:" push at runtime
   connected= 0;
   connected_msg= ""
   server_error_count_in_row= 0
   
   print time.strftime("%H:%M:%S ") + "TRYING:" + ip_name + spaces;
   
   first_connect_time= time.strftime( "%Y%m%d%H%M%S", time.localtime() )

   # Infinite loop that makes "smsbackend -G" to not exit (polling)
   # Loop that run only once (if sending ack)
   while 1:
      # (a) create URL arguments
      url_params = "auth=" + options.login
      # For the first time, do 'fast' get
      if ( request_sleep_first == 0 ):
         request_sleep= 0
	 request_sleep_first= -1;
	 
      if ack:
         url_params = url_params + "&limit=0&sleep=0&ack=" + str(ack)
      else:
         url_params = url_params + "&limit=1000&sleep=" + str(request_sleep)
      
      be_version_escaped = urllib.quote_plus(version_short)
      fe_version_escaped = urllib.quote_plus(options.frontend_name)
      url_params = url_params + "&online_since=" + first_connect_time
      url_params = url_params + "&be=" + be_version_escaped + "&fe=" + fe_version_escaped
      
      url_string = ip_name +  "/longtime?" + url_params
      url_string_proxy = "/" + get_rest(options.addr) + "/longtime?" + url_params
      
      if (options.verbose):
         print "INFO: intervals: A=" + str(request_sleep) + " B=" + str(sleep_interval) + " C="+ str(sleep_interval_error) + " url: [" + url_string +"]"
      
      # (b) send the request and read ouput
      try:
         if options.proxy_string: # is proxy connection
            if options.verbose:
               print "url_string_proxy (exact): ", url_string_proxy
            # make http request
            if options.verbose: 
               http_conn.set_debuglevel(3)
               print "------ sending request"
            http_conn.putrequest('GET', url_string_proxy)
            http_conn.endheaders()
            if options.verbose: print "------ getting response"
            response = http_conn.getresponse()
            data = response.read()
            if options.verbose: print data
         else: 
	    # make http connection
            http_request = urllib2.Request( url_string )
            #socket.setdefaulttimeout( int(request_sleep)+30 ) # in seconds
            opener = urllib2.build_opener()
            urllib2.install_opener(opener)
            # make http request
            pagehandle = urllib2.urlopen(http_request)
            data = pagehandle.read()
      except Exception, inst:			 
         #print("%s: %s"%(repr(inst), str(dir(inst))))		 
	 #last_http_error= "Ex[%s]" % ( inst )
         #if hasattr(inst, 'reason'):
		##print 'We failed to reach a server.'
        	##print 'Reason: ', inst.reason
		#last_http_error= "Failed to reach server: %s" % ( inst.reason )
    	 #elif hasattr(inst, 'code'):
        	##print 'The server couldn\'t fulfill the request.'
        	##print 'Error code: ', inst.code
		#last_http_error= "Server couldn\'t fulfill the request: HTTP %d" % ( inst.code )
    	 #elif hasattr(inst, 'args'):
        	##print 'Message: ', inst.args
		#last_http_error= "Exception message: %s" % ( str(inst.args) )
	 last_http_error= str(inst)
	 if options.verbose: 
            print "Exception:",
            print inst
         if sig_stop_generic_handler_flag:
            leave("") # we return nothing because it is handled with sig_stop_generic_handler
	
	 # Pokud je na AWEG serveru HTTP error (napr. restart apache), zazdime to zmerem k uzivateli
	 # (maximalne 5x za sebou - pak uz s tim musime ven)
	 server_error_count_in_row= server_error_count_in_row + 1
	    
	 if server_error_count_in_row <= 5:
	    if options.verbose:
	       print "Sleep: interval 10s (hardcoded) after failed HTTP request ["+last_http_error+"] (server restart?) #" + str(server_error_count_in_row)
	    time.sleep(10)	# Sleep interval 10 seconds on connection error (first 5 attempts)
	 else:
	    connected= 0
	    print time.strftime("%H:%M:%S ") + "CONNECTED?:"+ str(connected) +":" + last_http_error + spaces
	    if options.verbose:
	       print "Sleep: interval C (" + str(sleep_interval_error) +"s) after failed HTTP request"
            time.sleep(sleep_interval_error)	# Sleep interval C - after failed HTTP request (6th+ attempt)
         
         request_sleep= 0			# Next request after failure will be "fast"
         
	 continue
      
      # (c) Now, HTTP request is successfully finished:
      connected= 1
      server_error_count_in_row= 0
      
      # (c2) parse HTTP result - first line
      if options.verbose:   print "Response from server: [" + data + "]"
      data = data.split("\n")
      
      result_200ok= 0
      first_line= data[0]					# First line should contain numberic result from server
      p = re.compile('^\s*(\d{3})\s*(.*)', re.IGNORECASE)	# Regex for "200 OK" or "305 error message"
      m = p.match( first_line )
      if m:
         status = m.group(1)					# 200 or 304 or 305 etc.
         status_text = m.group(2)				# any message after the number
         if ( status == "200" ):
            result_200ok= 1
      
      if ( result_200ok ):
         connected= 1
	 connected_msg= ""
      else:
         connected= 0
	 connected_msg= first_line
	 
      # (c3) parse HTTP result - second line (may contain CONF:U_anumber)
      if ( data[1][:15] == "CONF:U_anumber=" ):			# CONF:U_anumber=420495412012
         connected_msg= data[1][5:]				# U_anumber=420495412012

      if ( not ack ):
         print time.strftime("%H:%M:%S ") + "CONNECTED:"+ str(connected) +":" + connected_msg + spaces
      
      # (c4) parse HTTP result - other lines
      for line in data:
	 if line == "":
            continue
	 if ( line[:2] == "SM" ):
	    print time.strftime("%H:%M:%S ") + line + spaces
	 if ( line[:6] == "REPORT" ):
	    print time.strftime("%H:%M:%S ") + line + spaces
	 if ( line[:4] == "CONF" ):
	    #				CONF:something=foo bar
            dvojtecka= line.find(":") 	# --^         ^
            rovnitko= line.find("=")	# ------------+
	    conf_variable= line[dvojtecka+1:rovnitko]
	    conf_value= line[rovnitko+1:]
	    #print " Conf [" + conf_variable + "] [" + conf_value + "]"
	    
            # Known Config-push values:
	    if ( conf_variable == "intervalA" ):		# "sleep" argument for next request
	       request_sleep= int(conf_value);
	    if ( conf_variable == "intervalB" ):		# Sleep interval beetween HTTP requests (after success)
	       sleep_interval= int(conf_value);
	       if ( sleep_interval < 1 ): sleep_interval=1      	# Safety
	    if ( conf_variable == "intervalC" ):		# Sleep interval beetween HTTP requests (after failed request)
	       sleep_interval_error= int(conf_value);
	 if ( line[:4] == "INFO" ):
	    print time.strftime("%H:%M:%S ") + line + spaces
      
      if ( ack ):
         break
      
      # Sleep interval C - after failed HTTP request
      # (even if HTTP-level request was ok, but response body does not contain "200 OK", we consider request as failure)
      if ( not result_200ok ):
         if ( options.verbose ):
            print "Sleep: interval C (" + str(sleep_interval_error) +"s) after failed HTTP request"
         time.sleep(sleep_interval_error)
	 continue
	 
      # Sleep interval B - after successfull HTTP request		 		 
      if ( options.verbose ):
	 print "Sleep: interval B (" + str(sleep_interval) + "s) after successfull HTTP request"
      time.sleep(sleep_interval)
   # end while 1      


def leave(text):
   print text
   sys.exit(1)


#def encrypt(text):
#   """
#   Import a public key, and encrypt some data
#   return ecrypted base64 encoded text
#   """
#   global public_key
#   
#   if options.verbose:   print "ecrypting this string: ", text
#   # Create a key object
#   k = ezPyCrypto.key()
#   
#   # Read in a public key
#   try:
#      fd = open(public_key, "rb")
#      pubkey = fd.read()
#      fd.close()
#   except:
#      print "Can't open public key: ", public_key
#   
#   # import this public key
#   k.importKey(pubkey)
#   
#   # Now encrypt some text against this public key
#   enc = k.encStringToAscii(text)
#   
#   # we muset remove those strings to make enc usefull
#   #del <StartPycryptoMessage>
#   p = re.compile('<StartPycryptoMessage>')
#   enc = p.sub( '', enc)
#
#   #del <EndPycryptoMessage>
#   p = re.compile('<EndPycryptoMessage>')
#   enc = p.sub( '', enc)
#
#   #del \n
#   p = re.compile('\n')
#   enc = p.sub( '', enc)
#
#   if options.verbose: print "Encrypt output:\n\n%s\n\n" % enc
#
#   return enc


def chomp(s):
   if s[-2:] == '\r\n':
      return s[:-2]
   if s[-1:] == '\r' or s[-1:] == '\n':
      return s[:-1]
   return s


def sig_stop_generic_handler(signum, frame):
   global config
   if options.verbose:   print 'Signal handler called with signal', signum
   global sig_stop_generic_handler_flag
   sig_stop_generic_handler_flag = 1
   if not (config == ""): 
      local_text = "CONFIG:%s\n" % (config)
   else:
      local_text = ""
   local_text = local_text + "STATUS:%d:%d:2:Program terminated (signal %d received)." % (sent_sms, rest_sms, signum)
   leave(local_text)


def main():
   if options.verbose:   print "\nmain_procedure"
   if options.verbose:   print options
   if options.verbose:   print sys.argv

if __name__=='__main__':
   #parse options & arguments
   usage = "usage: %prog [options] \"message text up to 800 characters\""
   parser = OptionParser(usage=usage)
   parser.add_option("-v", "--version", action="store_true", dest="version", default=False, help = "print program version")
   parser.add_option("-V", "--verbose", action="store_true", dest="verbose", default=False, help="Verbose debugging output")
   parser.add_option("-a", "--addr", dest="addr", help="URL of AWEG server (example: https://aweg.maternacz.com/)", metavar=" <server URL>")
   parser.add_option("-l", "--login", dest="login", help="for -p http, username and password", metavar=" <login:password>")
   parser.add_option("-d", "--destination", dest="destination", help="destination number(s), separated by comma (no space!) e.g. 123456789,00420223344559", metavar=" <destination>")
   parser.add_option("-f", "--file", dest="csvfilename", help="File with messages(s) to read in form:          #comment\t\t\t\t\t\t\tdestination1;text1\t\t\t\t\t\t\t\t\tdestinaton2;text2", metavar=" <filename>")
   parser.add_option("-x", "--hex", action="store_true", dest="hexformat", default=False, help = "use this parameter if smstext is given at hexadecimal format")
   parser.add_option("-u", "--uri", action="store_true", dest="uriformat", default=False, help = "use this parameter if smstext is given at URI escaped format")
   parser.add_option("-c", "--cstoascii", action="store_true", dest="use_cstoascii", default=False, help = "remove Czech accents from the message (filter through cstoascii)")
   parser.add_option("-P", "--proxy", dest="proxy_string", default=False, help = "URL for http proxy: http://[<username>[:<password>]@]<hostname>[:port]", metavar=" <proxy_string>")
   parser.add_option("-M", "--spaces", action="store_true", dest="spaces", default=False, help = "Add 4kB of spaces to end of every STDOUT line")
   parser.add_option("-D", "--report_request", action="store_true", dest="report_request", default=False, help = "Delivery report(s) are requested")
   parser.add_option("-R", "--delivery_reports", dest="delivery_reports", default=False, help = "Print delivery reports.", metavar=" <last_timestamp>[,bulk_id]")
   parser.add_option("-N", "--frontend_name", dest="frontend_name", default="-", help = "Frontend name and version, transferred via HTTP and logged on server.", metavar=" <name,version>")
   parser.add_option("-A", "--use-anumber", action="store_true", dest="use_anumber", default=False, help = "Use customer's A-number as sender, if provisioned on AWEG server. -A has precedence over -L.")
   parser.add_option("-L", "--use-alphanum", action="store_true", dest="use_alphanum", default=False, help = "Use customer's Alphanumeric string as sender, if provisioned on AWEG server.")
   parser.add_option("-G", "--get-messages", action="store_true", dest="get_messages", default=False, help = "Receiving MO SMS via HTTP. Messages or reports received in -G mode are returned as STDOUT, one per line.")
   parser.add_option("--Ga", "--get-ack", dest="get_ack", default=False, help = "List of unique ID of messages (M) or delivery reports (R) previously received by client; such a message is considered as delivered (to client) and deleted by AWEG server. Comma-separated list of 10-digit snippets (M: or R: and 8-digit hexadecimal numbers). Maximum 256 IDs at a time. Prefix M: or R: can be omitted in second and subsequent snippets; in such a case, the same prefix as in previuos snippet is assumed.", metavar = "<M:id1[,M:id2,R:id3,...]>" )
   parser.add_option("--allow-non-ssl", action="store_true", dest="allow_non_ssl", default=False, help = "SMSbackend will use HTTP protocol with no SSL encryption (if it is given in -a parameter) instead of HTTPS. As a default, HTTPS is forced even if http:// is given in -a parameter.")
   parser.add_option("-t", "--template", dest="template_id", default=False, help = "Template ID. For internal use (Profil SMS)", metavar="<template>")
   parser.add_option("-S", "--speed", dest="send_speed", default=0, type="float", help = "Speed of sending bulk of messages (SMS/sec), 0.1 - 100.0", metavar="<number>")
   parser.add_option("--Sf", "--speedfile", dest="send_speed_file", default="", help = "File for setting speed bulk of messages (exmaple: /tmp/speedfile.tmp). Overrices -S.", metavar="<filename>")

   (options, args) = parser.parse_args()
      
   # print version info
   if options.version:
      print version
      sys.exit(1)

   # -l --login is mandatory option in any case
   if len(args) != 1 and not options.login:
      msg= "Required option (--login) is missing"
      print "STATUS:0::2: " + msg + "\n"
      parser.error(msg + ". Please use -h for help.")
   
   # -a --addr is mandatory option in any case
   if len(args) != 1 and not options.addr:
      msg= "Required option (--addr) is missing"
      print "STATUS:0::2: " + msg + "\n"
      parser.error(msg + ". Please use -h for help.")
   
   # if no smstext is given and no csv or version or delivery_reports is requested, it means, that required opt are missing
   if len(args) != 1 and not options.csvfilename and not options.delivery_reports and not options.get_messages and not options.get_ack:
      #print "STATUS:0::2: No text specified.\n"
      #parser.error("No text specified!")
      print "STATUS:0::2: Required option is missing.\n"
      parser.error("Required option is missing")
      
   # we parse smstext only if no version or delivery_reports is requested
   if not options.csvfilename and not options.delivery_reports and not options.get_messages and not options.get_ack:
      global smstext
      smstext = args[0]
      #print "smstext: ", smstext


   # signal handling
   signal.signal( signal.SIGTERM, sig_stop_generic_handler )
   signal.signal( signal.SIGINT, sig_stop_generic_handler )
   #signal.signal( signal.SIGKILL, sig_stop_generic_handler ) # windows doesn't support this signal

   options.addr_is_https= 0
   if (options.addr):
      
      # Put slash at the end of URL:
      if ( options.addr[-1:] != "/" ):
	  options.addr=  options.addr + "/"
      
      
      # Unless disabled by --allow-non-ssl, force HTTPS even if HTTP is given in '-a'
      options.addr_is_https= 0
      if not options.allow_non_ssl:
	   pr = re.compile('^http:\/\/(.*)') # strip 'proto://' out
	   mr = pr.match( options.addr )
	   if mr:
		   options.addr= "https://" + mr.group(1)
		   
      if ( options.addr[:5] == "https" ):
         options.addr_is_https= 1
      
   # sms to destination        
   if options.destination and smstext:
      if options.verbose:   print "RunMode: sending single SMS to destinations(s) from commandline via [" + options.addr + "]"
      #detect if hexformat and decode to ascii
      if options.hexformat:
         from binascii import b2a_hex, a2b_hex
         smstext = a2b_hex(smstext)
         if options.verbose:   print "Hex decoded text: ", smstext

      # try to decode smstext before another processing, only if -u is given
      if options.uriformat:   smstext = urllib.unquote_plus(smstext)
      
      # HTTP request with retrying:		   
      process_http()

   # csv file 
   elif options.addr and options.csvfilename:
         if options.verbose:   print "RunMode: sending Bulk SMS from CSV file (" + options.csvfilename + ") via [" + options.addr + "]"
         csv_lines = parse_csvfile()
         # print how many parts we will send
         print "\nMESSAGES:%d\n" % (messages_count)
         #V if (not (options.allow_non_ssl and get_protocol(options.addr) == 'http')) and (not options.proxy_string): 
            #V # set connection to https
	    #V if options.verbose: print "prepare http(s) connection 2"
            #V http_conn = httplib.HTTPSConnection(get_domain(put_slash(options.addr)))
         process_http(csv_lines)
   # delivery reports
   elif options.delivery_reports:
      #V if (not (options.allow_non_ssl and get_protocol(options.addr) == 'http')) and (not options.proxy_string): 
      #V    # set connection to https
      #V    http_conn = httplib.HTTPSConnection(get_domain(put_slash(options.addr)))      
      delivery_reports()
   # get messages (presence)
   elif options.get_messages or options.get_ack:
      process_http_longtime( options.get_ack )
      
   # nothing   
   else:
      print "STATUS:0::2: Missing one of required options or text argument\n"
      parser.error("Missing one of required options or text argument")

   main()

# ChangeLog
# 12.9.2013  vpithart v3.11
#		-G (longtime mode) suppress connection errors up to 50 seconds; short-time errors like '500 internal server error'
#		on aweg's apache reload or restart are not reported as 'CONNECTED:0' now
# 14.11.2011 vpithart
#		New parameters -S and --Sf (instant speed management, changeable within CSV bulk)
# 19.10.2011 vpithart
#		New parameter -t (--template); template number (for ProfilSMS internal use)
# 30.8.2011  vpithart
#		Removing -D <file>; only plain -D is used for delivery reports. No dr_file is written anymore.
#		Rework for AWEG 3.9 (support of unicode; new UDH-style splitting of messages)
# 07.12.2010 kkostka
#            Returned status text was corrected on warning HTTP response codes: on the both 201 and 202 "OK " there is returned like on 200 
# 22.10.2008 vpithart
#            Error message from HTTP layer (404 not found etc.) now correctly appears in STATUS: line
# 9.10.2008 vpithart
#            URL-encoding of message text adjusted: space is now '%20' instead '+'
# 8.9.2008  vpithart
#            Using new 'connected' message with aNumber
#            CONNECTED:1:U_anumber=420234493002
# 13.8.2008 vpithart
#            Error codes 32x now parsed
# 5.8.2008  vpithart
#            --spaces now effective in -G mode
# 29.7.2008 vpithart
#           bugfix: CONFIG: is not multiplied while sending CSV file (-f mode)
# 15.5.2008  Vojtech Pithart (build numbers replaced by SVN revision numbers)
#	     version 2.0
#            removing all "serial" releated stuff
#            http_conn is initialized in process_http
# 23.4.2007  Lucie Leistnerova (build 37)
#            if server returns 315 (No matching route found), don't cancel 
#            sending and proceed
# 21.6.2006  lucie leistnerova (build 36)
#            fixed bug with multiple config lines
# 5.4.2006   lucie leistnerova (build 35)
#            argument --use-anumber added
# 5.4.2006   lucie leistnerova (build 34)
#            argument --email-reply added
# 30.3.2006  lucie leistnerova (build 33)
#            sorted drfile created
# 10.3.2006  lucie leistnerova (build 32)
#            report=1 added to url when drfile is given
#            backslash added to the end of url if only domain is given
# 9.3.2006   lucie leistnerova (build 31)
#            fixd bug with empty msg_ids
# 8.3.2006   lucie leistnerova (build 30)
#            printing 'CONFIG:' line before 'STATUS:' line 
# 27.2.2006  vpithart (build 29)
#            reports (-R) 1) does not add \n (no empty lines among REPORT: lines)
#                         2) outputting the word "END" after all REPORTs
# 14.2.2006  vpithart (build 27,28)
#            sending bv= and fv= URI parameters (backend's and frontend's version)
#            sending VERSION command via modem
#            -N option for passing frontend's identity through HTTP
# 16.1.2006  lucie leistnerova (build 26)
#            fixed problem with http/https connections
# 4.1.2006   lucie leistnerova (build 25)
#            fixed problem with overwritting proxy connection with direct https connection
# 12.12.2005 lucie leistnerova (build 24)
#            added class ConnectionError for proxy connect error
#            fixed problem with reconnecting
# 9.12.2005  lucie leistnerova (build 23)
#            fixed problem with printing ID:xxx in sending CSV file by modem 
# 8.12.2005  lucie leistnerova (build 22)
#            added function modem_quote that replace non-printing characters in message to ' '
# 6.12.2005  lucie leistnerova (build 21)
#            fixed report request
#            added functions get_rest and flush_end that works with url
# 4.12.2005  lucie leistnerova (version 1.1 build 20)
#            added sending throught proxy and https
#            added functions get_protocol and get_domain that parses url string
#            removed function encrypt
# 24.11.2005 epetr (build 19)
#            if net is disconnected when sending, retry to send according max_retry_count=20 and retry_pause=30
#            if net is disconnected when sending and retry failed return correct number of sent_sms and rest_sms
# 21.11.2005 epetr (build 18)
#            -f filename is printed as first line to -D dr_file
# 18.11.2005 epetr (build 17)
#            added encryption pulic/private keys, available with -e parameter 
# 15.11.2005 epetr (build 16)
#             support for -R <last_timestamp>[,bulk_id] added
# 14.11.2005 epetr (build 15)
#            support -D parametr added
# 14.11.2005 epetr (build 14)
#             After start, backend computes number of bodyparts (from length(s) of text, number of
#             destinations and true160 parameter) and displays total number of parts that is about
#             to send. This is for progress-bar in graphical front-ends.
# 9.11.2005  vpithart (build 13)
#             unbuffering output - dirty spaces (-M)
# 8.11.2005  epetr (build 12)
#             added proxy support
# 7.11.2005  epetr (build 11)
#             kill,int signal handling
#             support for delivery reports
# 27.9.2005  epetr (build 10)
#             timeout - pro DNS resolv s funkcnim timeout
#             timeout - pro http dotaz (funguje jen pri zadan IP, proto resim dns resolv samostatne
# 20.9.2005  epetr (build 9)
#             Pri nedostupne siti dlouhy timeout - unsolved, it hans on DNS reslove
#              Identifikace modemu jmenem
#            
# 28.7.2005  vpithart (build 8)
#            Speed-ups in send_sms function
# 21.7.2005  epetr
#            CSV is processed with same line order as was read from file
#             when modem line problem occure, status text with those sent_sms and rest_sms is returned
# 12.7.2005  vpithart
#            Version&build string moved onto top of the file, variable 'version'
#  4.7.2005  vpithart
#            parameter -q (verbose) renamed to logical -V
#             changes in protocol, now sending QUIET at begin,
#            then no responses are waited for except after DONE.
#             see "send_sms" function. read_lines blocked too much.
# xx.xx.xxxx epetr
#            init version
