FROM ghcr.io/jacq-system/symfony-base:main@sha256:263e36da83c55d4caaab9babe8c05cdb94d05bde235059806aff5716f7c3de9c
LABEL org.opencontainers.image.source=https://github.com/jacq-system/symfony
LABEL org.opencontainers.image.description="JACQ herbarium service Symfony"
ARG GIT_TAG
ENV GIT_TAG=$GIT_TAG

COPY  --chown=www:www htdocs /app
RUN chmod -R 777 /app/var
