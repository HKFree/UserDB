FROM alpine:3.24.0

RUN apk add bash wget
RUN apk add python3 py3-requests py3-dotenv py3-mysqlclient

COPY . /opt

RUN echo -e "#!/bin/sh\n/opt/downloadStvUserReport.py" > /etc/periodic/hourly/downloadStvUserReport.sh
RUN chmod +x /etc/periodic/hourly/downloadStvUserReport.sh

WORKDIR /opt

ENTRYPOINT ["/bin/bash", "-c", "crond -f -l 6 -L /dev/stdout"]
