FROM ghcr.io/jacq-system/symfony-base:main@sha256:b7f9c14d1c7fc14f9786ff138727afb1a8c28793524b96b6857feb946a52460d
LABEL org.opencontainers.image.source=https://github.com/jacq-system/symfony
LABEL org.opencontainers.image.description="JACQ herbarium service Symfony"
ARG GIT_TAG
ENV GIT_TAG=$GIT_TAG

COPY  --chown=www:www htdocs /app
RUN chmod -R 777 /app/var
