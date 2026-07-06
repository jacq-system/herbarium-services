FROM ghcr.io/jacq-system/symfony-base:main@sha256:109348e4d5b6aaeb0871d063bf691291cfe52aff471b16d3a9a00dec711eed25
LABEL org.opencontainers.image.source=https://github.com/jacq-system/symfony
LABEL org.opencontainers.image.description="JACQ herbarium service Symfony"
ARG GIT_TAG
ENV GIT_TAG=$GIT_TAG

COPY  --chown=www:www htdocs /app
RUN chmod -R 777 /app/var
