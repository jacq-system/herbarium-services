FROM ghcr.io/jacq-system/symfony-base:main@sha256:e2611920692763b0aa631c565aeebf006715661b5bfa767fd6e069d79d419d97
LABEL org.opencontainers.image.source=https://github.com/jacq-system/symfony
LABEL org.opencontainers.image.description="JACQ herbarium service Symfony"
ARG GIT_TAG
ENV GIT_TAG=$GIT_TAG

COPY  --chown=www:www htdocs /app
RUN chmod -R 777 /app/var
