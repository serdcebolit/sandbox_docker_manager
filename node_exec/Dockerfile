FROM node:16

RUN curl -L "https://github.com/docker/compose/releases/download/v2.15.1/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose \
    && chmod +x /usr/local/bin/docker-compose \
    && groupadd -g 600 bitrix \
    && useradd -g 600 -u 600 --no-create-home -p bitrix bitrix