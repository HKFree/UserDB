FROM php:8.2-apache-bookworm
RUN apt update
RUN apt install --yes libreoffice zip

RUN curl http://ftp.de.debian.org/debian/pool/contrib/f/fonts-ibm-plex/fonts-ibm-plex_5.1.3-1_all.deb --output /tmp/fonts-ibm-plex_5.1.3-1_all.deb
RUN dpkg -i /tmp/fonts-ibm-plex_5.1.3-1_all.deb && rm /tmp/fonts-ibm-plex_5.1.3-1_all.deb

COPY www /var/www/html
COPY templates/ /opt/templates
RUN chown www-data:www-data /var/www/

EXPOSE 80
