FROM docker.elastic.co/elasticsearch/elasticsearch:8.8.0

RUN /usr/share/elasticsearch/bin/elasticsearch-plugin install ingest-attachment --batch
