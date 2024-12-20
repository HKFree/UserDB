#!/bin/bash
printenv | grep -v "no_proxy" >> /etc/environment
cron -f
