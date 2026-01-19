FROM ghcr.io/jacq-system/symfony-base:main@sha256:67a00e51fc5b8de9b8749d164fb537f1754aee01f450e065fa21ae8b173d917a
LABEL org.opencontainers.image.source=https://github.com/jacq-system/symfony
LABEL org.opencontainers.image.description="JACQ herbarium service Symfony"
ARG GIT_TAG
ENV GIT_TAG=$GIT_TAG

COPY  --chown=www:www htdocs /app
RUN chmod -R 777 /app/var
