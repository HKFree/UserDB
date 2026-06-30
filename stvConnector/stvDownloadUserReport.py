#!/usr/bin/python
#
# Stahuje "report-users" ze SledovaniTV API (seznam hkfree-uid, která v daném měsíci využila službu)
#  a
# Zapisuje do UserDB tabulky UzivatelTelevizeReport
#  a
# Zapisuje do UserDB tabulky UzivatelTelevizeAktivni (novy uzivatel - prvni bezplatne obdobi)
#
# Spouštět automaticky 1x za den
#
import requests
import sys
from datetime import datetime, timedelta
import MySQLdb
from stvCommon import STV_API_URL, STV_PARTNER, STV_PASSWORD, udb_cursor, udb_conn

def insert_into_TelevizeReport(uzivatel_id, rok, mesic, poznamka):
  query = (
    "INSERT INTO UzivatelTelevizeReport (Uzivatel_id, rok, mesic, poznamka) "
    "VALUES (%s, %s, %s, %s) "
    "ON DUPLICATE KEY UPDATE poznamka=VALUES(poznamka), posledni_zmena=now()"
  )
  udb_cursor.execute(query, (uzivatel_id, rok, mesic, poznamka))
  udb_conn.commit()

def insert_into_TelevizeAktivni(uzivatel_id):
  query = ("SELECT count(*) FROM UzivatelTelevizeAktivni WHERE Uzivatel_id=%s AND datum_do > date_sub(curdate(), interval 1 year)")
  udb_cursor.execute(query, (uzivatel_id,))
  result = udb_cursor.fetchone()
  already_active_recently = result[0]
  if (already_active_recently > 0):
    return False

  poznamka = "První bezplatné období (do konce příštího měsíce)"

  query = (
    "INSERT INTO UzivatelTelevizeAktivni (Uzivatel_id, datum_od, datum_do, prvni_bezplatne_obdobi, poznamka) "
    "VALUES (%s, CURDATE(), LAST_DAY(DATE_ADD(CURDATE(), INTERVAL 1 MONTH)), 1, %s) "
    "ON DUPLICATE KEY UPDATE poznamka=VALUES(poznamka), posledni_zmena=now()"
  )
  udb_cursor.execute(query, (uzivatel_id, poznamka))
  udb_conn.commit()

  return True

def insert_stitek_pouziva_stv(uzivatel_id):
  try:
    stitek_id = 20263 # TV-sleduje ... Používá Sledování TV (alespoň jednou)
    query = (
      "INSERT INTO StitekUzivatele (Uzivatel_id, Stitek_id) "
      "VALUES (%s, %s) "
      "ON DUPLICATE KEY UPDATE Stitek_id=VALUES(Stitek_id)"
    )
    udb_cursor.execute(query, (uzivatel_id, stitek_id))
    udb_conn.commit()
  except:
    print(f"UID {uzivatel_id}: error štítek TV-sleduje")


def download_stv_user_report():
  # Determine month as YYYY-MM, from yesterday's date
  yesterday = datetime.now() - timedelta(days=1)
  year = yesterday.year
  month = yesterday.month
  month_str = '%d-%02d' % (year, month)

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

  # counters
  total = len(data['users'])
  skipped = 0
  updated = 0
  newcomers = 0
  counter = 0

  for item in data['users']:
    counter += 1
    print(f"User {counter}/{total}: {item}")

    uzivatel_id = item.get('partnerid')
    if not uzivatel_id or uzivatel_id == None:
      print("User record missing 'partnerid' field.", file=sys.stderr)
      skipped += 1
      continue
    uzivatel_id = int(uzivatel_id)

    try:
      insert_into_TelevizeReport(uzivatel_id, year, month, item)
      if insert_into_TelevizeAktivni(uzivatel_id):
        newcomers += 1
        print(f"User {counter}/{total} UID {uzivatel_id}: Prvni bezplatne obdobi")
    except MySQLdb.IntegrityError as e:
      print(f"User {counter}/{total} UID {uzivatel_id} missing in UserDB, skipped")
      skipped += 1
      continue
    except MySQLdb.Error as e:
      print(f"User {counter}/{total} UID {uzivatel_id}: Error executing query: {e}", file=sys.stderr)
      continue

    insert_stitek_pouziva_stv(uzivatel_id)

    updated += 1
    print(f"User {counter}/{total} UID {uzivatel_id}: Done")

  print(f"SledovaniTV User Report: Out of {total} records, {updated} updated in UserDB, {newcomers} newcomers, {skipped} skipped.")

download_stv_user_report()

print("Done.")
