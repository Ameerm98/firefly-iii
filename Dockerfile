ARG BASE_TAG="latest"

FROM fireflyiii/base:${BASE_TAG}

# For more information about fireflyiii/base visit https://dev.azure.com/firefly-iii/BaseImage

ARG version
ENV VERSION=$version

ARG isodate
ENV ISODATE=$isodate

ARG gitrevision
ENV GITREVISION=$gitrevision


# static labels
LABEL org.opencontainers.image.authors="James Cole <james@firefly-iii.org>" org.opencontainers.image.url="https://github.com/firefly-iii/docker" org.opencontainers.image.documentation="https://docs.firefly-iii.org/" org.opencontainers.image.source="https://dev.azure.com/Firefly-III/_git/MainImage" org.opencontainers.image.vendor="James Cole <james@firefly-iii.org>" org.opencontainers.image.licenses="AGPL-3.0-or-later" org.opencontainers.image.title="Firefly III" org.opencontainers.image.description="Firefly III - personal finance manager" org.opencontainers.image.base.name="docker.io/fireflyiii/base:latest"

# dynamic labels

LABEL org.opencontainers.image.created="${ISODATE}"
LABEL org.opencontainers.image.version="${VERSION}"
LABEL org.opencontainers.image.revision="${GITREVISION}"

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY counter.txt /var/www/counter-main.txt
COPY date.txt /var/www/build-date-main.txt

USER root
RUN chmod uga+x /usr/local/bin/entrypoint.sh
USER www-data

#
# This assumes that download.zip is in the current directory
# you may have to download it manually first.
#

COPY download.zip /var/www/download.zip

RUN unzip -q /var/www/download.zip -d $FIREFLY_III_PATH && \
	chmod -R 775 $FIREFLY_III_PATH/storage && \
	rm /var/www/download.zip

COPY alerts.json /var/www/html/resources/alerts.json
