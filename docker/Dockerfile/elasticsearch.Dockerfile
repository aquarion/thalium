FROM docker.elastic.co/elasticsearch/elasticsearch:8.3.0

RUN /usr/share/elasticsearch/bin/elasticsearch-plugin install ingest-attachment --batch
