FROM ghcr.io/jacq-system/symfony-base:main@sha256:19f62ce19abdae2dd6da2a6e8fab378c98b66260ff80caed8060d6cd35cfc02c
LABEL org.opencontainers.image.source=https://github.com/jacq-system/symfony
LABEL org.opencontainers.image.description="JACQ herbarium service Symfony"
ARG GIT_TAG
ENV GIT_TAG=$GIT_TAG

COPY  --chown=www:www htdocs /app
RUN chmod -R 777 /app/var
