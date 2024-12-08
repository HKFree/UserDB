FROM debian:10

RUN apt-get update

RUN apt-get install --yes \
  cron \
  curl \
  gcc make \
  perl \
  libmariadb-dev libmariadb-dev-compat

RUN cpan DBI \
  D/DV/DVEEDEN/DBD-mysql-4.052.tar.gz \
  JSON \
  Data::Dumper

COPY . /opt/fio2userdb

RUN cat /opt/fio2userdb/crontab.txt | crontab

WORKDIR /opt/fio2userdb

RUN ln -sf /proc/1/fd/1 /container.stdout
RUN ln -sf /proc/1/fd/2 /container.stderr

ENTRYPOINT [ "/opt/fio2userdb/cron-with-environment.sh" ]
