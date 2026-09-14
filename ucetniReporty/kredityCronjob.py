#!/usr/bin/env python3
#
# Odesílá účetní report o spotřebovaných kreditech
#
# Spouštět z cronu 1x za měsíc
#
# Potřebuje environment variables:
# REPORT_EMAIL_FROM=automat@example.org
# REPORT_EMAIL_TO=accountant@example.org
# REPORT_EMAIL_CC=board@example.org
#
import os
import sys
import datetime
import subprocess
import smtplib
from email.message import EmailMessage
from pathlib import Path

# Load environment variables
email_from = os.environ.get("REPORT_EMAIL_FROM")
email_to = os.environ.get("REPORT_EMAIL_TO")
email_cc = os.environ.get("REPORT_EMAIL_CC")

# Calculate previous month and year
today = datetime.date.today()
first_of_this_month = today.replace(day=1)
last_month = first_of_this_month - datetime.timedelta(days=1)

year = last_month.strftime("%Y")
month = last_month.strftime("%m")

xlsx_filename = f"hkfree přehled spotřebovaných kreditů {year}-{month}.xlsx"

# Execute the report generation script
try:
    subprocess.run(
        [
            sys.executable,
            "./reportSpotrebovaneKredity.py",
            "--year",
            year,
            "--month",
            month,
            "--save-to-filename",
            xlsx_filename,
        ],
        check=True,
    )
except subprocess.CalledProcessError as e:
    print(f"Error generating report: {e}", file=sys.stderr)
    sys.exit(1)

subject = f"účetní report o spotřebovaných kreditech {month}/{year}"

# Build the email
msg = EmailMessage()
msg["Subject"] = subject
msg["From"] = email_from
msg["To"] = email_to
if email_cc:
    msg["Cc"] = email_cc
msg.set_content("")

# Attach the XLSX file
file_path = Path(xlsx_filename)
if file_path.exists():
    with open(file_path, "rb") as f:
        file_data = f.read()
        msg.add_attachment(
            file_data,
            maintype="application",
            subtype="vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            filename=file_path.name,
        )
else:
    print(f"Attachment file {xlsx_filename} not found.", file=sys.stderr)
    sys.exit(1)

# Send the email via SMTP server
try:
    with smtplib.SMTP("smtp.hkfree.org") as smtp:
        # Resolve all recipients for the SMTP envelope
        recipients = [email_to] + ([email_cc] if email_cc else [])
        smtp.send_message(msg, to_addrs=recipients)
    print(f'Report "{xlsx_filename}" odeslán na {email_to}, cc:{email_cc}, subject "{subject}"\n')
except Exception as e:
    print(f"Failed to send email: {e}", file=sys.stderr)
    sys.exit(1)
