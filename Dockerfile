FROM ghcr.io/jacq-system/symfony-base:main@sha256:dd1f6f0f711a4454c9e25cf880a21120d351a9c5c6d5980f5dcb7762528fbe32
LABEL org.opencontainers.image.source=https://github.com/jacq-system/symfony
LABEL org.opencontainers.image.description="JACQ herbarium service Symfony"
ARG GIT_TAG
ENV GIT_TAG=$GIT_TAG

COPY  --chown=www:www htdocs /app
RUN chmod -R 777 /app/var
