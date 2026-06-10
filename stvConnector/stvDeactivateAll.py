#!/usr/bin/python
#
# Volá "deactivate-user" ze SledovaniTV API pro uživatele kteří měli službu aktivní do včera a dnes už nemají
#
# Spouštět automaticky 1x za den brzo po půlnoci
#
import requests
import sys
from datetime import datetime, timedelta
from stvCommon import STV_API_URL, STV_PARTNER, STV_PASSWORD, udb_cursor, udb_conn

GO = False
if len(sys.argv) > 1 and sys.argv[1] == '--go':
  GO = True

def deactivate_all():

  query = ("SELECT Uzivatel_id, datum_do FROM UzivatelTelevizeAktivni WHERE datum_do = date_sub(curdate(), interval 1 day)")
  udb_cursor.execute(query)

  if udb_cursor.rowcount == 0:
    print("No users to deactivate today.")
    return

  total = udb_cursor.rowcount

  print(f"{total} user(s) to deactivate today.")

  counter = 0
  for row in udb_cursor.fetchall():
    counter += 1
    uid = row[0]
    print(f"User {counter}/{total} UID {uid}: deaktivace")

    url = f'{STV_API_URL}/deactivate-user?partner={STV_PARTNER}&password={STV_PASSWORD}&partnerid={uid}&all=1'

    if GO:
      try:
        resp = requests.get(url)
        resp.raise_for_status()
      except requests.RequestException as e:
        print(f"User {counter}/{total} UID {uid}: Request failed: {e}", file=sys.stderr)
        continue
    else:
      print(f"Dry run: would GET [{url}]")


deactivate_all()

print("Done.")
