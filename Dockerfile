FROM ghcr.io/jacq-system/symfony-base:main@sha256:77c6807c55188e0a550abd8745c3fb6b0a4e03550ad19037ee216cb5d1b30e04
LABEL org.opencontainers.image.source=https://github.com/jacq-system/symfony
LABEL org.opencontainers.image.description="JACQ herbarium service Symfony"
ARG GIT_TAG
ENV GIT_TAG=$GIT_TAG

COPY  --chown=www:www htdocs /app
RUN chmod -R 777 /app/var
