#!/usr/bin/python
#
# Volá activate-user ze SledovaniTV API pro uživatele kteří měli službu neaktivní a teď už mají aktivní.
# Souvisí s tlačítkem [Aktivovat] v UserDB.
#
# Spouštět z cronu každé 3 minuty.
#
import requests
import sys
from datetime import datetime, timedelta
from stvCommon import STV_API_URL, STV_PARTNER, STV_PASSWORD, udb_cursor, udb_conn

GO = False
if len(sys.argv) > 1 and sys.argv[1] == '--go':
  GO = True

def activate_all():

  query = """SELECT ut.id FROM UzivatelTelevize ut
    LEFT JOIN (SELECT DISTINCT Uzivatel_id FROM UzivatelTelevizeAktivni WHERE curdate() BETWEEN datum_od AND datum_do) aktivni_ids ON (aktivni_ids.Uzivatel_id=ut.id)
    WHERE
    aktivni_ids.Uzivatel_id IS NOT NULL
    AND ut.posledni_deaktivace IS NOT NULL
    AND (ut.posledni_aktivace IS NULL OR ut.posledni_aktivace < ut.posledni_deaktivace)
    ORDER BY ut.id
    """
  udb_cursor.execute(query)

  if udb_cursor.rowcount == 0:
    print("No users to activate now.")
    return

  total = udb_cursor.rowcount

  print(f"{total} user(s) to activate now.")

  counter = 0
  for row in udb_cursor.fetchall():
    counter += 1
    uid = row[0]
    print(f"User {counter}/{total} UID {uid}: aktivace")

    url = f'{STV_API_URL}/activate-user?partner={STV_PARTNER}&password={STV_PASSWORD}&partnerid={uid}'

    if GO:
      try:
        resp = requests.get(url)
        resp.raise_for_status()
        udb_cursor.execute("UPDATE UzivatelTelevize SET posledni_aktivace = NOW() WHERE id = %s", (uid,))
        udb_conn.commit()
      except requests.RequestException as e:
        print(f"User {counter}/{total} UID {uid}: Request failed: {e}", file=sys.stderr)
        continue
    else:
      print(f"Dry run: would GET [{url}]")


activate_all()

print("Done.")
