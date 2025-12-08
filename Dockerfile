FROM ghcr.io/jacq-system/symfony-base:main@sha256:2c68690036f2992a3c7eb94291a613e9d08df219324ba78e66e89ab0fa81b6d5
LABEL org.opencontainers.image.source=https://github.com/jacq-system/symfony
LABEL org.opencontainers.image.description="JACQ herbarium service Symfony"
ARG GIT_TAG
ENV GIT_TAG=$GIT_TAG

COPY  --chown=www:www htdocs /app
RUN chmod -R 777 /app/var
