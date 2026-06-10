FROM alpine:3.24.0

RUN apk add bash wget
RUN apk add python3 py3-requests py3-dotenv py3-mysqlclient

COPY . /opt

RUN echo "/opt/downloadStvUserReport.py" > /etc/periodic/daily/downloadStvUserReport.sh
RUN chmod +x /etc/periodic/daily/downloadStvUserReport.sh

WORKDIR /opt

ENTRYPOINT ["crond", "-f"]
