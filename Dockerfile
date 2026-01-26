FROM ghcr.io/jacq-system/symfony-base:main@sha256:0481a4d99db9e6d84b16b8ee22f7c203e68f81af5abcfa92f6f460204252ace7
LABEL org.opencontainers.image.source=https://github.com/jacq-system/symfony
LABEL org.opencontainers.image.description="JACQ herbarium service Symfony"
ARG GIT_TAG
ENV GIT_TAG=$GIT_TAG

COPY  --chown=www:www htdocs /app
RUN chmod -R 777 /app/var
