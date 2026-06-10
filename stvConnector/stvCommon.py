#!/usr/bin/python
import os
import sys
import MySQLdb
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
  udb_conn = MySQLdb.connect(
    user=USERDB_DB_USERNAME,
    password=USERDB_DB_PASSWORD,
    host=USERDB_DB_HOST,
    port=USERDB_DB_PORT,
    database=USERDB_DB_NAME
  )
except MySQLdb.Error as e:
  print(f"Error connecting to MariaDB: {e}", file=sys.stderr)
  sys.exit(1)

udb_cursor = udb_conn.cursor()
