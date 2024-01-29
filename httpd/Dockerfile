FROM centos:7 as docker

RUN yum update -y && yum install -y curl

RUN curl https://get.docker.com/ | sh

FROM ghcr.io/serdcebolit/httpd:82

RUN curl -L "https://github.com/docker/compose/releases/download/v2.15.1/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose \
    && chmod +x /usr/local/bin/docker-compose

COPY --from=docker /usr/bin/docker /usr/bin/docker

COPY ./entrypoint.sh /root/