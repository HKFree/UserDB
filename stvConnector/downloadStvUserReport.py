#!/usr/bin/python
#
# Stahuje "report-users" ze SledovaniTV API (seznam hkfree-uid, která v daném měsíci využila službu)
#  a
# Zapisuje do UserDB tabulky UzivatelTelevizeReport
#
# Spouštět automaticky 1x za den
#
import os
import sys
import requests
import json
import MySQLdb
from datetime import datetime, timedelta
from dotenv import load_dotenv

load_dotenv()  # reads variables from a .env file and sets them in os.environ

# SledovaniTV connection details from environment variables
STV_API_URL = os.environ.get('STV_API_URL', 'https://sledovanitv.cz/partner/api')
STV_PARTNER = os.environ.get('STV_PARTNER')
STV_PASSWORD = os.environ.get('STV_PASSWORD')
if not STV_PARTNER or not STV_PASSWORD:
  print("STV_PARTNER and STV_PASSWORD environment variables must be set.", file=sys.stderr)
  sys.exit(1)

# MariaDB connection details from environment variables
USERDB_DB_HOST = os.environ.get('USERDB_DB_HOST', 'localhost')
USERDB_DB_PORT = int(os.environ.get('USERDB_DB_PORT', 3306))
USERDB_DB_USERNAME = os.environ.get('USERDB_DB_USERNAME')
USERDB_DB_PASSWORD = os.environ.get('USERDB_DB_PASSWORD')
USERDB_DB_NAME = os.environ.get('USERDB_DB_NAME')

if not all([USERDB_DB_USERNAME, USERDB_DB_PASSWORD, USERDB_DB_NAME]):
  print("MariaDB environment variables (USERDB_DB_USERNAME, USERDB_DB_PASSWORD, USERDB_DB_NAME) must be set.", file=sys.stderr)
  sys.exit(1)

try:
  conn = MySQLdb.connect(
    user=USERDB_DB_USERNAME,
    password=USERDB_DB_PASSWORD,
    host=USERDB_DB_HOST,
    port=USERDB_DB_PORT,
    database=USERDB_DB_NAME
  )
except MySQLdb.Error as e:
  print(f"Error connecting to MariaDB: {e}", file=sys.stderr)
  sys.exit(1)

cursor = conn.cursor()

def download_stv_user_report():
  # Determine month as YYYY-MM, from yesterday's date
  yesterday = datetime.now() - timedelta(days=1)
  year = yesterday.year
  month = yesterday.month
  month_str = '%d-%02d' % (year, month)

  # Build URL
  url = f'{STV_API_URL}/report-users?partner={STV_PARTNER}&password={STV_PASSWORD}&service=basic&month={month_str}'

  try:
    resp = requests.get(url)
    resp.raise_for_status()
  except requests.RequestException as e:
    print(f"Request failed: {e}", file=sys.stderr)
    sys.exit(1)

  try:
    data = resp.json()
  except Exception as e:
    print(f"Failed to parse JSON: {e}", file=sys.stderr)
    sys.exit(1)

  if not (isinstance(data, dict) and isinstance(data.get("users"), list)):
    print("Expected a JSON object with a 'users' array.", file=sys.stderr)
    sys.exit(1)

  total = len(data['users'])
  counter = 0
  # Process each item
  for item in data['users']:
    counter += 1
    print(f"User {counter}/{total}: {item}")

    uzivatel_id = item.get('partnerid')
    if not uzivatel_id or uzivatel_id == None:
      print("User record missing 'partnerid' field.", file=sys.stderr)
      continue
    uzivatel_id = int(uzivatel_id)
    sql = (
      "INSERT INTO UzivatelTelevizeReport (Uzivatel_id, rok, mesic, poznamka) "
      "VALUES (%s, %s, %s, %s) "
      "ON DUPLICATE KEY UPDATE poznamka=VALUES(poznamka), posledni_zmena=now()"
    )
    try:
      cursor.execute(sql, (uzivatel_id, year, month, item))
      conn.commit()
    except MySQLdb.Error as e:
      print(f"Error executing query for user {uzivatel_id}: {e}", file=sys.stderr)
      continue

    print(f"User {counter}/{total}: OK")
  cursor.close()

download_stv_user_report()
