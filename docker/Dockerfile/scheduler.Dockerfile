FROM thalium

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    supervisor \
  && rm -rf /var/lib/apt/lists/*

COPY docker/horizon/horizon.conf /etc/supervisor/conf.d/horizon.conf
COPY docker/horizon/supervisord.conf /etc/supervisor/supervisord.conf

RUN rm -rf /var/lib/apt/lists/*

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisor/supervisord.conf"]
WORKDIR /etc/supervisor/conf.d/