FROM alpine:3.24.0

RUN apk add bash wget tzdata
RUN apk add python3 py3-requests py3-dotenv py3-mysqlclient py3-openpyxl

ENV TZ=Europe/Prague

COPY . /opt

RUN rm /var/spool/cron/crontabs/root
RUN ln -s /opt/crontab.txt /var/spool/cron/crontabs/root

WORKDIR /opt
